<?php
	/*
		This class is used for SOAP communication between a WordPress Client and a Compatible SOAP server.
		Please Note: This class does not handle the client registration process
	*/
	class btc_soap_client extends btc_soap {
		
		private $config 		= NULL;
		private $is_connected	= FALSE;
		private $common			= NULL;
	
		function __construct() {
		
			if (btc_get_config('active') == 1) {

				$this->config['remote_server_url']			= btc_get_config('remote_site_soap_url');
				$this->config['remote_server_username']		= btc_get_config('remote_site_soap_username');
				$this->config['remote_server_site_id']		= btc_get_config('remote_site_id');
						
				//used to login
				$auth_client = new btc_soap($this->config['remote_server_url']);
				
				$array['username'] 	= $this->config['remote_server_username'];
				$array['site_id']	= $this->config['remote_server_site_id'];
				
				$connection 		= $auth_client->call('bt_soap_login', array($array));
				
				$session_id 		= $connection['session_id'];
				$session_name		= $connection['session_name'];
				
				//no longer needed
				unset($auth_client);
				
				if ($connection['success'] == 1) {
					//this is where the authenticated SOAP connection starts
					parent::__construct($this->config['remote_server_url'] . '?' . $session_name . '=' . $session_id);
					$this->is_connected = TRUE;
				}
				else {
					$this->is_connected = FALSE;
				}
			}
			else {
				$this->is_connected = FALSE;
			}
		}
		
		public function is_connected() {
			return $this->is_connected;
		}
				
		//push common
		public function push_common() {
			$common_array = btc_common();
			
			$result = $this->call('bt_soap_receive_common', array($common_array));
			
			return $result;
		}
		
		//push events
		public function push_events($events) {
			
			$result = $this->call('bt_soap_receive_events', array($events));
			
			return $result;
		}
	}

?>