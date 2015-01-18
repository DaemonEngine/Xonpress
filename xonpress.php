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
}

class DarkPlaces_ConnectionWp_Factory
{
	function build($host, $port)
	{
		return new DarkPlaces_ConnectionWp($host,$port);
	}
}

function xonpress_status( $attributes )
{
	$attributes = shortcode_atts( array (
		'ip'   => '127.0.0.1',
		'port' => 26000,
		'public_host' => '',
		'stats_url' => '',
	), $attributes );

	return DarkPlaces()->status_html( $attributes["ip"], $attributes["port"],
		$attributes['public_host'], $attributes['stats_url'] );
}

function xonpress_players( $attributes )
{
	$attributes = shortcode_atts( array (
		'ip'   => '127.0.0.1',
		'port' => 26000,
	), $attributes );

	return DarkPlaces()->players_html( $attributes["ip"], $attributes["port"] );
}

function xonpress_screenshot( $attributes )
{
	$attributes = shortcode_atts( array (
		'ip'        => '127.0.0.1',
		'port'      => 26000,
		'class'     => 'xonpress_screenshot',
		'on_error'  => '', // TODO these should be placeholder images (maybe just 1?)
		'on_noimage'=> ''
	), $attributes );

	$status = DarkPlaces()->status( $attributes["ip"], $attributes["port"] );
	if ( $status["error"] )
		return $attributes["on_error"];
		
	$image = strtolower($status["mapname"]).".jpg";
	if ( !file_exists(get_option('xonpress_maps_dir')."/maps/$image") )
		return $attributes["on_noimage"];
		
	return "<img class='{$attributes['class']}' ".
		"src='".get_option('xonpress_maps_url')."/maps/$image' ".
		"alt='Screenshot of {$status['mapname']}' ".
		"/>";
}

function xonpress_mapinfo( $attributes ) 
{
	if ( empty($attributes['title']) && empty($attributes['mapinfo']) )
		return '';
	
	$attributes = shortcode_atts( array (
		'mapinfo'      => '',
		'title'        => null,
		'description'  => null,
		'author'       => null,
		'gametypes'    => null,
		'screenshot'   => '',
		'download'     => '',
		'sources'      => '',
	), $attributes );
	
	global $mapinfo;
	$mapinfo = new Mapinfo();
	
	$maps_url = get_option('xonpress_maps_url');
	$maps_dir = get_option('xonpress_maps_dir');
	
	if ( $attributes['mapinfo'] )
		$mapinfo->load_file("$maps_dir/maps/{$attributes['mapinfo']}");
	
	foreach ( array('title', 'description', 'author', 'sources') as $key)
		if ( isset($attributes[$key]) )
			$mapinfo->$key = $attributes[$key];
	if ( isset($attributes['gametypes']) )
		$mapinfo->gametypes = explode(' ', $attributes['gametypes']);
	
	if ( $attributes['screenshot'] )
	{
		$mapinfo->screenshot = $attributes['screenshot'];
	}
	else
	{
		$image = strtolower($mapinfo->name).".jpg";
		if ( file_exists("$maps_dir/maps/$image") )
			$mapinfo->screenshot = "$maps_url/maps/$image";
	}
	
	if ( $attributes['download'] )
	{
		$mapinfo->download = $attributes['download'];
	}
	else
	{
		$pk3 = $mapinfo->name.".pk3";
		if ( file_exists("$maps_dir/$pk3") )
			$mapinfo->download = "$maps_url/$pk3";
	}
	
	ob_start();
	get_template_part('xonotic-map');
	$buffer = ob_get_contents();
	@ob_end_clean();
	return $buffer;
}

function xonpress_initialize()
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
	
	Xonpress_Settings::set_defaults();
	
}

class Xonpress_Settings
{
	private $id = 'xonpress_settings';
	
	function __construct()
	{
		add_action( 'admin_init', array($this,'admin_init') );
		add_action( 'admin_menu', array($this, 'admin_menu') );
	}
	
	static function set_defaults()
	{
		$upload_dir = wp_upload_dir();
		add_option('xonpress_maps_dir',"{$upload_dir['basedir']}/maps");
		add_option('xonpress_maps_url',"{$upload_dir['baseurl']}/maps");
	}

	function admin_init()
	{
		register_setting( $this->id, $this->id, array($this,'sanitize') );
	
		add_settings_section(
			'xonpress_section', 
			'Options', 
			function(){}, 
			$this->id
		);

		add_settings_field( 
			'xonpress_maps_dir', 
			'Map Directory', 
			array($this,'render_xonpress_maps_dir'), 
			$this->id, 
			'xonpress_section' 
		);

		add_settings_field( 
			'xonpress_maps_url', 
			'Map URL', 
			array($this,'render_xonpress_maps_url'),
			$this->id, 
			'xonpress_section' 
		);
	}
	
	function admin_menu()
	{
		if ( !is_admin() )
			return;
		
		add_options_page( 
			'Xonpress', 
			'Xonpress', 
			'manage_options', 
			$this->id, 
			array($this,'menu_page')
		);
	}
	
	function menu_page()
	{
		?>
		<form action='options.php' method='post'>
			
			<h2>Xonpress</h2>
			
			<?php
			settings_fields( $this->id );
			do_settings_sections( $this->id );
			submit_button();
			?>
			
		</form>
		<?php
	}
	
	function sanitize( $input )
	{
		$upload_dir = wp_upload_dir();
		$clean_input = array();
		
		if ( isset($input['xonpress_maps_url']) )
		{
			$string = trim($input['xonpress_maps_url']);
			if ( $string == "" )
				$string = "{$upload_dir['baseurl']}/maps";
			$clean_input['xonpress_maps_url'] = $string;
		}
		
		if ( isset($input['xonpress_maps_dir']) )
		{
			$string = trim($input['xonpress_maps_dir']);
			if ( $string == "" )
				$string = "{$upload_dir['basedir']}/maps";
			$clean_input['xonpress_maps_dir'] = $string;
		}
		
		return $clean_input;
	}
	
	function render_field($name)
	{
		$value = esc_attr(get_option($name));
		echo "<input type='text' id='$name' name='$name' value='$value' />";
	}
	
	function render_xonpress_maps_dir()
	{
		$this->render_field('xonpress_maps_dir');
	}
	
	function render_xonpress_maps_url()
	{
		$this->render_field('xonpress_maps_url');
	}
}

if ( !function_exists('add_shortcode') )
{
	function shortcode_atts($a, $b) 
	{
		foreach ( $b as $k => $v )
			$a[$k] = $v;
		return $a;
	}
	function wp_upload_dir() 
	{ 
		return array(
			'basedir' => dirname(__FILE__)."/../../uploads",
			'baseurl' => "http://",
		); 
	}
	
	echo xonpress_status(array());
	echo "\n\n";
	echo xonpress_players(array());
	echo "\n\n";
	echo xonpress_mapinfo(array('mapinfo'=>'canterlot_v005.mapinfo'));
}
else
{
	add_shortcode('xon_status',  'xonpress_status');
	add_shortcode('xon_img',     'xonpress_screenshot');
	add_shortcode('xon_players', 'xonpress_players');
	add_shortcode('xon_mapinfo', 'xonpress_mapinfo');
	
	register_activation_hook( __FILE__, 'xonpress_initialize' );
	$xonpress_settings = new Xonpress_Settings();
	
	DarkPlaces()->connection_factory = new DarkPlaces_ConnectionWp_Factory();
}
