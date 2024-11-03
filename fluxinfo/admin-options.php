<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// create custom plugin settings menu
add_action('admin_menu', 'fluxinfo_create_menu');
function fluxinfo_create_menu() {

	//create new top-level menu
	add_submenu_page( 'options-general.php', 'App Info', 'App Info', 'manage_options', 'fluxinfo/admin-options.php', 'fluxinfo_settings_page' );

	//call register settings function
	add_action( 'admin_init', 'register_fluxinfo_settings' );
}

// Register Settings
function register_fluxinfo_settings() {
	register_setting( 'fluxinfo-settings-group', 'fluxinfo_name' );
	register_setting( 'fluxinfo-settings-group', 'fluxinfo_expire_block' );
	register_setting( 'fluxinfo-settings-group', 'fluxinfo_operator_port' );
	register_setting( 'fluxinfo-settings-group', 'fluxinfo_theme' );
	register_setting( 'fluxinfo-settings-group', 'fluxinfo_renew_reminder' );
	register_setting( 'fluxinfo-settings-group', 'fluxinfo_renew_reminder_days' );
}

// Keys Updated
//add_action('update_option_fluxinfo_key', 'fluxinfo_keys_updated', 10);
//add_action('update_option_fluxinfo_secret', 'fluxinfo_keys_updated', 10);
//function fluxinfo_keys_updated() {
//	update_option('fluxinfo_tested', 'no');
//}

// Show Settings Page
function fluxinfo_settings_page() {
?>
<div class="wrap">

<h1><?php echo esc_html(__( 'App Info for Flux', 'info-for-flux' )); ?></h1>

<p><?php echo esc_html(__( 'This plugin will monitor and display Flux Network (RunOnFlux.com) information such as app Expiration abd more.', 'info-for-flux' )); ?></p>

<?php fluxinfo_get_app_specs(); ?>

<form method="post" action="options.php">

    <?php settings_fields( 'fluxinfo-settings-group' ); ?>
    <?php do_settings_sections( 'fluxinfo-settings-group' ); ?>

    <table class="form-table">

    <tr valign="top">
    	<th scope="row" style="padding-bottom: 0;">
    	<p style="font-size: 19px; margin-top: 0;"><?php echo esc_html(__( 'Flux Information:', 'info-for-flux' )); ?></p>
    	<p style="font-size: 19px; margin-bottom: 2px;"><?php echo esc_html(__( 'App name: ', 'info-for-flux' )) . esc_attr( get_option('fluxinfo_name') ) . esc_html(__(' and expires in ', 'info-for-flux' )) . esc_html(fluxinfo_app_days_remaining()) . esc_html(__(' days', 'info-for-flux' )); ?></p>
    	</th>
    </tr>
	<tr>
		<td scope="row" style="padding-bottom: 0;"><p>
	<table><tr>
		<th style="background-color: #ccc; border: 1px solid #ddd;">Node</th>
		<th style="background-color: #ccc; border: 1px solid #ddd;">Uptime</th>
		<th style="background-color: #ccc; border: 1px solid #ddd;">Operator</th>
		<th style="background-color: #ccc; border: 1px solid #ddd;">db Sync</th>
		<th style="background-color: #ccc; border: 1px solid #ddd;">Master IP</th></tr>
	<?php
		$nodes = fluxinfo_get_all_instances();
		foreach ($nodes as $node) {
			$nodeapi = explode(':', $node->ip);
			$nodeip = $nodeapi[0];
			if (count($nodeapi) == 1) $nodeweb = $nodeip .':16126';
			else $nodeweb = $nodeip . ':' . substr($nodeapi[1], 0, -1). '6';
			$start_time = strtotime($node->runningSince);
			$up_time = (int)((time() - $start_time)/(60*60)); // Uptime in hours
			if ($up_time < 48) $up = $up_time ." hours";
			else {
				$up_time = (int)($up_time/24);
				$up = $up_time . " days";
			}
			$data = fluxinfo_get_operator_status($nodeip);
			echo '<tr><td style="border: 1px solid #ddd;"><a href="http://' . esc_html($nodeweb) . '" target="_blank">'. esc_html($nodeip) .'</a></td>';
			echo '<td style="border: 1px solid #ddd;">'. esc_html($up) .'</td>';
			echo '<td style="border: 1px solid #ddd;">'. esc_html($data->status) .'</td>';
			echo '<td style="border: 1px solid #ddd;">'. esc_html($data->sequenceNumber) .'</td>';
			echo '<td style="border: 1px solid #ddd;">'. esc_html($data->masterIP) ."</td><tr>\n";
		}
?></table></p></td>
	</tr>

    </table>

	<input type="hidden" name="fluxinfo_name" value="<?php echo esc_attr( get_option('fluxinfo_name')); ?>">

    <table class="form-table">
		<tr valign="top">
			<th scope="row">
			<?php echo esc_html(__( 'Remind about App Renewal', 'info-for-flux' )); ?>
			</th>
			<td><input type="checkbox" name="fluxinfo_renew_reminder" <?php if(get_option('fluxinfo_renew_reminder')) { ?>checked<?php } ?>></td>
		</tr>
        <tr valign="top">
	        <th scope="row"><?php echo esc_html(__( 'Daily Advance Notice (Days)', 'info-for-flux' )); ?></th>
    	    <td><input type="number" name="fluxinfo_renew_reminder_days" value="<?php echo esc_attr( get_option('fluxinfo_renew_reminder_days') ); ?>" /></td>
        </tr>
    </table>

    <?php submit_button(); ?>

	<br/>

    <div class="rfw-admin-promo">

		<p style="font-size: 15px; font-weight: bold;"><?php echo esc_html(__( '100% free plugin developed by', 'info-for-flux' )); ?> Tom Moulton, <a href="https://www.RunOnFlux.com" target="_blank">InFlux Inc</a></p>

		<p style="font-size: 15px;">- <?php echo esc_html(__( 'Find this plugin useful?', 'info-for-flux' )); ?> <a href="https://wordpress.org/support/plugin/info-for-flux/reviews/#new-post" target="_blank"><?php echo esc_html(__( 'Please submit a review', 'info-for-flux' )); ?></a> <a href="https://wordpress.org/support/plugin/info-for-flux/reviews/#new-post" target="_blank" style="text-decoration: none;">⭐️⭐️⭐️⭐️⭐️</a></p>

		<p style="font-size: 15px;">- <?php echo esc_html(__( 'Need help? Have a suggestion?', 'info-for-flux' )); ?> <a href="https://wordpress.org/support/plugin/info-for-flux" target="_blank"><?php echo esc_html(__( 'Create a support topic', 'info-for-flux' )); ?><span class="dashicons dashicons-external" style="font-size: 15px; margin-top: 5px; text-decoration: none;"></span></a></p>

		<br/>

		<p style="font-size: 12px;">
			
			<a href="https://github.com/RunOnFlux/FluxInfoWP" target="_blank"><?php echo esc_html(__( 'View on GitHub', 'info-for-flux' )); ?><span class="dashicons dashicons-external" style="font-size: 15px; margin-top: 2px; text-decoration: none;"></span></a>
		
		</p>

    </div>

	<br/>

	<br/><br/>

</form>
</div>

<?php } ?>