<?php
class WP_Job_Manager_Form_Test extends WP_Job_Manager_Form {
	protected static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function reset() {
		self::$instance = null;
	}

	public static function has_instance() {
		return null !== self::$instance;
	}

	public function output( $atts = [] ) {
		echo 'success';
	}
}
