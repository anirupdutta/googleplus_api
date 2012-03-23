<?php
/**
 * Common library of functions used by googleplus Services.
 *
 * @package googleplus_api
 */

/**
 * Tests if the system admin has enabled Sign-On-With-googleplus
 *
 * @param void
 * @return bool
 */
function googleplus_api_allow_sign_on_with_googleplus() {
	if (!$consumer_key = elgg_get_plugin_setting('consumer_key', 'googleplus_api')) {
		return FALSE;
	}

	if (!$consumer_secret = elgg_get_plugin_setting('consumer_secret', 'googleplus_api')) {
		return FALSE;
	}

	return elgg_get_plugin_setting('sign_on', 'googleplus_api') == 'yes';
}

/**
 * Forwards
 *
 * @todo what is this?
 */
function googleplus_api_forward() {
	// sanity check
	if (!googleplus_api_allow_sign_on_with_googleplus()) {
		forward();
	}

	$request_link = googleplus_api_get_authorize_url();
	forward($request_link, 'googleplus_api');
}

/**
 * Log in a user with googleplus.
 */
function googleplus_api_login($token) {

	// sanity check
	if (!googleplus_api_allow_sign_on_with_googleplus()) {
		forward();
	}

	if(!$token->access_token) {         
		register_error(elgg_echo('googleplus_api:login:error'));
		forward();
	}

	elgg_load_library('apiClient');
	elgg_load_library('apiPlusService');
        elgg_load_library('apiOauth2Service');

		// check admin settings
	$client_id = elgg_get_plugin_setting('consumer_key', 'googleplus_api');
	$client_secret = elgg_get_plugin_setting('consumer_secret', 'googleplus_api');
        $developer_key = elgg_get_plugin_setting('developer_key', 'googleplus_api');
        
	if (!($client_id && $client_secret && $developer_key)) {
		return NULL;
	}

        $client = new apiClient();
        $site = elgg_get_site_entity();
        $client->setApplicationName($site->name);
        
        $client->setClientId($client_id);
        $client->setClientSecret($client_secret);
        $client->setDeveloperKey($developer_key);
        $client->setAccessToken($token);
        
        $plus = new apiPlusService($client);
        $oauth2 = new apiOauth2Service($client);
        
        $me = $plus->people->get('me');


	// attempt to find user and log them in.
	// else, create a new user.
	$options = array(
		'type' => 'user',
		'plugin_user_setting_name_value_pairs' => array(
                        'googleplus_url' => $me['url'],
			'access_key' => $me['id'],
		),
		'plugin_user_setting_name_value_pairs_operator' => 'AND',
		'limit' => 0
	);
	
	$users = elgg_get_entities_from_plugin_user_settings($options);

	if ($users) {
		if (count($users) == 1 && login($users[0])) {
			system_message(elgg_echo('googleplus_api:login:success'));
			elgg_set_plugin_user_setting('access_secret', $token, $users[0]->guid);

		} else {
			system_message(elgg_echo('googleplus_api:login:error'));
		}

		forward();
	} else {
		// need googleplus account credentials
	        $user = FALSE;

		// create new user
		if (!$user) {
			// check new registration allowed
			if (!googleplus_api_allow_new_users_with_googleplus()) {
				register_error(elgg_echo('registerdisabled'));
				forward();
			}

			// Elgg-ify googleplus credentials
                        $username = str_replace(' ', '', strtolower($me['displayName']));
			while (get_user_by_username($username)) {
				$username = str_replace(' ', '', strtolower($me['displayName'])) . '_' . rand(1000, 9999);
			}

			$password = generate_random_cleartext_password();
			$name = $me['displayName'];

			$user = new ElggUser();
			$user->username = $username;
			$user->name = $name;
			$user->access_id = ACCESS_PUBLIC;
			$user->salt = generate_random_cleartext_password();
			$user->password = generate_user_password($user, $password);
			$user->owner_guid = 0;
			$user->container_guid = 0;
                        
                        $google_user = $oauth2->userinfo->get();

                        // These fields are currently filtered through the PHP sanitize filters.
                        // See http://www.php.net/manual/en/filter.filters.sanitize.php
                        $google_email = filter_var($google_user['email'], FILTER_SANITIZE_EMAIL);
                        
                        if($google_email)
                        {
                            $user->email = $google_email;
                        }
                        if($google_user['profile'])
                        {
                            $user->description = $google_user['profile'];
                        }
                        if($google_email)
                        {
                            $user->contactemail = $google_email;
                        }
                        if($me['tagline'])
                        {
                            $user->briefdescription = $me['tagline'];
                        }
                        if($me['currentLocation'])
                        {
                            $user->briefdescription = $me['currentLocation'];
                        }
                        
			if (!$user->save()) {
				register_error(elgg_echo('registerbad'));
				forward();
			}

			// @todo require email address?

			$forward = "profile/{$user->username}";
		}

		// set googleplus services tokens
		elgg_set_plugin_user_setting('googleplus_url', $me['url'], $user->guid);
		elgg_set_plugin_user_setting('access_key', $me['id'], $user->guid);
		elgg_set_plugin_user_setting('access_secret', $token, $user->guid);

		// pull in googleplus icon
                
                $profile_image = filter_var($me['image']['url'], FILTER_VALIDATE_URL);
		googleplus_api_update_user_avatar($user, $profile_image);

		// login new user
		if (login($user)) {

			system_message(elgg_echo('googleplus_api:login:success'));

		} else {

			system_message(elgg_echo('googleplus_api:login:error'));
		}

		forward($forward, 'googleplus_api');
	}

	// register login error
	register_error(elgg_echo('googleplus_api:login:error'));
	forward();
}

