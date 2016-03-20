=== Xonpress ===
Tags: shortcode xonotic
Donate link: https://www.paypal.me/MattBas
License: GPLv3+
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Requires at least: 3.5
Tested up to: 4.4.2
Contributors: mattiabasaglia
Stable tag: master
Tags: xonotic, unvanquished

Xonotic/Unvanquished integration for Wordpress.

== Description ==

Adds shortcodes to display Xonotic server information on a Wordpress page.

= xon_status =

Shows server status/info.

 * server      [xon://127.0.0.1:26000] Connection address to query the server
 * public_host [same as in server]     Address to show on the page
 * stats_url   [empty]                 Url to link to server stats
 
= xon_players =

Shows a list of players currently in the server.

 * server      [xon://127.0.0.1:26000] Connection address to query the server
 
= xon_img =

Displays a screenshot of the map currently being player.
Note: maps need to be extracted and have names like mymap.jpg (all lowercase),
you can use this script to extract them: 
https://github.com/Evil-Ant-Colony/Xonotic-EAC-Scripts/blob/master/pk3/pk3-get-screenshots

 * server   [xon://127.0.0.1:26000] Connection address to query the server
 * class    [xonpress_screenshot]   CSS class for the image element

= xon_player_number =

Display a summary of the number of players in the server.
The format is player/total (bots)

 * server      [xon://127.0.0.1:26000] Connection address to query the server

= xon_server_list =

Shows a table with server details. It can show a fixed list of servers or
get it from the master server.

 * master           Master server address
 * master_protocol  Protocol name to use a default master server
 * servers          A list of servers

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

= unvicon_prefix / unvicon_suffix =

Prefix/suffix used to link to the unvicons in Daemon strings.

= color_min_luma / color_max_luma =

Value between 0 and 1 to adjust the parsed colors to light or dark backgrounds.

== Theme Requirements ==

* img/noscreenshot.png
  * Image shown when a map screenshot cannot be found.
* xonotic-map.php (template)
  * Used by [xon_mapinfo], it should use the global $mapinfo

== Installation ==

Nothing special, just extract the files in wp-contents/plugins/xonpress

== Changelog ==

= 0.2 =
 * Unvanquished integration
 * Master server list

= 0.1 =
 * Xonotic integration

== Upgrade Notice ==

Just pull the latest changes...

== Screenshots ==

None at the moment.

== Frequently Asked Questions ==

= Will it work with other games based on similar engines? =
Maybe, they need to respond to getstatus.
