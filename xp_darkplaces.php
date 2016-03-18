<?php
/**
 * \file
 * \author Mattia Basaglia
 * \copyright Copyright 2015-2016 Mattia Basaglia
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

class Engine_ConnectionWp extends Engine_ConnectionCached
{
	const table_name = 'xonpress_cache';
	protected $cache_minutes = 1;

	function __construct($host="127.0.0.1", $port=26000) 
	{
		parent::__construct($host, $port);
	}
	
	protected function get_cached_request($request)
	{
		global $wpdb;
		$table = $wpdb->prefix.self::table_name;
		return $wpdb->get_var( $wpdb->prepare("
			SELECT response FROM $table WHERE 
			server = %s 
			AND port = %d 
			AND query = %s 
			AND time > TIMESTAMPADD(minute,%d, CURRENT_TIMESTAMP)",
			$this->host,
			$this->port,
			$request,
			-(int)$this->cache_minutes
		));
	}
	protected function set_cached_request($request, $response)
	{
		global $wpdb;
		$table = $wpdb->prefix.self::table_name;
		$wpdb->query($wpdb->prepare("
			INSERT INTO $table (server, port, query, response) 
			VALUE ( %s, %d, %s, %s )
			ON DUPLICATE KEY UPDATE response = %s",
			$this->host,
			$this->port,
			$request,
			$response, 
			$response
		));
	}
	
	static function create_table()
	{
		global $wpdb;
		$table = $wpdb->prefix.Engine_ConnectionWp::table_name;
		
		$charset_collate = $wpdb->get_charset_collate(); // NOTE: Requires WordPress 3.5
		$sql = "CREATE TABLE $table (
			server varchar(16) NOT NULL,
			port int(11) NOT NULL,
			query varchar(64) NOT NULL,
			response text NOT NULL,
			time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (server,port,query)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
}

class Engine_ConnectionWp_Factory
{
	function build($host, $port)
	{
		return new Engine_ConnectionWp($host,$port);
	}
}

DarkPlaces()->connection_factory = new Engine_ConnectionWp_Factory();
