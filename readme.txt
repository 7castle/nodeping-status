=== NodePing Status ===
Contributors: nosilver4u
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=MKMQKCBFFG3WW
Tags: nodeping, uptime, status
Requires at least: 3.9
Tested up to: 3.9.1
Stable tag: 1.0.0
License: GPLv3

Display NodePing Status page within Wordpress.

== Description ==

Allows you to embed a NodePing status page within WordPress using a simple shortcode. Uses the NodePing API to pull data directly, and allows you to configure how many days are used for uptime stats.

The NodePing status page can be embedded with this shortcode:

[nodeping_status]

You can optionally specifiy how many days of uptime to display (days), and how many days to use to calculate total uptime (total):

[nodeping_status days="7" total="30"]</pre>

[NodePing](http://nodeping.com/) is a Server and Website monitoring service. To use this plugin, you need a [nodeping.com](http://nodeping.com) account.

== Installation ==

1. Upload the 'nodeping-status' plugin to your '/wp-content/plugins/' directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Visit the settings page to enter your API Token.
1. Insert shortcode [nodeping_status] on a page.
1. Done!

== Changelog ==

= 1.0.0 =
* Initial version
