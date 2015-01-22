=== Xonpress ===
Tags: shortcode xonotic
Donate link: TODO
License: GPLv3+
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Xonotic Integration for Wordpress.

== Description ==

Adds shortcodes to display Xonotic server information on a Wordpress page.

= xon_status =

Shows server status/info.

 * ip          [127.0.0.1] Connection address to query the server
 * port        [26000]     Connection (UDP) port to query the server
 * public_host [ip]        Address to show on the page
 * stats_url   [empty]     Url to link to server stats
 
= xon_players =

Shows a list of players currently in the server.

 * ip          [127.0.0.1] Connection address to query the server
 * port        [26000]     Connection (UDP) port to query the server
 
= xon_img =

Displays a screenshot of the map currently being player.
Note: maps need to be extracted and have names like mymap.jpg (all lowercase),
you can use this script to extract them: 
https://github.com/Evil-Ant-Colony/Xonotic-EAC-Scripts/blob/master/pk3/pk3-get-screenshots

 * ip       [127.0.0.1]               Connection address to query the server
 * port     [26000]                   Connection (UDP) port to query the server
 * class    [xonpress_screenshot]     CSS class for the image element
 
= xon_mapinfo =

Shows information about a map.
It calls the theme template 'xonotic-map', passing (as a global variable)
$mapinfo, which is an instance of Mapinfo.

 * mapinfo     Mapinfo file, if provided all fields are initialized from that
 * title       Map title
 * description One line description
 * author      Name of the author(s)
 * gametypes   Whitespace separated list of gametype identifiers
 * screenshot  Explicit screenshot URL (otherwise detected automatically)
 
== Options ==
All options are prefixed by xonpress_settings_ and can be set in
the admin area under Xonpress > Settings

= maps_dir =

Base path to retrieve map information, screenshots and mapinfo are searched in
the subdirectory /maps while pk3 files are searched directly here.

= maps_url =

URL corresponding to xonpress_maps_dir

= qfont =

Whether special darkplaces characters should be translated into Unicode (Default: 1)

== Theme Requirements ==

* img/noscreenshot.png
** Image shown when a map screenshot cannot be found.
* xonotic-map.php
** Used by [xon_mapinfo], it should use the global $mapinfo

== Installation ==

Nothing special, just extract the files in wp-contents/plugins/xonpress

== Frequently Asked Questions ==

= Will it work with other games based on Darkplaces or similar engines? =
Maybe, they need to respond to getstatus.