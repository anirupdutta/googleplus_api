<?php
/**
 * An english language definition file
 */

$english = array(
	'googleplus_api' => 'googleplus Services',

	'googleplus_api:requires_oauth' => 'googleplus Services requires the OAuth Libraries plugin to be enabled.',

	'googleplus_api:consumer_key' => 'Client ID',
	'googleplus_api:consumer_secret' => 'Client Secret',

	'googleplus_api:settings:instructions' => 'You must obtain a client id and secret from <a href="https://googleplus.com/oauth/" target="_blank">googleplus</a>. Most of the fields are self explanatory, the one piece of data you will need is the callback url which takes the form http://[yoursite]/action/googlepluslogin/return - [yoursite] is the url of your Elgg network.',

	'googleplus_api:usersettings:description' => "Link your %s account with googleplus.",
	'googleplus_api:usersettings:request' => "You must first <a href=\"%s\">authorize</a> %s to access your googleplus account.",
	'googleplus_api:authorize:error' => 'Unable to authorize googleplus.',
	'googleplus_api:authorize:success' => 'googleplus access has been authorized.',

	'googleplus_api:usersettings:authorized' => "You have authorized %s to access your googleplus account: @%s.",
	'googleplus_api:usersettings:revoke' => 'Click <a href="%s">here</a> to revoke access.',
	'googleplus_api:revoke:success' => 'googleplus access has been revoked.',

	'googleplus_api:login' => 'Allow existing users who have connected their googleplus account to sign in with googleplus?',
	'googleplus_api:new_users' => 'Allow new users to sign up using their googleplus account even if manual registration is disabled?',
	'googleplus_api:login:success' => 'You have been logged in.',
	'googleplus_api:login:error' => 'Unable to login with googleplus.',
	'googleplus_api:login:email' => "You must enter a valid email address for your new %s account.",
);

add_translation('en', $english);
