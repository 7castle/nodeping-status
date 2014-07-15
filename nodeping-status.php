<?php
/**
 * Display Nodeping status page within WordPress.
 * @version 1.0.0
 * @package NodePing_Status
 */
/*
Plugin Name: NodePing Status
Plugin URI: http://wordpress.org/extend/plugins/nodeping-status/
Description: Display NodePing Status page within Wordpress.
Author: Shane Bishop
Text Domain: nodeping-status
Version: 1.0.0
Author URI: http://www.shanebishop.net/
License: GPLv3
*/

// Constants
define('NODEPING_STATUS_DOMAIN', 'nodeping-status');
// the folder where we install optimization tools
//define('EWWW_IMAGE_OPTIMIZER_TOOL_PATH', WP_CONTENT_DIR . '/ewww/');
// this is the full path of the plugin file itself
define('NODEPING_STATUS_PLUGIN_FILE', __FILE__);
// this is the path of the plugin file relative to the plugins/ folder
define('NODEPING_STATUS_PLUGIN_FILE_REL', 'nodeping-status/nodeping-status.php');

// we check for safe mode and exec, then also direct the user where to go if they don't have the tools installed
function nodeping_status_notice() {
	// if no api secret entered, display the warning
	echo "<div id='nodeping-status-warning' class='error'><p>" . sprintf(__('To display a NodePing status page, you must first enter your API token on the %1$s.', NODEPING_STATUS_DOMAIN), "<a href='options-general.php?page=" . NODEPING_STATUS_PLUGIN_FILE_REL . "'>" . __('Settings Page', NODEPING_STATUS_DOMAIN) . "</a>") . "</p></div>";
}

define('NODEPING_STATUS_VERSION', '100');

/**
 * Hooks
 */
// variable for plugin settings link
$plugin = plugin_basename (NODEPING_STATUS_PLUGIN_FILE);
add_filter("plugin_action_links_$plugin", 'nodeping_status_settings_link');
add_action('admin_init', 'nodeping_status_admin_init');
add_action('admin_menu', 'nodeping_status_admin_menu', 60);
add_action('admin_enqueue_scripts', 'nodeping_status_scripts');
add_action('wp_enqueue_scripts', 'nodeping_status_scripts');
register_deactivation_hook(NODEPING_STATUS_PLUGIN_FILE, 'nodeping_status_network_deactivate');

/**
 * Plugin initialization function
 */
