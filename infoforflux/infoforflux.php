<?php
/**
* Plugin Name: App Info for Flux
* Description: Display and Monitor Flux Network (runonflux.com) 
* Version: 1.0.6
* Author: Tom Moulton tom@runonflux.com
* Author URI: https://runonflux.com
* License: GPLv3 or later
* Text Domain: infoforflux
*
**/
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

include( 'admin-options.php');

/**
 * On activate redirect to settings page
 **/
register_activation_hook(__FILE__, function () {
  $name = substr(DB_HOST, strpos(DB_HOST, '_') + 1, strpos(DB_HOST, ':') - strpos(DB_HOST, '_') - 1);
  delete_option('infoforflux_name');
  delete_option('infoforflux_expire_block');
  delete_option('infoforflux_operator_port');
  delete_option('infoforflux_renew_reminder_days');
  delete_transient('infoforflux_expiration_notice_dismissed');
  if (!get_option('infoforflux_name')) {
    add_option('infoforflux_name', $name);
    add_option('infoforflux_expire_block', 0);
    add_option('infoforflux_operator_port', 0);
    add_option('infoforflux_renew_reminder_days', 30);
  } else {
	update_option('infoforflux_name', $name);
  }
  infoforflux_get_app_specs();
});
/**
register_activation_hook(__FILE__, function () {
  add_option('infoforflux_do_activation_redirect', true);
	add_option('infoforflux_tested', 'no');
});
add_action('admin_init', function () {
  if (get_option('infoforflux_do_activation_redirect', false)) {
    delete_option('infoforflux_do_activation_redirect');
    exit( wp_redirect("options-general.php?page=infoforflux%2Fadmin-options.php") );
  }
});
 **/

// add_action( 'plugins_loaded', 'infoforflux_display_notifications' );
 add_action( 'plugins_loaded', 'infoforflux_display_notifications' );

// Plugin List - Settings Link
add_filter( 'plugin_action_links', 'infoforflux_settings_link_plugin', 10, 5 );
function infoforflux_settings_link_plugin( $actions, $plugin_file )
{
	static $plugin;

	if (!isset($plugin))
		$plugin = plugin_basename(__FILE__);
	if ($plugin == $plugin_file) {
		$settings = array('settings' => '<a href="tools.php?page=infoforflux/admin-options.php">' . __('Settings', 'infoforflux') . '</a>');
    	$actions = array_merge($settings, $actions);
	}

	return $actions;
}

// Field
function infoforflux_field() {
	$name = esc_attr( get_option('infoforflux_name') );
	$theme = esc_attr( get_option('infoforflux_theme') );
	if($name) {
		?>
		<div class="g-recaptcha" <?php if($theme == "dark") { ?>data-theme="dark" <?php } ?>data-sitekey="<?php echo esc_attr($key); ?>"></div>
		<br/>
		<?php
	}
}

// Field WP Admin
function infoforflux_field_admin() {
	$name = esc_attr( get_option('infoforflux_name') );
	$theme = esc_attr( get_option('infoforflux_theme') );
	if($name) {
		?>
		<div style="margin-left: -15px;" class="g-recaptcha" <?php if($theme == "dark") { ?>data-theme="dark" <?php } ?>data-sitekey="<?php echo esc_attr($key); ?>"></div>
		<br/>
		<?php
	}
}

function infoforflux_get_current_block() {
	$response = wp_remote_get( 'https://api.runonflux.io/daemon/getblockcount');
	if (wp_remote_retrieve_response_code( $response ) != 200) {
		return false;
	}
	$body = json_decode(wp_remote_retrieve_body( $response ));
	if ($body->status != 'success') {
		return false;
	}
	return $body->data;
}

function infoforflux_get_app_specs() {
	$name = esc_attr( get_option('infoforflux_name') );
	$response = wp_remote_get( 'https://api.runonflux.io/apps/appspecifications/'. $name );
	if (wp_remote_retrieve_response_code( $response ) != 200) {
		return false;
	}
	$body = json_decode(wp_remote_retrieve_body( $response ));
	if ($body->status != 'success') {
		return false;
	}
	if ($body->data->name != $name) {
		print("Name ". esc_attr($body->data->name) . ' <> '. esc_attr($name) .'<br>');
	}
	$height = $body->data->height;
	$expire = $body->data->expire;
	$endblock = $height + $expire;
	update_option('infoforflux_expire_block', $endblock);
	$port = 0;
	foreach ($body->data->compose as $spec) {
		if ($spec->name === 'operator') {
			$port = $spec->ports[2];
			break;
		}
	}
	update_option('infoforflux_operator_port', $port);
	return $true;
}

