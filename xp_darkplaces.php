<?php


require_once("darkplaces.php");
class DarkPlaces_ConnectionWp extends DarkPlaces_ConnectionCached
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
		$table = $wpdb->prefix.DarkPlaces_ConnectionWp::table_name;
		
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

class DarkPlaces_ConnectionWp_Factory
{
	function build($host, $port)
	{
		return new DarkPlaces_ConnectionWp($host,$port);
	}
}

DarkPlaces()->connection_factory = new DarkPlaces_ConnectionWp_Factory();