<?php

/*
    Booking Class:
    Used for Google calendar integration
    
*/
    


class SimpleCalendarClass
{
    // Debug variables
   public $debug_mode = false; 

    // OAuth2 constants
    const OAUTH2_REVOKE_URI = 'https://accounts.google.com/o/oauth2/revoke';
    const OAUTH2_TOKEN_URI = 'https://accounts.google.com/o/oauth2/token';
    const OAUTH2_AUTH_URL = 'https://accounts.google.com/o/oauth2/auth';
    const OAUTH2_FEDERATED_SIGNON_CERTS_URL = 'https://www.googleapis.com/oauth2/v1/certs';
    
    // Calendar constants
    const CAL_BASE_URL = 'https://www.googleapis.com/calendar/v3/calendars/';
    const USER_AGENT_SUFFIX = "google-api-php-client/0.4.8";
   

    /* 
    *   Construct function, sets up the apiConfig with the requeired variables
    *   @param (array) array
    *       client_id (string)  The client id from Google API Console
    *       redirect_uri (string)   The redirect uri from Google API Console
    *       scope (string)  The scope of the requests, which is essentially what you are planning to  access
    *       access_type (string)   The access type is either 'online' or 'offline', 'offline' gives you a longer access period and allows you to get a refresh_token
    *       response_type (string)  The response type refers to the flow of the program
    */
	function __construct($array)
	{
         $this->apiConfig = $array;
         $this->validate_token();
	}
    
    /* 
    *   Returns the url to the authorisation link, once used and a refresh token is retained, you'll never need this again
    *   @return (string) Google OAutht2 link
    */
    private function create_auth_url()
    {
        $params = array(
            'redirect_uri=' . urlencode($this->apiConfig['redirect_uri']),
            'client_id=' . urlencode($this->apiConfig['client_id']),
            'scope=' . urlencode($this->apiConfig['scope']),
            'access_type=' . urlencode($this->apiConfig['access_type']),
            'response_type=' .urlencode($this->apiConfig['response_type'])
        );
        $params = implode('&', $params);
        return self::OAUTH2_AUTH_URL . "?$params";
    }  
    
    /* 
    *   Returns a new access token from the refresh token
    *   @param (string) refresh_token 
    *   @return (string) New access token
    */
    public function refresh_token()
    {    
        $info = array(
              'refresh_token' => $this->apiConfig['refresh_token'],
              'grant_type' => 'refresh_token',
              'client_id' => $this->apiConfig['client_id'],
              'client_secret' => $this->apiConfig['client_secret']
        );
        
        // Get returned CURL request
        $request = $this->make_request(self::OAUTH2_TOKEN_URI, 'POST', 'normal', $info);
        
        // Push the new token into the apiConfig
        $this->apiConfig['access_token'] = $request->access_token;
        
        // Return the token
        return $request->access_token;
    }
      
    /* 
    *   Returns an access token from the code given in the first request to Google
    *   @param (string) data - the actual GET code given after authorisation
    *   @param (string) grant_type - always 'authorisation_code' in this instance
    *   @return (array) Contains all the returned data inc. access_token, refresh_token(first time only)
    */
    public function get_token($data, $grant_type)
    {
        if(!$grant_type) $grant_type = 'authorization_code';
        
        $info = array(
              'code' => $data,
              'grant_type' => $grant_type,
              'redirect_uri' => $this->apiConfig['redirect_uri'],
              'client_id' => $this->apiConfig['client_id'],
              'client_secret' => $this->apiConfig['client_secret']
        );
        
        // Get the returned CURL request
        $request = $this->make_request(self::OAUTH2_TOKEN_URI, 'POST', 'normal', $info);
        
        // Push the new data into the apiConfig
        $this->apiConfig['code'] = $data;
        $this->apiConfig['access_token'] = $request->access_token;
        
        // Return all request data
        return $request;
    }
    
    /*
    *	Check the access_token is still valid, if not use the refresh_token to get a new one
    *   
    */
    public function validate_token()
    {
        // make a dummy request
        $events = $this->get_calendars();
        if(isset($events->error->code) && $events->error->code == '401')
        {
            $data = $this->refresh_token();
            return $data;
        }
    }   
    
    
    /* 
    *   CURL request function
    *   @param (string) url - Obvious
    *   @param (string) method - POST, GET, PUT, DELETE, whatever...
    *   @param (string) data - We shall see...
    *   @return (object) Returns data cleanly
    */
    public function make_request($url, $method, $type, $data)
    {
        // Init and build/switch methods
        $ch = curl_init($url);
        if ($method == 'GET')
        {
            foreach($data as $k => $v)
            {
                $get = '';
                $get = $get . '&' . $k . '=' . $v;
            }
            $url = $url . "?" . $get;
            $options = array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 2
            );
            curl_setopt_array($ch, $options);
        } 
           
        // JSON Encode or not
        if($type == 'json')
        {
			$post_fields = json_encode($data);
			$header = array( "Authorization: Bearer " .  $this->apiConfig['access_token'] , "Host: www.googleapis.com",  "Content-Type: application/json",   "Content-Length: " . strlen(json_encode($data)));
		}else{
			$post_fields = $data
			$header =  array( "Authorization: Bearer " .  $this->apiConfig['access_token'] , "Host: www.googleapis.com");
		}
        
        // Build basic options array
        $options = array(
			CURLINFO_HEADER_OUT => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_SSL_VERIFYPEER => 1,
			CURLOPT_VERBOSE => 1,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POSTFIELDS => $post_fields,
			CURLOPT_HTTPHEADER => $header,
			CURLOPT_POSTFIELDS => $post_fields;
		);
		
