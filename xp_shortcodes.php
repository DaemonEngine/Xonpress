<?php 



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