function nodeping_status_init() {
	load_plugin_textdomain(NODEPING_STATUS_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

// Plugin initialization for admin area
function nodeping_status_admin_init() {
	nodeping_status_init();
	if ( ! get_option('nodeping_status_api_token') ) {
		add_action( 'network_admin_notices', 'nodeping_status_notice' );
		add_action( 'admin_notices', 'nodeping_status_notice' );
	}
	// register the settings
	register_setting( 'nodeping_status_options', 'nodeping_status_api_token', 'nodeping_status_token_verify' );
}

// adds the bulk optimize and settings page to the admin menu
function nodeping_status_admin_menu() {
	$nps_options_page = add_options_page(
		'NodePing Status',		//Title
		'NodePing Status',		//Sub-menu title
		'manage_options',		//Security
		NODEPING_STATUS_PLUGIN_FILE,	//File to open
		'nodeping_status_options'	//Function to call
	);
}

// enqueue custom scripts/stylesheets
function nodeping_status_scripts($hook) {
	wp_enqueue_style('datatable', plugins_url('css/datatable.css', __FILE__));
	wp_enqueue_style('fontawesome', plugins_url('css/font-awesome.min.css', __FILE__));
}

// adds a link on the Plugins page settings
function nodeping_status_settings_link($links) {
	// load the html for the settings link
	$settings_link = '<a href="options-general.php?page=' . plugin_basename(NODEPING_STATUS_PLUGIN_FILE) . '">' . __('Settings', NODEPING_STATUS_DOMAIN) . '</a>';
	// load the settings link into the plugin links array
	array_unshift($links, $settings_link);
	// send back the plugin links array
	return $links;
}

// checks the api token for proper results
function nodeping_status_token_verify ( $api_token ) {
	if ( ! $api_token ) {
		return false;
	}
	$api_token = trim( $api_token );
	$url = "https://api.nodeping.com/api/1/accounts?token=$api_token";
	$result = wp_remote_get( $url );
	if (is_wp_error($result)) {
		return false;
	} elseif (!empty($result['body']) && preg_match('/parent.+name.+status.+count/', $result['body'])) {
		return $api_token;
	}
}

// generates the status table when the shortcode is used
function nodeping_status_shortcode ( $atts ) {
	$api_token = get_option('nodeping_status_api_token');
	// don't do anything more if we don't have a valid token
	if ( ! $api_token ) {
		return '';
	}
	// set default attribute values if the user didn't set them
	$atts = shortcode_atts( array(
		'days' => '7',
		'total' => '30',
	), $atts);
	// retrieve a list of checks for the provided token
	$url = "https://api.nodeping.com/api/1/checks?token=$api_token";
	$result = wp_remote_get( $url );
	if (is_wp_error($result)) {
		return '';
	} elseif (!empty($result['body']) && preg_match('/_id.+label.+type/', $result['body'])) {
		$output = '';
		$date_offset = 0;
		$alternate = true;
		// set the timezone to UTC, because that is what the API uses
		date_default_timezone_set( 'UTC' );
		// if someone tried to set the total smaller than the number of days being queried, fix it, otherwise we won't be able to display the requested number of days
		if ( $atts['total'] < $atts['days'] ) {
			$atts['total'] = $atts['days'];
		}
		// set the beginning date for the uptime query
		$start_date = date( "Y-m-d", time() - 86400 * $atts['total'] );
		$output .= "<table id='statusgrid'>\n<thead>\n<tr>\n";
		$output .= "<th>&nbsp;</th>\n";
		$output .= "<th class='center'>" . __('Status', NODEPING_STATUS_DOMAIN) . "</th>\n";
		while ( $date_offset < $atts['days'] ) {
			$current_date = date( "Y-m-d", time() - 86400 * $date_offset );
			$date_offset++;
			$output .= "<th class='center'>$current_date</th>\n";
		}
		$output .= "<th class='center'>" . $atts['total'] . " " . __('Days', NODEPING_STATUS_DOMAIN) . "</th>\n";
		$output .= "</tr>\n</thead>\n<tbody>\n";
		// put the list of checks we retrieved into an array (the API gives us JSON data)
		$checks = json_decode( $result['body'], TRUE );
		foreach ( $checks as $id => $check ) {
			$date_offset = 0;
			// if a check isn't marked as public, we won't display it (might be configurable in the future)
			if ( empty ( $check['public'] ) ) {
				continue;
			}
			if ( $alternate ) {
				$tr_tag = '<tr class="odd">';
			} else {
				$tr_tag = '<tr class="even">';
			}
			$output .= "$tr_tag\n";
			$output .= "<td class='sorting_1'>" . $check['label'] . "</td>\n";
			// query the API for current status
			$url = "https://api.nodeping.com/api/1/checks/$id?token=$api_token&lastresult=1";
			$result = wp_remote_get( $url );
			if ( ! is_wp_error($result) ) {
				$lastresult = json_decode( $result['body'], TRUE );
				// if the check is in a failed state...
				if ( empty( $lastresult['lastresult']['su'] ) ) {
					$output .= "<td class='fail center'><i class='fa fa-arrow-circle-down'>&nbsp;</i></td>\n";
				// otherwise, thumbs up!
				} else {
					$output .= "<td class='pass center'><i class='fa fa-arrow-circle-up'>&nbsp;</i></td>\n";
				}
			}
			// query the API for uptime stats for the number of days specified
			$url = "https://api.nodeping.com/api/1/results/uptime/$id?token=$api_token&interval=days&start=$start_date";
			$result = wp_remote_get( $url );
			if ( ! is_wp_error( $result ) ) {
				$uptime = json_decode( $result['body'], TRUE );
				// even though the query may have retrieved more results than we need, we limit the number of columns to what the user asked for
				while ( $date_offset < $atts['days'] ) {
					// we start with today, and work our way back in time
					$current_date = date( "Y-m-d", time() - 86400 * $date_offset );
					$date_offset++;
					// make sure there is actually some data to display
					if ( empty( $uptime[$current_date] ) ) {
						$output .= "<td>--</td>\n";
					} else {
						// 100 = green, 99+ is orange, below 99 is red
						if ( $uptime[$current_date]['uptime'] == 100 ) {
							$uptime_class = 'pass';
						} elseif ( $uptime[$current_date]['uptime'] >= 99 ) {
							$uptime_class = 'disrupt';
						} else {
							$uptime_class = 'fail';
						}
						$output .= "<td class='$uptime_class center'>" . $uptime[$current_date]['uptime'] . "%</td>\n";
					}
				}
				// again, make sure we have something to output, just in case the query failed
				if ( empty( $uptime['total']['uptime'] ) ) {
					$output .= "<td class='month'>--</td>\n";
				// output the total uptime for the 'total' days specified by the user
				} else {
					if ( $uptime['total']['uptime'] == 100 ) {
						$uptime_class = 'pass';
					} elseif ( $uptime['total']['uptime'] >= 99 ) {
						$uptime_class = 'disrupt';
					} else {
						$uptime_class = 'fail';
					}
					$output .= "<td class='month $uptime_class center'>" . $uptime['total']['uptime'] . "%</td>\n";
				}
			}
			$alternate = !$alternate;
			$output .= "</tr>\n";
		}
		$output .= "</tbody>\n</table>\n";
		// set the timezone back according to the blog settings
		$site_timezone = get_option( 'timezone_string' );
		if ( ! empty( $site_timezone ) ) {
			date_default_timezone_set( $site_timezone );
		}
		// and send back our nice and pretty table
		return $output;
	}
}
add_shortcode( 'nodeping_status', 'nodeping_status_shortcode' );

//TODO: we probably don't need these, since we don't want people setting a token network-wide
// retrieve an option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting
/*function nodeping_status_get_option ($option_name) {
	if (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network(plugin_basename(NODEPING_STATUS_PLUGIN_FILE))) {
		$option_value = get_site_option($option_name);
	} else {
		$option_value = get_option($option_name);
	}
	return $option_value;
}

// set an option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting
function nodeping_status_set_option ($option_name, $option_value) {
	if (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network(plugin_basename(NODEPING_STATUS_PLUGIN_FILE))) {
		$success = update_site_option($option_name, $option_value);
	} else {
		$success = update_option($option_name, $option_value);
	}
	return $success;
}*/

// displays the EWWW IO options and provides one-click install for the optimizer utilities
function nodeping_status_options () {
?>
	<div class="wrap">
		<h2><?php _e('NodePing Status Settings', NODEPING_STATUS_DOMAIN); ?></h2>
		<p><a href="http://wordpress.org/extend/plugins/nodeping-status/"><?php _e('Plugin Home Page', NODEPING_STATUS_DOMAIN); ?></a> |
		<a href="http://wordpress.org/support/plugin/nodeping-status"><?php _e('Plugin Support', NODEPING_STATUS_DOMAIN); ?></a></p>
		<p><?php _e('The NodePing status page can be embedded with this shortcode:', NODEPING_STATUS_DOMAIN); ?><br />
			<pre>[nodeping_status days="7" total="30"]</pre>
		<?php _e('The attributes are optional, days is how many days of uptime stats you want to include, and total is how many days will be used to calculate the total uptime.', NODEPING_STATUS_DOMAIN); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields('nodeping_status_options');  ?>
			<table class="form-table">
				<tr><th><label for="nodeping_status_api_token"><?php _e('NodePing API Token', NODEPING_STATUS_DOMAIN); ?></label></th><td><input type="text" id="nodeping_status_api_token" name="nodeping_status_api_token" value="<?php echo get_option('nodeping_status_api_token'); ?>" size="40" /> <?php if (get_option('nodeping_status_api_token')) { echo "<i class='fa fa-check-circle' style='color: #366836'>&nbsp;</i>"; } ?></td></tr>
			</table>
			<p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes', NODEPING_STATUS_DOMAIN); ?>" /></p>
		</form>
	</div>
	<?php
}
?>
