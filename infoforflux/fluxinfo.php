<?php
/**
* Plugin Name: App Info for Flux
* Description: Display and Monitor Flux Network (runonflux.com) 
* Version: 1.0.3
* Author: Tom Moulton tom@runonflux.com
* Author URI: https://runonflux.com
* License: GPLv3 or later
* Text Domain: fluxinfo
*
**/

include( plugin_dir_path( __FILE__ ) . 'admin-options.php');

/**
 * On activate redirect to settings page
 **/
register_activation_hook(__FILE__, function () {
  $name = substr(DB_HOST, strpos(DB_HOST, '_') + 1, strpos(DB_HOST, ':') - strpos(DB_HOST, '_') - 1);
  delete_option('fluxinfo_name');
  delete_option('fluxinfo_expire_block');
  delete_option('fluxinfo_operator_port');
  delete_option('fluxinfo_renew_reminder_days');
  if (!get_option('fluxinfo_name')) {
    add_option('fluxinfo_name', $name);
    add_option('fluxinfo_expire_block', 0);
    add_option('fluxinfo_operator_port', 0);
    add_option('fluxinfo_renew_reminder_days', 30);
  } else {
	update_option('fluxinfo_name', $name);
  }
});
/**
register_activation_hook(__FILE__, function () {
  add_option('fluxinfo_do_activation_redirect', true);
	add_option('fluxinfo_tested', 'no');
});
add_action('admin_init', function () {
  if (get_option('fluxinfo_do_activation_redirect', false)) {
    delete_option('fluxinfo_do_activation_redirect');
    exit( wp_redirect("options-general.php?page=fluxinfo%2Fadmin-options.php") );
  }
});
 **/

 add_action( 'plugins_loaded', 'fluxinfo_display_notifications' );

// Plugin List - Settings Link
add_filter( 'plugin_action_links', 'fluxinfo_settings_link_plugin', 10, 5 );
function fluxinfo_settings_link_plugin( $actions, $plugin_file )
{
	static $plugin;

	if (!isset($plugin))
		$plugin = plugin_basename(__FILE__);
	if ($plugin == $plugin_file) {
		$settings = array('settings' => '<a href="options-general.php?page=fluxinfo%2Fadmin-options.php">' . __('Settings', 'fluxinfo') . '</a>');
    	$actions = array_merge($settings, $actions);
	}

	return $actions;
}

// Field
function fluxinfo_field() {
	$name = esc_attr( get_option('fluxinfo_name') );
	$theme = esc_attr( get_option('fluxinfo_theme') );
	if($name) {
		?>
		<div class="g-recaptcha" <?php if($theme == "dark") { ?>data-theme="dark" <?php } ?>data-sitekey="<?php echo esc_attr($key); ?>"></div>
		<br/>
		<?php
	}
}

// Field WP Admin
function fluxinfo_field_admin() {
	$name = esc_attr( get_option('fluxinfo_name') );
	$theme = esc_attr( get_option('fluxinfo_theme') );
	if($name) {
		?>
		<div style="margin-left: -15px;" class="g-recaptcha" <?php if($theme == "dark") { ?>data-theme="dark" <?php } ?>data-sitekey="<?php echo esc_attr($key); ?>"></div>
		<br/>
		<?php
	}
}

function fluxinfo_get_current_block() {
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

function fluxinfo_get_app_specs() {
	$name = esc_attr( get_option('fluxinfo_name') );
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
	update_option('fluxinfo_expire_block', $endblock);
	$port = 0;
	foreach ($body->data->compose as $spec) {
		if ($spec->name === 'operator') {
			$port = $spec->ports[2];
			break;
		}
	}
	update_option('fluxinfo_operator_port', $port);
	return $true;
}

function fluxinfo_app_days_remaining() {
	$ret = '(unknown)';
	$name = esc_attr( get_option('fluxinfo_name') );
	$endblock = esc_attr( get_option('fluxinfo_expire_block') );
	$current = fluxinfo_get_current_block();
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

function fluxinfo_get_all_instances() {
	$name = esc_attr( get_option('fluxinfo_name') );
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

function fluxinfo_get_operator_status($ip) {
	$port = esc_attr( get_option('fluxinfo_operator_port') );
	$response = wp_remote_get('http://'. $ip .':'. $port .'/status');
	$status = wp_remote_retrieve_response_code( $response );
	if ($status != 200) {
		return array("status" => "err ". $status, "sequenceNumber" => "", "masterIP" => "");
	}
	$body = json_decode(wp_remote_retrieve_body( $response ));
	if (isset($body->status)) return $body;
	return array("status" => "unknown", "sequenceNumber" => "", "masterIP"=> "");
}

function fluxinfo_display_notifications() {
	$is_admin = current_user_can('administrator');
	if ($is_admin) {
		fluxinfo_check_notifications();
	}
}

function fluxinfo_check_notifications() {
	// App Renewal Notification
	if(get_option('fluxinfo_renew_reminder')) { // Box is checked
		$max_days = get_option('fluxinfo_renew_reminder_days');
		$exp_days = fluxinfo_app_days_remaining();
		if ($exp_days < $max_days) add_action( 'admin_notices', 'fluxinfo_expiration_notice' );
	}
}

function fluxinfo_expiration_notice() {
    // Check if the notice has been dismissed (within the last 24 hours)
    $dismissed = get_transient('fluxinfo_expiration_notice_dismissed');

    // Only show the notice if it's not dismissed
    if ($dismissed === false) {
        ?>
        <div class="notice notice-info is-dismissible fluxinfo-expiration-notice">
            <p><?php echo esc_attr( get_option('fluxinfo_name') ) . esc_html(__(' and expires in ', 'fluxinfo' )) . esc_html(fluxinfo_app_days_remaining()) . esc_html(__(' days', 'fluxinfo' )); ?></p>
        </div>
        <script type="text/javascript">
            // Use jQuery to handle the dismiss button click
            jQuery(document).on('click', '.fluxinfo-expiration-notice .notice-dismiss', function() {
                // Send AJAX request to mark the notice as dismissed
                jQuery.post(ajaxurl, {
                    action: 'fluxinfo_expiration_dismiss_notice'
                });
            });
        </script>
        <?php
    }
}

// AJAX action to mark the notice as dismissed
add_action('wp_ajax_fluxinfo_expiration_dismiss_notice', 'fluxinfo_expiration_dismiss_notice');

function fluxinfo_expiration_dismiss_notice() {
    // Set a transient to remember the dismissal for 24 hours
    set_transient('fluxinfo_expiration_notice_dismissed', true, 24 * HOUR_IN_SECONDS);
    wp_die(); // this is required to terminate immediately and return a response
}
