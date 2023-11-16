<?php

class WP_Job_Manager_Admin_Settings_Stub extends WP_Job_Manager_Settings {
	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function test_input_capabilities( $option, $attributes, $value ) {
		return $this->input_capabilities( $option, $attributes, $value, '' );
	}
}

