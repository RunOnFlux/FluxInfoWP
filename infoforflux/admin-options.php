<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// create custom plugin settings menu
add_action('admin_menu', 'infoforflux_create_menu');
function infoforflux_create_menu() {

	//create new top-level menu
	add_submenu_page( 'tools.php', 'App Info for Flux', 'App Info for Flux', 'manage_options', 'infoforflux/admin-options.php', 'infoforflux_settings_page' );

	//call register settings function
	add_action( 'admin_init', 'infoforflux_register_settings' );
}

// Register Settings
function infoforflux_register_settings() {
	$santize = array('type' => 'string','sanitize_callback' => 'sanitize_text_field');
	register_setting( 'infoforflux-settings-group', 'infoforflux_name', $santize );
	register_setting( 'infoforflux-settings-group', 'infoforflux_expire_block', $santize );
	register_setting( 'infoforflux-settings-group', 'infoforflux_operator_port', $santize );
	register_setting( 'infoforflux-settings-group', 'infoforflux_theme', $santize );
	register_setting( 'infoforflux-settings-group', 'infoforflux_renew_reminder', $santize );
	register_setting( 'infoforflux-settings-group', 'infoforflux_renew_reminder_days', $santize );
	register_setting( 'infoforflux-settings-group', 'infoforflux_wp_repo', $santize );
	register_setting( 'infoforflux-settings-group', 'infoforflux_mysql_repo', $santize );
	register_setting( 'infoforflux-settings-group', 'infoforflux_operator_repo', $santize );
}

// Keys Updated
//add_action('update_option_infoforflux_key', 'infoforflux_keys_updated', 10);
//add_action('update_option_infoforflux_secret', 'infoforflux_keys_updated', 10);
//function infoforflux_keys_updated() {
//	update_option('infoforflux_tested', 'no');
//}

function infoforflux_repo_check($name, $varname, $reponame, $tag) {
	$repotag = get_option($varname);
	$repo = explode(':', $repotag);
	$notice = false;
	if ($repotag !== $reponame .':'. $tag) { // Not expected repotag
		if ($repo[0] === $reponame) { // Expected repo, different tag
			$notice = $name .": " . $repotag ." - Standard Wordpress with exprimental tag: " . $repo[1];
		} else {
			$notice = $name .": Non-Standard repo: " . $repotag;
		}
	}
	return $notice;
}

// Show Settings Page
function infoforflux_settings_page() {
?>
<div class="wrap">

<h1><?php echo esc_html(__( 'App Info for Flux', 'infoforflux' )); ?></h1>

<p><?php echo esc_html(__( 'This plugin will monitor and display Flux Network (RunOnFlux.io) information such as app Expiration and more.', 'infoforflux' )); ?></p>

<?php infoforflux_get_app_specs(); ?>

<form method="post" action="options.php">

    <?php settings_fields( 'infoforflux-settings-group' ); ?>
    <?php do_settings_sections( 'infoforflux-settings-group' ); ?>

    <table class="form-table">

    <tr valign="top">
    	<th scope="row" style="padding-bottom: 0;">
    	<p style="font-size: 19px; margin-top: 0;"><?php echo esc_html(__( 'Flux Information:', 'infoforflux' )); ?></p>
    	<p style="font-size: 19px; margin-bottom: 2px;"><?php echo esc_html(__( 'App name: ', 'infoforflux' )) . esc_attr( get_option('infoforflux_name') ) . esc_html(__(' expires in ', 'infoforflux' )) . esc_html(infoforflux_app_days_remaining()) . esc_html(__(' days', 'infoforflux' )); ?></p>
<?php
	$notice = infoforflux_repo_check('WordPress', 'infoforflux_wp_repo', 'runonflux/wp-nginx', 'latest');
	if ($notice) {
		echo '<p style="font-size: 19px; margin-bottom: 2px;">' . esc_html($notice) . "</p>";
	}
	$notice = infoforflux_repo_check('Mysql', 'infoforflux_mysql_repo', 'mysql', '8.3.0');
	if ($notice) {
		echo '<p style="font-size: 19px; margin-bottom: 2px;">' . esc_html($notice) . "</p>";
	}
	$notice = infoforflux_repo_check('Operator', 'infoforflux_operator_repo', 'runonflux/shared-db', 'latest');
	if ($notice) {
		echo '<p style="font-size: 19px; margin-bottom: 2px;">' . esc_html($notice) . "</p>";
	}
?>
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
		$nodes = infoforflux_get_all_instances();
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
			$data = infoforflux_get_operator_status($nodeip);
			echo '<tr><td style="border: 1px solid #ddd;"><a href="http://' . esc_html($nodeweb) . '" target="_blank">'. esc_html($nodeip) .'</a></td>';
			echo '<td style="border: 1px solid #ddd;">'. esc_html($up) .'</td>';
			echo '<td style="border: 1px solid #ddd;">'. esc_html($data->status) .'</td>';
			echo '<td style="border: 1px solid #ddd;">'. esc_html($data->sequenceNumber) .'</td>';
			echo '<td style="border: 1px solid #ddd;">'. esc_html($data->masterIP) ."</td><tr>\n";
		}
