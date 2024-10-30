<?php
/*
Plugin Name: Bluetrait Connector
Plugin URI: http://wordpress.org/extend/plugins/bluetrait-connector/
Description: Connects a WordPress install to a Bluetrait install via SOAP
Version: 0.3
Author: Michael Dale
Author URI: http://www.bluetrait.com/
*/

/*
Change this value to TRUE to stop admins from disabling/uninstalling/changing the BTC plugin. 
See FAQ for more info http://wordpress.org/extend/plugins/bluetrait-connector/faq/
*/
define('BTC_LOCKDOWN', FALSE);

/*
Stop people from accessing the file directly and causing errors.
*/
if (!function_exists('add_action')) {
	die('You cannot run this file directly.');
}

/*
Don't break stuff if user tries to use this plugin on anything less than WordPress 2.0.0
*/
if (!function_exists('get_role')) {
	return;
}

//includes
if (!class_exists('nusoap_client')) {
	include('btc-nusoap.php');
}
include('btc-soap.class.php');
include('btc-soap-client.class.php');

/*
Required for WordPress 2.5+
*/
global $btc_version;
global $wpdb;

//bluetrait event viewer version number
$btc_version = '0.3';

//setup the database, check/upgrade database
btc_is_installed();
btc_site();
btc_upgrade();

//menu actions
add_action('admin_menu', 'btc_admin_menu_settings');

//events
add_action('activate_' . plugin_basename(__FILE__), 'btc_trigger_activate_btc');
add_action('deactivate_' . plugin_basename(__FILE__), 'btc_trigger_deactivate_btc');

//cron stuff
add_action('btc_cron_hourly_tasks_hook', 'btc_cron_hourly_tasks');
add_action('btc_cron_daily_tasks_hook', 'btc_cron_daily_tasks');

add_action('init', 'btc_add_admin_cap');
add_action('init', 'btc_uninstall');

//checks to see if btc is installed
function btc_is_installed() {
	global $wpdb;

	$installed = get_option('btc_installed');
	
	if(!$installed) {
		btc_install_system();
		return false;
	}
	else {
		return true;
	}

}

//this function checks that the user is really trying to uninstall and if they have permission to (if so uninstall)
function btc_uninstall() {
	
	if (isset($_POST['btc_submit_uninstall'])) {
		if (current_user_can('btc') && !BTC_LOCKDOWN) {
			if (function_exists('check_admin_referer')) {
				check_admin_referer('btc-uninstall');
			}
			btc_uninstall_system();
		}
		else {
			if (function_exists('btev_trigger_error')) {
				btev_trigger_error("Unauthorised Uninstall Attempt of Bluetrait Connector.", E_USER_WARNING);
			}
		}
	}
}

//install
function btc_install_system() {
	global $wpdb, $btc_version;

	//create config will do nothing if option already exists
	btc_create_config();	
	btc_site();
	btc_schedule_tasks();
	
	if (function_exists('btev_trigger_error')) {
		btev_trigger_error('Bluetrait Connector ' . $btc_version . ' Has Been Successfully Installed.', E_USER_NOTICE);
	}
	add_option('btc_installed', '1');
}

//this function does the uninstalling
function btc_uninstall_system() {
	global $wpdb;

	/*
	Deactivate Plugin
	*/
	$current = get_option('active_plugins');
	array_splice($current, array_search(plugin_basename(__FILE__), $current), 1 ); // Array-fu!
	update_option('active_plugins', $current);

	/*
	Delete Options from WordPress Table
	*/
	delete_option('btc_config');
	delete_option('btc_installed');
	
	/*
	Unschedule Cron Tasks
	*/
	if (function_exists('wp_clear_scheduled_hook')) {
		wp_clear_scheduled_hook('btc_cron_hourly_tasks_hook');
		wp_clear_scheduled_hook('btc_cron_daily_tasks_hook');
	}
	
	/*
	Redirect To Plugin Page
	*/
	wp_redirect('plugins.php?deactivate=true');
}

//upgrade database if needed
function btc_upgrade() {
	global $wpdb, $btc_version;

	if (btc_get_config('version') != $btc_version) {
		btc_set_config('version', '0.3', TRUE);
		if (function_exists('btev_trigger_error')) {
			btev_trigger_error('Bluetrait Connector upgraded to version ' . $btc_version, E_USER_NOTICE);
		}
	}

} 

