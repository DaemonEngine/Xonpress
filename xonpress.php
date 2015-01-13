<?php
/**
 * Plugin Name: Xonpress
 * Plugin URI: TODO://URI_Of_Page_Describing_Plugin_and_Updates
 * Description: Xonotic Integration for Wordpress
 * Version: 0.1
 * Author: Mattia Basaglia
 * Author URI: https://github.com/mbasaglia
 * License: GPLv3+
 */
/**
 * \file
 * \author Mattia Basaglia
 * \copyright Copyright 2015 Mattia Basaglia
 * \section License
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
 
require_once("darkplaces.php");
require_once("table.php");


function xonpress_showstatus( $attributes )
{
	$attributes = shortcode_atts( array (
		'ip'   => '127.0.0.1',
		'port' => 26000,
	), $attributes );

	$dpudp = new XonPress_UDP($attributes["ip"],$attributes["port"]);
	$status = $dpudp->status();
	$public_host = empty($attributes['public_host']) ? $attributes["ip"] : $attributes['public_host'];

	$html = "";
	$status_table = new HTML_Table('xonpress_status');

	$status_table->simple_row("Server",$public_host.":".$attributes["port"]);

	if ( $status["error"] )
	{
		$status_table->simple_row("Error", "<span class='xonpress_error'>Could not retrieve server info</span>", false);
		$html .= $status_table;
	}
	else
	{
		$status_table->simple_row("Name", $status["hostname"]);
		$status_table->simple_row("Map", $status["mapname"]);
		$status_table->simple_row("Players", 
			"{$status['clients']}/{$status['sv_maxclients']} ({$status['bots']} bots)");

		$html .= $status_table;

		if (!empty($status["players"]))
		{
			$players = new HTML_Table('xonpress_players');
			$players->header_row(array("Name", "Score", "Ping"));

			foreach ( $status["players"] as $player )
				$players->data_row( array (
					$player->name,
					$player->score == -666 ? "spectator" : $player->score,
					$player->ping,
				));
			$html .= $players;
		}
	}

	return $html;
}

if ( !function_exists('add_shortcode') )
{
	function shortcode_atts($a, $b) { return $a; }
	echo xonpress_showstatus(array());
}
else
{
	add_shortcode('xon_status', 'xonpress_showstatus');
}
