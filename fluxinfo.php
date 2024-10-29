<?php
/**
* Plugin Name: Flux Info
* Description: Display and Monitor Flux Network (runonflux.com) 
* Version: 1.0.0
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
	$lines = "<table><tr><th>Node</th><th>Uptime</th><th>Operator</th><th>db Sync</th><th>Master IP</th></tr>\n";
	foreach ($body->data as $node) {
		$nodeip = explode(':', $node->ip)[0];
		$start_time = strtotime($node->runningSince);
		$up_time = (int)((time() - $start_time)/(60*60)); // Uptime in hours
		if ($up_time < 48) $up = $up_time ." hours";
		else {
			$up_time = (int)($up_time/24);
			$up = $up_time . " days";
		}
		$data = fluxinfo_get_operator_status($nodeip);
		$lines .= "<tr><td>". $nodeip ."</td><td>". $up ."</td><td>". $data->status ."</td><td>". $data->sequenceNumber ."</td><td>". $data->masterIP ."</td><tr>\n";
	}
	$lines .= "</table>\n";
	return $lines;
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