/**
 * Pull in the latest avatar from googleplus.
 *
 * @param unknown_type $user
 * @param unknown_type $file_location
 */
function googleplus_api_update_user_avatar($user, $file_location) {
	// @todo Should probably check that it's an image file.
	//$file_location = str_replace('_normal.jpg', '.jpg', $file_location);

	$sizes = array(
		'topbar' => array(16, 16, TRUE),
		'tiny' => array(25, 25, TRUE),
		'small' => array(40, 40, TRUE),
		'medium' => array(100, 100, TRUE),
		'large' => array(200, 200, FALSE),
		'master' => array(550, 550, FALSE),
	);

	$filehandler = new ElggFile();
	$filehandler->owner_guid = $user->getGUID();
	foreach ($sizes as $size => $dimensions) {
		$image = get_resized_image_from_existing_file(
			$file_location,
			$dimensions[0],
			$dimensions[1],
			$dimensions[2]
		);

		$filehandler->setFilename("profile/$user->guid$size.jpg");
		$filehandler->open('write');
		$filehandler->write($image);
		$filehandler->close();
	}
	
	// update user's icontime
	$user->icontime = time();

	return TRUE;
}

/**
 * User-initiated googleplus authorization
 *
 * Callback action from googleplus registration. Registers a single Elgg user with
 * the authorization tokens. Will revoke access from previous users when a
 * conflict exists.
 *
 * Depends upon {@link googleplus_api_get_authorize_url} being called previously
 * to establish request tokens.
 */
function googleplus_api_authorize() {
	$token = googleplus_api_get_access_token($_GET['code']);
        if(!isloggedin()){
		googleplus_api_login($token);
        }
	if(!$token->access_token) {         
		register_error(elgg_echo('googleplus_api:authorize:error'));
		forward('settings/plugins', 'googleplus_api');
	}

        elgg_load_library('apiClient');
	elgg_load_library('apiPlusService');
	

	// check admin settings
	$client_id = elgg_get_plugin_setting('consumer_key', 'googleplus_api');
	$client_secret = elgg_get_plugin_setting('consumer_secret', 'googleplus_api');
        $developer_key = elgg_get_plugin_setting('developer_key', 'googleplus_api');
        
	if (!($client_id && $client_secret && $developer_key)) {
		return NULL;
	}

        $client = new apiClient();
        $site = elgg_get_site_entity();
        $client->setApplicationName($site->name);
        
        $client->setClientId($client_id);
        $client->setClientSecret($client_secret);
        $client->setDeveloperKey($developer_key);
        $client->setAccessToken($token);
        
        $plus = new apiPlusService($client);
        
        $me = $plus->people->get('me');
	
        // make sure no other users are registered to this googleplus account.
	$options = array(
		'type' => 'user',
		'plugin_user_setting_name_value_pairs' => array(
                        'googleplus_url' => $me['url'],
			'access_key' => $me['id'],
		),
		'limit' => 0
	);

	$users = elgg_get_entities_from_plugin_user_settings($options);

	if ($users) {
		foreach ($users as $user) {
			// revoke access
			elgg_unset_plugin_user_setting('googleplus_url', $user->getGUID());
			elgg_unset_plugin_user_setting('access_key', $user->getGUID());
			elgg_unset_plugin_user_setting('access_secret', $user->getGUID());
		}
	}

	// register user's access tokens
	elgg_set_plugin_user_setting('googleplus_url', $me['url']);
	elgg_set_plugin_user_setting('access_key', $me['id']);
	elgg_set_plugin_user_setting('access_secret', $token);
	
	system_message(elgg_echo('googleplus_api:authorize:success'));
	forward('settings/plugins', 'googleplus_api');
}