?></table></p></td>
	</tr>

    </table>

	<input type="hidden" name="infoforflux_name" value="<?php echo esc_attr( get_option('infoforflux_name')); ?>">

    <table class="form-table">
		<tr valign="top">
			<th scope="row">
			<?php echo esc_html(__( 'Remind about App Renewal', 'infoforflux' )); ?>
			</th>
			<td><input type="checkbox" name="infoforflux_renew_reminder" <?php if(get_option('infoforflux_renew_reminder')) { ?>checked<?php } ?>></td>
		</tr>
        <tr valign="top">
	        <th scope="row"><?php echo esc_html(__( 'Daily Advance Notice (Days)', 'infoforflux' )); ?></th>
    	    <td><input type="number" name="infoforflux_renew_reminder_days" value="<?php echo esc_attr( get_option('infoforflux_renew_reminder_days') ); ?>" /></td>
        </tr>
    </table>

    <?php submit_button(); ?>

	<br/>

    <div class="rfw-admin-promo">

		<p style="font-size: 15px; font-weight: bold;"><?php echo esc_html(__( '100% free plugin developed by', 'infoforflux' )); ?> Tom Moulton, <a href="https://www.RunOnFlux.com" target="_blank">InFlux Inc</a></p>

		<p style="font-size: 15px;">- <?php echo esc_html(__( 'Find this plugin useful?', 'infoforflux' )); ?> <a href="https://wordpress.org/support/plugin/info-for-flux/reviews/#new-post" target="_blank"><?php echo esc_html(__( 'Please submit a review', 'infoforflux' )); ?></a> <a href="https://wordpress.org/support/plugin/info-for-flux/reviews/#new-post" target="_blank" style="text-decoration: none;">⭐️⭐️⭐️⭐️⭐️</a></p>

		<p style="font-size: 15px;">- <?php echo esc_html(__( 'Need help? Have a suggestion?', 'infoforflux' )); ?> <a href="https://wordpress.org/support/plugin/info-for-flux" target="_blank"><?php echo esc_html(__( 'Create a support topic', 'infoforflux' )); ?><span class="dashicons dashicons-external" style="font-size: 15px; margin-top: 5px; text-decoration: none;"></span></a></p>

		<br/>

		<p style="font-size: 12px;">
			
			<a href="https://github.com/RunOnFlux/infoforfluxWP" target="_blank"><?php echo esc_html(__( 'View on GitHub', 'infoforflux' )); ?><span class="dashicons dashicons-external" style="font-size: 15px; margin-top: 2px; text-decoration: none;"></span></a>
		
		</p>

    </div>

	<br/>

	<br/><br/>

</form>
</div>

<?php } ?>