//lets get the details about btc out of the database
function btc_site() {
	global $btc_site;
	
	$btc_site = get_option('btc_config');
}

//returns a config value from the site array
function btc_get_config($config_name) {
	global $btc_site;
	if (!empty($btc_site)) {
		if (array_key_exists($config_name, $btc_site)) {
			return $btc_site[$config_name];
		}
		else {
			return false;
		}
	}
	else {
		return false;
	}
}

//sets a config value into the array
function btc_set_config($config_name, $config_value, $update_now = FALSE) {
	global $btc_site;
	
	$btc_site[$config_name] = $config_value;
	
	if ($update_now) {
		btc_save_config();
	}
}

//saves the site array back to the database
function btc_save_config() {
	global $btc_site;
	
	update_option('btc_config', $btc_site);	
}

//populates the site table with info
function btc_create_config() {
	global $btc_version;
	
	$site = array();
	//version 0.1/0.2/0.3
	$site['installed'] = current_time('mysql');
	$site['version'] = $btc_version;
	$site['active'] = 0;
	$site['remote_site_id'] = '';
	$site['remote_site_soap_url'] = '';
	$site['remote_site_soap_username'] = '';

	add_option('btc_config', $site, 'Bluetrait Connector Config');
}


//add a role so that we can check if the user can "do stuff" (TM)
function btc_add_admin_cap() {

	$role = get_role('administrator');
	$role->add_cap('btc');

}

//link to bluetrait connector settings
function btc_subpanel_settings_link() {
	return 'options-general.php?page=btc.php_settings';
}

function btc_soap_register_client_site($connect_array) {
	$register_client	= new btc_soap($connect_array['url']);
	unset($connect_array['url']);
	$result 			= $register_client->call('bt_soap_register_site', array($connect_array));
	
	return $result;
}
function btc_soap_unregister_client_site() {
	$bt_soap	= new btc_soap_client();
	if ($bt_soap->call('bt_soap_unregister_site') == 1) {
		return true;
	}
	else {
		return false;
	}
}