/**
 * Remove googleplus access for the currently logged in user.
 */
function googleplus_api_revoke() {
	// unregister user's access tokens
	elgg_unset_plugin_user_setting('googleplus_url');
	elgg_unset_plugin_user_setting('access_key');
	elgg_unset_plugin_user_setting('access_secret');

	system_message(elgg_echo('googleplus_api:revoke:success'));
	forward('settings/plugins', 'googleplus_api');
}

/**
 * Returns the url to authorize a user.
 *
 * @param string $callback The callback URL?
 */
function googleplus_api_get_authorize_url() {
	global $SESSION;
	$redirectUri = elgg_get_site_url().'googleplus_api/authorize';

        elgg_load_library('apiClient');
        
        $client_id = elgg_get_plugin_setting('consumer_key', 'googleplus_api');
	$client_secret = elgg_get_plugin_setting('consumer_secret', 'googleplus_api');
        $developer_key = elgg_get_plugin_setting('developer_key', 'googleplus_api');
        
        
        if (!($client_id && $client_secret && $developer_key)) {
		return NULL;
	}
        
        $client = new apiClient();
        $client->setApplicationName($site->name);
        $client->setClientId($client_id);
        $client->setClientSecret($client_secret);
        $client->setRedirectUri($redirectUri);
        $client->setDeveloperKey($developer_key);
        
        $client->setScopes(array('https://www.googleapis.com/auth/userinfo.profile','https://www.googleapis.com/auth/userinfo.email','https://www.googleapis.com/auth/plus.me','https://www.googleapis.com/auth/tasks','https://www.googleapis.com/auth/calendar','https://www.googleapis.com/auth/urlshortener'));


	// get authorize url
	$authorizeurl = $client->createAuthUrl();
	return $authorizeurl;
}

/**
 * Returns the access token to use in googleplus calls.
 *
 * @param unknown_type $code
 */
function googleplus_api_get_access_token($code) {
	global $SESSION;

        elgg_load_library('apiClient');
        
        $client_id = elgg_get_plugin_setting('consumer_key', 'googleplus_api');
	$client_secret = elgg_get_plugin_setting('consumer_secret', 'googleplus_api');
        $developer_key = elgg_get_plugin_setting('developer_key', 'googleplus_api');
        
        
        if (!($client_id && $client_secret && $developer_key)) {
		return NULL;
	}
        
        $client = new apiClient();
        $client->setApplicationName($site->name);
        $client->setClientId($client_id);
        $client->setClientSecret($client_secret);
        $client->setDeveloperKey($developer_key);
          
        $client->authenticate();
        $token = $client->getAccessToken();

	return $token;
}

/**
 * Checks if this site is accepting new users.
 * Admins can disable manual registration, but some might want to allow
 * googleplus-only logins.
 */
function googleplus_api_allow_new_users_with_googleplus() {
	$site_reg = elgg_get_config('allow_registration');
	$googleplus_reg = elgg_get_plugin_setting('new_users');

	if ($site_reg || (!$site_reg && $googleplus_reg == 'yes')) {
		return true;
	}

	return false;
}