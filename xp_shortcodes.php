<?php 
/**
 * \file
 *
 * \author Mattia Basaglia
 *
 * \copyright Copyright (C) 2015-2016 Mattia Basaglia
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

require_once("engine.php");

function xonpress_status( $attributes )
{
    $attributes = shortcode_atts( array (
        'server'   => 'xon://127.0.0.1:26000',
        'public_host' => '',
        'stats_url' => '',
    ), $attributes );

    return Controller()->status_html(
        $attributes["server"],
        $attributes['public_host'],
        $attributes['stats_url']
    );
}

function xonpress_players( $attributes )
{
    $attributes = shortcode_atts( array (
        'server'   => 'xon://127.0.0.1:26000',
    ), $attributes );

    return Controller()->players_html( $attributes["server"] );
}

function xonpress_screenshot( $attributes )
{
    $attributes = shortcode_atts( array (
        'server'   => 'xon://127.0.0.1:26000',
        'class'     => 'xonpress_screenshot',
    ), $attributes );

    $status = Controller()->status( $attributes["server"] );

    $image_url = get_stylesheet_directory_uri()."/img/noscreenshot.png";

    if ( !$status["error"] )
    {
        $image = strtolower($status["mapname"]).".jpg";
        if ( file_exists(Xonpress_Settings::get_option('maps_dir')."/maps/$image") )
            $image_url = Xonpress_Settings::get_option('maps_url')."/maps/$image";
    }

    return "<img class='{$attributes['class']}' ".
        "src='$image_url' ".
        "alt='Screenshot of {$status['mapname']}' ".
        "/>";
}

function xonpress_player_number ( $attributes )
{
    $attributes = shortcode_atts( array (
        'server'   => 'xon://127.0.0.1:26000',
    ), $attributes );

    return Controller()->player_number( $attributes["server"] );
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

    $maps_url = Xonpress_Settings::get_option('maps_url');
    $maps_dir = Xonpress_Settings::get_option('maps_dir');

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
        else
            $mapinfo->screenshot = get_template_directory_uri()."/img/noscreenshot.png";
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