//html for event viewer settings
function btc_subpanel_settings() {
?>

<?php
	if (isset($_POST['submit']) || isset($_POST['btc_soap_remove'])) {
		if (!BTC_LOCKDOWN) {
			if (isset($_POST['submit'])) {
				$connect_array['username'] 	= $_POST['btc_soap_username'];
				$connect_array['password'] 	= $_POST['btc_soap_password'];
				$connect_array['site_id']	= $_POST['btc_soap_site_id'];
				$connect_array['site_type']	= 'wordpress_blog';
				$connect_array['url'] 		= $_POST['btc_soap_server_url'];
				
				$result 					= btc_soap_register_client_site($connect_array);
						
				if ($result['success'] == 1) {
					$btc_message = 'Successfully connected to SOAP server';
					
					btc_set_config('remote_site_id', $connect_array['site_id']);
					btc_set_config('remote_site_soap_url', $connect_array['url']);
					btc_set_config('remote_site_soap_username', $connect_array['username']);
					btc_set_config('active', '1');
									
					btc_push_common();
					btc_push_events();
					
				}
				else {
					$btc_message = 'Failed to connect to SOAP server';
					btc_set_config('active', '0');
				}
				btc_save_config();
				if (function_exists('btev_trigger_error')) {
					btev_trigger_error($btc_message, E_USER_NOTICE);
				}
			}
			else if (isset($_POST['btc_soap_remove'])) {
				if (btc_soap_unregister_client_site()) {
					$btc_message = 'Successfully removed SOAP connection';
					btc_set_config('remote_site_id', '');
					btc_set_config('remote_site_soap_url', '');
					btc_set_config('remote_site_soap_username', '');
					btc_set_config('active', '0');
					btc_save_config();
				}
				else if (isset($_POST['btc_soap_force_remove']) && ($_POST['btc_soap_force_remove'] == 1)) {
					$btc_message = 'Forcefully removed SOAP connection';
					btc_set_config('remote_site_id', '');
					btc_set_config('remote_site_soap_url', '');
					btc_set_config('remote_site_soap_username', '');
					btc_set_config('active', '0');
					btc_save_config();
				}
				else {
					$btc_message = 'Failed to remove SOAP connection';
				}
				if (function_exists('btev_trigger_error')) {
					btev_trigger_error($btc_message, E_USER_NOTICE);
				}
			}
			
			if (function_exists('btev_trigger_error')) {
				btev_trigger_error("Bluetrait Connector Settings Updated.", E_USER_NOTICE);
			}
			?>
			<?php if (isset($btc_message)) { ?>
				<div id="message" class="updated fade"><p><strong><?php echo wp_specialchars($btc_message); ?></strong></p></div>
			<?php } ?>
		<?php
		}
		else {
			if (function_exists('btev_trigger_error')) {
				btev_trigger_error("Unauthorised Update Attempt of Bluetrait Connector Settings.", E_USER_WARNING);
			}
		}
	}
	if (function_exists('btev_get_config')) {
		if (version_compare(btev_get_config('version'), '1.9.0') === 1 || version_compare(btev_get_config('version'), '1.9.0') === 0) {
			$btc_event_sync_support = true;
		}
		else {
			$btc_event_sync_support = false;
		}
	}
	else {
		$btc_event_sync_support = false;
	}
	?>
<div class="wrap">
	<h2>Bluetrait Connector Settings</h2>
	<form action="<?php echo btc_subpanel_settings_link(); ?>" method="post">
		<table class="form-table">

		<tr valign="top">
			<?php if (btc_get_config('active') == 0) { ?>
			<th scope="row">Connect to Server</th>
			<td>
				<fieldset>
					<p>Remote Username <br /><input name="btc_soap_username" size="35"  type="text" value="" /></p>
					<p>Remote Password <br /><input name="btc_soap_password" size="35"  type="password" value="" /></p>
					<p>Site ID <br /><input name="btc_soap_site_id" size="35"  type="text" value="" /></p>
					<p>Server SOAP URL <br /><input name="btc_soap_server_url" size="35"  type="text" value="<?php
						if (isset($_POST['btc_soap_server_url'])) { 
							echo wp_specialchars($_POST['btc_soap_server_url']);
						} ?>" /></p>
					<p class="submit"><input type="submit" name="submit" value="Submit"/></p>
				</fieldset>
			</td>
			<?php } else { ?>
			<th scope="row">Connected</th>
			<td>
				<fieldset>
					<p>Connected To Server: <?php echo wp_specialchars(btc_get_config('remote_site_soap_url')); ?></p>
					<p>Forcefully remove SOAP connection if server rejects attempt? <input name="btc_soap_force_remove" type="checkbox" value="1" /></p>
					<p class="submit"><input type="submit" name="btc_soap_remove" value="Remove Connection"/></p>
				</fieldset>
			</td>
			<?php } ?>
		</tr>
		
		<tr valign="top">
			<th scope="row">Events Sync</th>
				<td>
				<?php if ($btc_event_sync_support) { ?>
					<p><strong>Successfully found a compatible version of Bluetrait Event Viewer.</strong></p><p>Events will sync every hour if connected.</p>
				<?php } else { ?>
					<p><strong>Unable to find a compatible version of Bluetrait Event Viewer.</strong></p><p>Please make sure Bluetrait Event Viewer 1.9.0+ is activated if you want events to sync.</p>
				<?php } ?>
				</td>
			</th>
		</tr>
		
		</table>
			
	</form>
	
	<div id="btc_uninstall">
		<script type="text/javascript">
		<!--
		function btc_uninstall() {
			if (confirm("Are you sure you wish to uninstall Bluetrait Connector?")){
				return true;
			}
			else{
				return false;
			}
		}
		//-->
		</script>
		<form action="<?php echo btc_subpanel_settings_link(); ?>" method="post" onsubmit="return btc_uninstall(this);">
		<?php
			if (function_exists('wp_nonce_field')) {
				wp_nonce_field('btc-uninstall');
			}
			?>
			<p class="submit"><input type="submit" name="btc_submit_uninstall" value="Uninstall" /> (This removes all BTC settings)</p>
		</form>
	</div>
</div>
<?php
}

