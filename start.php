<?php

elgg_register_event_handler('init', 'system', 'googleplus_api_init');

function googleplus_api_init() {
	global $CONFIG;

	$base = elgg_get_plugins_path() . 'googleplus_api';
      
	elgg_register_library('apiClient', "$base/vendors/google-api-php-client/src/apiClient.php");
	elgg_register_library('apiPlusService', "$base/vendors/google-api-php-client/src/contrib/apiPlusService.php");
	elgg_register_library('apiUrlshortenerService', "$base/vendors/google-api-php-client/src/contrib/apiUrlshortenerService.php");
	elgg_register_library('apiCalendarService', "$base/vendors/google-api-php-client/src/contrib/apiCalendarService.php");
	elgg_register_library('apiTasksService', "$base/vendors/google-api-php-client/src/contrib/apiTasksService.php");
        elgg_register_library('apiOauth2Service',"$base/vendors/google-api-php-client/src/contrib/apiOauth2Service.php");

        
        elgg_register_library('googleplus_api', "$base/lib/googleplus_api.php");

	elgg_load_library('googleplus_api');

	elgg_extend_view('css', 'googleplus_api/css');

	// sign on with google
	if (googleplus_api_allow_sign_on_with_googleplus()) {
		elgg_extend_view('login/extend', 'googleplus_api/login');
	}

	// register page handler
	elgg_register_page_handler('googleplus_api', 'googleplus_api_pagehandler');

	// allow plugin authors to hook into this service
	elgg_register_plugin_hook_handler('activity', 'googleplus_service', 'googleplus_api_activity');
	elgg_register_plugin_hook_handler('comments', 'googleplus_service', 'googleplus_api_comments');
	elgg_register_plugin_hook_handler('urlshortner', 'googleplus_service', 'googleplus_api_urlshortner');
	elgg_register_plugin_hook_handler('event_create', 'googleplus_service', 'googleplus_api_event');
	elgg_register_plugin_hook_handler('task_create', 'googleplus_service', 'googleplus_api_task');
        
}

function googleplus_api_pagehandler($page) {
	if (!isset($page[0])) {
		forward();
	}

	switch ($page[0]) {
		case 'authorize':
			googleplus_api_authorize();
			break;
		case 'revoke':
			googleplus_api_revoke();
			break;
		case 'forward':
			googleplus_api_forward();
			break;
		case 'login':
			googleplus_api_login();
			break;
		default:
			forward();
			break;
	}
}



/**
 * Retrieve list of activity from googleplus.
 *
 * @param unknown_type $hook
 * @param unknown_type $entity_type
 * @param unknown_type $returnvalue
 * @param unknown_type $params
 */
function googleplus_api_activity($hook, $entity_type, $returnvalue, $params) {

	elgg_load_library('apiClient');
	elgg_load_library('apiPlusService');
	
        $redirectUri = elgg_get_site_url().'googleplus_api/authorize';
        
	// check admin settings

        $client_id = elgg_get_plugin_setting('consumer_key', 'googleplus_api');
        $client_secret = elgg_get_plugin_setting('consumer_secret', 'googleplus_api');
        
        $developer_key = elgg_get_plugin_setting('developer_key', 'googleplus_api');
        
	if (!($client_id && $client_secret && $developer_key)) {
		return NULL;
	}

	// check user settings
	$user_id = $params['user'];
	$googleplus_id = elgg_get_plugin_user_setting('access_key', $user_id, 'googleplus_api');
	$access_token = elgg_get_plugin_user_setting('access_secret', $user_id, 'googleplus_api');
	if (!($googleplus_id && $access_token)) {
		return NULL;
	}

        $client = new apiClient();
        $site = elgg_get_site_entity();
        $client->setApplicationName($site->name);
        
        $client->setClientId($client_id);
        $client->setClientSecret($client_secret);
        $client->setDeveloperKey($developer_key);
        $client->setRedirectUri($redirectUri);
        $client->setAccessToken($access_token);
        
        $plus = new apiPlusService($client);
        
        $optParams = array(
            'maxResults' => $params['maxResults'],
            'pageToken' => $params['pageToken']
        );
        
        $activities = $plus->activities->listActivities($googleplus_id, 'public', $optParams);

	return $activities;
}

/**
 * Retrieve comments from googleplus.
 *
 * @param unknown_type $hook
 * @param unknown_type $entity_type
 * @param unknown_type $returnvalue
 * @param unknown_type $params
 */
