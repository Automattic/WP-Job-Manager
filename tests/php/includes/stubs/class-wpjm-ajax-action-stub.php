<?php

class WPJM_Ajax_Action_Stub {
	public $action = 'awesome_action';
	public $fired = false;

	public function __construct() {
		add_action( 'job_manager_ajax_' . $this->action, array( $this, 'ajax_handler' ) );
	}

	public function ajax_handler() {
		$this->fired = true;
	}
}