//basic date function. Should be able to use a wordpress one though.
function btc_now($format = 'Y-m-d H:i:s', $add_seconds = 0) {

	$base_time = time() + $add_seconds + 3600 * get_option('gmt_offset');
	
	switch($format) {
	
		case 'Y-m-d H:i:s':
			return gmdate('Y-m-d H:i:s', $base_time);
		break;
		
		case 'H:i:s':
			return gmdate('H:i:s', $base_time);
		break;
		
		case 'Y-m-d':
			return gmdate('Y-m-d', $base_time);
		break;
		
		case 'Y':
			return gmdate('Y', $base_time);
		break;
		
		case 'm':
			return gmdate('m', $base_time);
		break;
		
		case 'd':
			return gmdate('d', $base_time);
		break;
	
	}
}

//adds the event viewer settings to the options submenu
function btc_admin_menu_settings() {
	if (function_exists('add_options_page')) {
		add_options_page('BTC Settings', 'Bluetrait Connector Settings', 8, basename(__FILE__) . '_settings', 'btc_subpanel_settings');
	}
}

/*
	The following part handles the cron section for Bluetrait Connector
===========================================================================================================================================
*/
//this is where we schedule the cron tasks
function btc_schedule_tasks() {

	if (function_exists('wp_next_scheduled')) {
		if (!wp_next_scheduled('btc_cron_daily_tasks_hook')) {
			wp_schedule_event(0, 'daily', 'btc_cron_daily_tasks_hook');
		}
		if (!wp_next_scheduled('btc_cron_hourly_tasks_hook')) {
			wp_schedule_event(0, 'hourly', 'btc_cron_hourly_tasks_hook');
		}
		return true;
	}
	else {
		return false;
	}

}

//this is where we can now put any functions we want to run every day
function btc_cron_daily_tasks() {
	if (btc_get_config('active') == 1) {
		btc_push_common();
	}
}

function btc_cron_hourly_tasks() {
	if (btc_get_config('active') == 1) {
		btc_push_events();
	}
}

function btc_common() {
	global $wp_version, $wp_db_version;
	
	$array[] = array('name' => 'site_type', 'value' => 'wordpress_blog');
	$array[] = array('name' => 'site_program_version', 'value' => $wp_version);
	$array[] = array('name' => 'site_database_version', 'value' => $wp_db_version);
	$array[] = array('name' => 'site_name', 'value' => get_option('blogname'));
	$array[] = array('name' => 'site_address', 'value' => get_option('siteurl'));
	$array[] = array('name' => 'site_api_version', 'value' => '1.0');
	
	return $array;
}

function btc_push_common() {
	
	$btc_soap_connection		= new btc_soap_client();
	
	if ($btc_soap_connection->is_connected()) {
		$common_array = btc_common();

		$result = $btc_soap_connection->call('bt_soap_receive_common', array($common_array));
		
		return $result;
	}
	else {
		return false;
	}
}

function btc_push_events() {

	if (function_exists('btev_get_events_not_synced')) {
		$events = btev_get_events_not_synced();
		if (count($events) > 0) {
			$btc_soap_connection		= new btc_soap_client();
			if ($btc_soap_connection->is_connected()) {
				$result = $btc_soap_connection->push_events($events);
				//mark syned events as sent
				if ($result['success'] == 1) {
					if (function_exists('btev_set_synced')) {
						btev_set_synced($events);
					}
				}
			}
		}
	}

}

/*
===========================================================================================================================================
*/
function btc_trigger_activate_btc() {
	if (function_exists('btev_trigger_error')) {
		btev_trigger_error('Bluetrait Connector activated.', E_USER_NOTICE);
	}
	return;
}
function btc_trigger_deactivate_btc() {
	if (!BTC_LOCKDOWN) {
		if (function_exists('btev_trigger_error')) {
			btev_trigger_error('Bluetrait Connector deactivated.', E_USER_NOTICE);
		}
	}
	else {
		add_action('shutdown', 'btc_lockdown_reactivate');
	}
	return;
}
function btc_lockdown_reactivate() {
	//nope BTC isn't going away
	if (function_exists('btev_trigger_error')) {
		btev_trigger_error("Unauthorised Deactivation Attempt of Bluetrait Connector.", E_USER_WARNING);
	}
	$current = get_option('active_plugins');
	if (!isset($current[plugin_basename(__FILE__)])) {
		$current[] = plugin_basename(__FILE__);
		sort($current);
		update_option('active_plugins', $current);
	}
	return;
}
?>