function infoforflux_app_days_remaining() {
	$ret = '(unknown)';
	$name = esc_attr( get_option('infoforflux_name') );
	$endblock = esc_attr( get_option('infoforflux_expire_block') );
	$current = infoforflux_get_current_block();
	if ($current) {
		if ($current > $endblock) $ret = 'EXPIRED';
		else {
			$blocks = $endblock - $current;
			$minutes = $blocks * 2; // Flux Generates a block every 120 seconds +/-
			$days = (int)($minutes/(60*24));
			$ret = $days;
		}
	}
	return $ret;
}

function infoforflux_get_all_instances() {
	$name = esc_attr( get_option('infoforflux_name') );
	$response = wp_remote_get( 'https://api.runonflux.io/apps/location/'. $name );
	$status = wp_remote_retrieve_response_code( $response );
	if ($status != 200) {
		return false;
	}
	$body = json_decode(wp_remote_retrieve_body( $response ));
	if ($body->status != 'success') {
		return false;
	}
	return $body->data;
}

function infoforflux_get_operator_status($ip) {
	$port = esc_attr( get_option('infoforflux_operator_port') );
	$url = 'http://'. $ip .':'. $port .'/status';
	$response = wp_remote_get($url);
	$status = wp_remote_retrieve_response_code( $response );
	if ($status != 200) {
		return array("status" => "err ". $status, "sequenceNumber" => "", "masterIP" => "");
	}
	$body = json_decode(wp_remote_retrieve_body( $response ));
	if (isset($body->status)) return $body;
	return array("status" => "unknown", "sequenceNumber" => "", "masterIP"=> "");
}

function infoforflux_display_notifications() {
	$is_admin = current_user_can('administrator');
	if ($is_admin) {
		infoforflux_check_notifications();
	}
}

function infoforflux_check_notifications() {
	// App Renewal Notification
	if(get_option('infoforflux_renew_reminder')) { // Box is checked
		$max_days = get_option('infoforflux_renew_reminder_days');
		$exp_days = infoforflux_app_days_remaining();
		if ($exp_days < $max_days) add_action( 'admin_notices', 'infoforflux_expiration_notice' );
	}
}

function infoforflux_enqueue_admin_scripts($hook) {
    // Enqueue JavaScript only on the admin dashboard
    if ('infoforflux.php' === $hook) {
        wp_enqueue_script(
            'infoforflux-expiration-notice-js',
            plugin_dir_url(__FILE__) . 'infoforflux-expiration-notice.js',
            array('jquery'), // jQuery as a dependency
            '1.0',
            true
        );

        // Pass ajaxurl to the script
        wp_localize_script('infoforflux-expiration-notice-js', 'infoforflux_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'action'   => 'infoforflux_expiration_dismiss_notice',
        ));
    }
}
add_action('admin_enqueue_scripts', 'infoforflux_enqueue_admin_scripts');

function infoforflux_expiration_notice() {
    // Check if the notice has been dismissed (within the last 24 hours)
    $dismissed = get_transient('infoforflux_expiration_notice_dismissed');

    // Only show the notice if it's not dismissed
    if ($dismissed === false) {
        ?>
        <div class="notice notice-info is-dismissible infoforflux-expiration-notice" style="background-color: LightPink;">
            <p><?php echo esc_attr( get_option('infoforflux_name') ) . esc_html(__(' expires in ', 'infoforflux' )) . esc_html(infoforflux_app_days_remaining()) . esc_html(__(' days', 'infoforflux' )); ?></p>
        </div>
        <?php
    }
}

// AJAX action to mark the notice as dismissed
add_action('wp_ajax_infoforflux_expiration_dismiss_notice', 'infoforflux_expiration_dismiss_notice');

function infoforflux_expiration_dismiss_notice() {
    // Set a transient to remember the dismissal for 24 hours
    set_transient('infoforflux_expiration_notice_dismissed', true, 24 * HOUR_IN_SECONDS);
    wp_die(); // this is required to terminate immediately and return a response
}
