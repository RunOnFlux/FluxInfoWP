<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// create custom plugin settings menu
add_action('admin_menu', 'fluxinfo_create_menu');
function fluxinfo_create_menu() {

	//create new top-level menu
	add_submenu_page( 'options-general.php', 'Flux Info', 'Flux Info', 'manage_options', 'admin-options.php', 'fluxinfo_settings_page' );

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

<h1><?php echo esc_html(__( 'Flux Info', 'fluxinfo' )); ?></h1>

<p><?php echo esc_html(__( 'This plugin will monitor and display Flux Network (RunOnFlux.com) information such as app Expiration abd more.', 'fluxinfo' )); ?></p>

<?php fluxinfo_get_app_specs(); ?>

<form method="post" action="options.php">

    <?php settings_fields( 'fluxinfo-settings-group' ); ?>
    <?php do_settings_sections( 'fluxinfo-settings-group' ); ?>

    <table class="form-table">

    <tr valign="top">
    	<th scope="row" style="padding-bottom: 0;">
    	<p style="font-size: 19px; margin-top: 0;"><?php echo esc_html(__( 'Flux Information:', 'fluxinfo' )); ?></p>
    	<p style="font-size: 19px; margin-bottom: 2px;"><?php echo esc_html(__( 'App name: ', 'fluxinfo' )) . esc_attr( get_option('fluxinfo_name') ) . esc_html(__(' and expires in ', 'fluxinfo' )) . esc_html(fluxinfo_app_days_remaining()) . esc_html(__(' days', 'fluxinfo' )); ?></p>
    	</th>
    </tr>
	<tr>
		<td scope="row" style="padding-bottom: 0;"><p><?php echo fluxinfo_get_all_instances(); ?></p></td>
	</tr>

    </table>

	<input type="hidden" name="fluxinfo_name" value="<?php echo esc_attr( get_option('fluxinfo_name')); ?>">

    <table class="form-table">
		<tr valign="top">
			<th scope="row">
			<?php echo esc_html(__( 'Remind about App Renewal', 'fluxinfo' )); ?>
			</th>
			<td><input type="checkbox" name="fluxinfo_renew_reminder" <?php if(get_option('fluxinfo_renew_reminder')) { ?>checked<?php } ?>></td>
		</tr>
        <tr valign="top">
	        <th scope="row"><?php echo esc_html(__( 'Daily Advance Notice (Days)', 'fluxinfo' )); ?></th>
    	    <td><input type="number" name="fluxinfo_renew_reminder_days" value="<?php echo esc_attr( get_option('fluxinfo_renew_reminder_days') ); ?>" /></td>
        </tr>
    </table>

    <?php submit_button(); ?>

	<br/>

    <div class="rfw-admin-promo">

		<p style="font-size: 15px; font-weight: bold;"><?php echo esc_html(__( '100% free plugin developed by', 'fluxinfo' )); ?> Tom Moulton, <a href="https://www.RunOnFlux.com" target="_blank">InFlux Inc</a></p>

		<p style="font-size: 15px;">- <?php echo esc_html(__( 'Find this plugin useful?', 'fluxinfo' )); ?> <a href="https://wordpress.org/support/plugin/flux-info/reviews/#new-post" target="_blank"><?php echo esc_html(__( 'Please submit a review', 'fluxinfo' )); ?></a> <a href="https://wordpress.org/support/plugin/fluxinfo/reviews/#new-post" target="_blank" style="text-decoration: none;">⭐️⭐️⭐️⭐️⭐️</a></p>

		<p style="font-size: 15px;">- <?php echo esc_html(__( 'Need help? Have a suggestion?', 'fluxinfo' )); ?> <a href="https://wordpress.org/support/plugin/flux-info" target="_blank"><?php echo esc_html(__( 'Create a support topic', 'fluxinfo' )); ?><span class="dashicons dashicons-external" style="font-size: 15px; margin-top: 5px; text-decoration: none;"></span></a></p>

		<br/>

		<p style="font-size: 12px;">
			
			<a href="https://github.com/RunOnFlux/FluxInfoWP" target="_blank"><?php echo esc_html(__( 'View on GitHub', 'fluxinfo' )); ?><span class="dashicons dashicons-external" style="font-size: 15px; margin-top: 2px; text-decoration: none;"></span></a>
		
		</p>

    </div>

	<br/>

	<br/><br/>

</form>
</div>

<?php } ?>