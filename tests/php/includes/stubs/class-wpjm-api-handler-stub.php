<?php

class WPJM_Api_Handler_Stub {
	public $fired = false;

	public function __construct() {
		add_action( 'job_manager_api_' . strtolower( get_class( $this ) ), array( $this, 'api_handler' ) );
	}

	public function api_handler() {
		$this->fired = true;
	}
}