function googleplus_api_comments($hook, $entity_type, $returnvalue, $params) {

	elgg_load_library('apiClient');
	elgg_load_library('apiPlusService');
	
        $redirectUri = elgg_get_site_url().'googleplus_api/authorize';
        
	// check admin settings
	$client_id = elgg_get_plugin_setting('consumer_key', 'googleplus_api');
	$client_secret = elgg_get_plugin_setting('consumer_secret', 'googleplus_api');
        $developer_key = elgg_get_plugin_setting('developer_key', 'googleplus_api');
        
	if (!($client_id && $client_secret && $developer_key)) {
		return NULL;
	}

	// check user settings
	$user_id = $params['user'];
	$googleplus_id = elgg_get_plugin_user_setting('access_key', $user_id, 'googleplus_api');
	$access_token = elgg_get_plugin_user_setting('access_secret', $user_id, 'googleplus_api');
	if (!($googleplus_id && $access_token)) {
		return NULL;
	}

        if(!$params[activityId])
        {
            return NULL;
        }
        
        $client = new apiClient();
        $site = elgg_get_site_entity();
        $client->setApplicationName($site->name);
        
        $client->setClientId($client_id);
        $client->setClientSecret($client_secret);
        $client->setDeveloperKey($developer_key);
        $client->setRedirectUri($redirectUri);        
        $client->setAccessToken($access_token);
        
        $plus = new apiPlusService($client);
        
        $optParams = array(
            'maxResults' => $params['maxResults'],
            'pageToken' => $params['pageToken'],
            'sortOrder' => $params['sortOrder']
        );
        
        $comments = $plus->comments->listComments($params[activityId],$optParams);

	return $comments;
}

/**
 * Use google url shortner to shorten an url.
 *
 * @param unknown_type $hook
 * @param unknown_type $entity_type
 * @param unknown_type $returnvalue
 * @param unknown_type $params
 */
function googleplus_api_urlshortner($hook, $entity_type, $returnvalue, $params) {

    	elgg_load_library('apiClient');
	elgg_load_library('apiUrlshortenerService');

        $redirectUri = elgg_get_site_url().'googleplus_api/authorize';  
        
	// check admin settings
	
	if($params['UseOauth'])
        {
		$client_id = elgg_get_plugin_setting('consumer_key', 'googleplus_api');
		$client_secret = elgg_get_plugin_setting('consumer_secret', 'googleplus_api');
        }
        $developer_key = elgg_get_plugin_setting('developer_key', 'googleplus_api');
        
	if (!($client_id && $client_secret) && !($developer_key)) {
		return NULL;
	}

	// check user settings
	$user_id = $params['user'];
	$googleplus_id = elgg_get_plugin_user_setting('access_key', $user_id, 'googleplus_api');
	$access_token = elgg_get_plugin_user_setting('access_secret', $user_id, 'googleplus_api');
	
        if (!($googleplus_id && $access_token)&& !($developer_key)) {
		return NULL;
	}

        if(!$params['url'])
        {
            return NULL;
        }
        
        $client = new apiClient();
        $site = elgg_get_site_entity();
        $client->setApplicationName($site->name);
        
        if($client_id)
        {
            $client->setClientId($client_id);
        }
        if($client_secret)
        {
            $client->setClientSecret($client_secret);
        }
        if($access_token)
        {
            $client->setAccessToken($access_token);
        }
        if($developer_key)
        {
            $client->setDeveloperKey($developer_key);            
        }
        $client->setRedirectUri($redirectUri);
        $service = new apiUrlshortenerService($client);
        
        $url = new Url();
        $url->longUrl = $params['url'];
        $short = $service->url->insert($url);
        
        return $short;
}


/**
 * Use google calendar service.
 *
 * @param unknown_type $hook
 * @param unknown_type $entity_type
 * @param unknown_type $returnvalue
 * @param unknown_type $params
 */
