<?php
/**
 * Plugin Name: Xonpress
 * Plugin URI: https://github.com/mbasaglia/Xonpress
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


function xonpress_showstatus( $attributes )
{
	$attributes = shortcode_atts( array (
		'ip'   => '127.0.0.1',
		'port' => 26000,
		'public_host' => '',
	), $attributes );

	return DarkPlaces()->status_html($attributes["ip"],$attributes["port"],
		$attributes['public_host']);
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