		// Push the data type if normal
		if($method == 'POST' && type == 'normal')
		{
			array_push($options, array(CURLOPT_POST => 1));
		}
		
		// Set CURL options
		curl_setopt_array($ch, $options);              

        // Make CURL reponse
        $response = curl_exec($ch);     
        
        // CURL info gathering
        $curl_info['sent'] = curl_getinfo($ch, CURLINFO_HEADER_OUT);
        $curl_info['respHeaderSize'] = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curl_info['respHttpCode'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_info['curlErrorNum'] = curl_errno($ch);
        $curl_info['curlError'] = curl_error($ch);
        $curl_info['url'] = $url;
        
        // Close CURL
        curl_close($ch);
        $response = json_decode($response);
        
        // Check for errors ** DEV MODE **
        if($this->debug_mode == true)
        {
            if ($curl_info['curlErrorNum'] > 0) 
            {
                throw new apiIOException("HTTP Error: ($respHttpCode) $curlError");
            }
            foreach($curl_info as $k => $v)
            {
                $error[$k] = $v;
            }
            $response->headers = $error;
        }
        // Returns data
        return $response;
    }
  
   
   
	/* 
    *   @param (string) calendar_id - the calendar id, if its blank it will revert to the primary one
    *   @return (object) Returns all calendar events for this calendar
    */
	public function get_events($calendar_id = NULL)
	{
        $calendar_id = ($calendar_id == NULL ? 'primary' : $calendar_id);
        $url = self::CAL_BASE_URL . $calendar_id . '/events';
        $events = $this->make_request($url, 'GET', 'normal', array('access_token' => $this->apiConfig['access_token']));
        return $events;
	}
    
	/* 
    *   @param (string) calendar_id - the calendar id, if its blank it will revert to the primary one
    *   @param (string) event_id - the event id, must be present or the request is useless
    *   @return (object) Returns a calendar event for this calendar
    */
	public function get_event($calendar_id = NULL, $event_id)
	{
        if(!$event_id) return array('error' => 'No Event ID specified');
        $calendar_id = ($calendar_id == NULL ? 'primary' : $calendar_id);
        $url = self::CAL_BASE_URL . $calendarID . '/events/' . $event_id;
        $events = $this->make_request($url, 'GET', 'normal', array('access_token' => $this->apiConfig['access_token'])); 
        return $events;	
	}

	/* 
    *   @return (object) Returns a list of Calendars
    */
	public function get_calendars()
	{
        $url = 'https://www.googleapis.com/calendar/v3/users/me/calendarList';
        $events = $this->make_request($url, 'GET', 'normal', array('access_token' => $this->apiConfig['access_token'])); 
        return $events;	
	}
	
    /* 
    *   @param (string) calendar_id - the calendar id, if its blank it will revert to the primary one
    *   @param (array) array
                calendar_id (string)
                start_date (string) - dd-mm-yyyy 
                end_date (string) - dd-mm-yyyy
                summary (string)
                description (string)   
    *   @return (object) Returns a calendar event for this calendar
    */
	public function create_event($array)
	{
       $calendar_id = ($array['CalendarID'] == NULL ? 'primary' : $array['CalendarID'] );
       
       $data = array(
            "access_token" => $this->apiConfig['access_token'],
             "kind" => "calendar#event",
             "status" =>"tentative",
             "summary" => "Booked, ref: " . $array['BookingRef'],
             "description" => "Custom Booking from Go Explore",
             "start" => array(
                "dateTime" => date(DATE_ATOM, strtotime($array['StartDate'] . ' 12:01pm')),
                "timeZone" => "GMT"
             ),
             "end" => array(
                "dateTime" =>date(DATE_ATOM, strtotime($array['EndDate'] . ' 12:00pm')),
                "timeZone" => "GMT"
             ),
             "colorId" => "4"
        );

        $url = self::CAL_BASE_URL . $calendar_id . '/events'; 
        $events = $this->make_request($url, 'POST', 'json', $data); 
        return $events;
	}
    
    /* 
    *   Deletes an Event
    *   @param (array) array
                calendar_id (string)
                start_date (string) - dd-mm-yyyy 
                end_date (string) - dd-mm-yyyy
    *   @return (object) Returns a calendar event for this calendar
    */
	public function delete_event($array)
	{
       if(!$array['event_id']) return array('error' => 'no event specified');
       $calendar_id = ($array['calendar_id'] == NULL ? 'primary' : $array['calendar_id'] );
       $event_id = $array['event_id'];
       
        $url = self::CAL_BASE_URL . $calendar_id . '/events/' . $event_id; 
        $events = $this->make_request($url, 'DELETE',  'json',  null); 
        return $events;
	}
    
    /* 
    *   Deletes an Event
    *   @param (array) array
                calendar_id (string)
                start_date (string) - dd-mm-yyyy 
                end_date (string) - dd-mm-yyyy
    *   @return (object) Returns a calendar event for this calendar
    */
    public function update_event($array)
    {
       if(!$array['event_id']) return array('error' => 'no event specified');
       $calendar_id = ($array['calendar_id'] == NULL ? 'primary' : $array['calendar_id'] );
       $event_id = $array['event_id'];
       
       $data = array(
             "start" => array(
                "dateTime" => date(DATE_ATOM, strtotime($array['start'] . ' 12:01pm'))
             ),
             "end" => array(
                "dateTime" =>date(DATE_ATOM, strtotime($array['end'] . ' 12:00pm'))
             )
        );
       
       
       $url = self::CAL_BASE_URL . $calendar_id .'/events/' . $event_id;
       $events = $this->make_request($url, 'PUT', 'json', $data);
       return $events;
    }
 
    
}

?>