function googleplus_api_event($hook, $entity_type, $returnvalue, $params) {

    	elgg_load_library('apiClient');
	elgg_load_library('apiCalendarService');

        $redirectUri = elgg_get_site_url().'googleplus_api/authorize';
        
	// check admin settings
	$client_id = elgg_get_plugin_setting('consumer_key', 'googleplus_api');
	$client_secret = elgg_get_plugin_setting('consumer_secret', 'googleplus_api');
        $developer_key = elgg_get_plugin_setting('developer_key', 'googleplus_api');
        
	if (!($client_id && $client_secret && $developer_key)) {
		return NULL;
	}

	// check user settings
	$user_id = $params['user'];
	$googleplus_id = elgg_get_plugin_user_setting('access_key', $user_id, 'googleplus_api');
	$access_token = elgg_get_plugin_user_setting('access_secret', $user_id, 'googleplus_api');
	if (!($googleplus_id && $access_token)) {
		return NULL;
	}

        
        $client = new apiClient();
        $site = elgg_get_site_entity();
        $client->setApplicationName($site->name);
        
        $client->setClientId($client_id);
        $client->setClientSecret($client_secret);
        $client->setDeveloperKey($developer_key);
        $client->setRedirectUri($redirectUri);
        $client->setAccessToken($access_token);
        
        
        if(!$params['primary_calendar'])
        {
            
            if(!$params['calendar_summary'])
            {
                return NULL;
            }
        
            $calendar = new Calendar();
            $calendar->setSummary($params['calendar_summary']);
            $calendar->setTimeZone($params['timezone']);

            $createdCalendar = $service->calendars->insert($calendar);
        }

        if(!($params['event_summary'] && $params['email_event_attendee'] && $params['event_start_time']) && $params['event_end_time'])
        {
            return NULL;
        }
        
        $event = new Event();
        $event->setSummary($params['event_summary']);
        $event->setLocation($params['event_location']);
        
        $start = new EventDateTime();

        $start->setDateTime($params['event_start_time']);
        $event->setStart($start);

        $end = new EventDateTime();
        $end->setDateTime($params['event_end_time']);
        

        $event->setEnd($end);

        $attendee1 = new EventAttendee();
        $attendee1->setEmail($params['email_event_attendee']);
        $attendee1->setDisplayName($params['display_name_event_attendee']);

        $attendees = array($attendee1);

        $event->attendees = $attendees;
        
        if($params['primary_calendar'])
        {
            $createdEvent = $service->events->insert('primary', $event);
        }
        else
        {
            $createdEvent = $service->events->insert($createdCalendar->getId(), $event);
        }
        
        return $createdEvent->getId();
}



/**
 * Use google task service.
 *
 * @param unknown_type $hook
 * @param unknown_type $entity_type
 * @param unknown_type $returnvalue
 * @param unknown_type $params
 */
function googleplus_api_task($hook, $entity_type, $returnvalue, $params) {

    	elgg_load_library('apiClient');
	elgg_load_library('apiTasksService');

        $redirectUri = elgg_get_site_url().'googleplus_api/authorize';
                
	// check admin settings
	$client_id = elgg_get_plugin_setting('consumer_key', 'googleplus_api');
	$client_secret = elgg_get_plugin_setting('consumer_secret', 'googleplus_api');
        
        
	if (!($client_id && $client_secret)) {
		return NULL;
	}

	// check user settings
	$user_id = $params['user'];
	$googleplus_id = elgg_get_plugin_user_setting('access_key', $user_id, 'googleplus_api');
	$access_token = elgg_get_plugin_user_setting('access_secret', $user_id, 'googleplus_api');
	if (!($googleplus_id && $access_token)) {
		return NULL;
	}

        
        $client = new apiClient();
        $site = elgg_get_site_entity();
        $client->setApplicationName($site->name);
        
        $client->setClientId($client_id);
        $client->setClientSecret($client_secret);
        $client->setRedirectUri($redirectUri);
        $client->setAccessToken($access_token);
        
        
        if(!$params['primary_calendar'])
        {
            
            if(!$params['task_title'])
            {
                return NULL;
            }
        
            $tasklist = new TaskList();
            $tasklist->setTitle($params['task_title']);
            $createdtasklist = $service->insertTasklists($tasklist);

        }

        $task = new Task();
        if(!$params['task_title'])
        {
            $params['task_title'] = 'New Task';
        }
        if(!$params['task_notes'])
        {
            $params['task_notes'] = 'Please Complete me';
        }
        
        $task->setTitle($params['task_title']);
        $task->setNotes($params['task_notes']);
        $task->setDue(new TaskDateTime($params['task_duetime']));

        
        if($params['primary_calendar'])
        {
            $createdtask = $service->insertTasks('@default', $task);
        }
        else
        {
            $createdtask = $service->events->insert($createdtasklist->getId(), $task);
        }
        
        return $createdtask->getId();
}