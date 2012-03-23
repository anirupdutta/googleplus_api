<?php
/**
 * 
 */

$user_id = elgg_get_logged_in_user_guid();
$googleplus_id = elgg_get_plugin_user_setting('access_key', $user_id, 'googleplus_api');
$access_secret = elgg_get_plugin_user_setting('access_secret', $user_id, 'googleplus_api');

$site_name = elgg_get_site_entity()->name;
echo '<div>' . elgg_echo('googleplus_api:usersettings:description', array($site_name)) . '</div>';

if (!$googleplus_id || !$access_secret) {
	// send user off to validate account
	$request_link = googleplus_api_get_authorize_url();
	echo '<div>' . elgg_echo('googleplus_api:usersettings:request', array($request_link, $site_name)) . '</div>';
} else {
	$url = elgg_get_site_url() . "googleplus_api/revoke";
	echo '<div>' . sprintf(elgg_echo('googleplus_api:usersettings:revoke'), $url) . '</div>';
}
