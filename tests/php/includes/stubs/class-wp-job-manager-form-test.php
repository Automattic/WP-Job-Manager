<?php
class WP_Job_Manager_Form_Test extends WP_Job_Manager_Form {
	protected static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public static function reset() {
		self::$_instance = null;
	}

	public static function has_instance() {
		return null !== self::$_instance;
	}

	public function output( $atts = array() ) {
		echo 'success';
	}
}
