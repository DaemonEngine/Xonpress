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
 * img_path [upload_basedir/mapshots] Server path used to check if a screenshot exists
 * img_url  [upload_baseurl/mapshots] Base URL used to serve the images
 * class    [xonpress_screenshot]     CSS class for the image element
 * on_error/on_noimage []             HTML to be displayed in case of error.
 
 
== Installation ==

Nothing special, just extract the files in wp-contents/plugins/xonpress

== Frequently Asked Questions ==

= Will it work with other games based on Darkplaces or similar engines? =
Maybe, they need to respond to getstatus.