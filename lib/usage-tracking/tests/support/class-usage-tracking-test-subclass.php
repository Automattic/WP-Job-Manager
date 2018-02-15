<?php

require_once dirname( __FILE__ ) . '/../../class-usage-tracking-base.php';

/**
 * Usage Tracking subclass for testing. Please update the superclass name to
 * match the one used by your plugin (usage-tracking/class-usage-tracking-base.php).
 */
class Usage_Tracking_Test_Subclass extends WP_Job_Manager_Usage_Tracking_Base {

	const TRACKING_ENABLED_OPTION_NAME = 'testing-usage-tracking-enabled';

	public static function get_instance() {
		return self::get_instance_for_subclass( get_class() );
	}

	public function get_prefix() {
		return 'testing';
	}

	public function get_text_domain() {
		return 'text-domain';
	}

	public function get_tracking_enabled() {
		return get_option( self::TRACKING_ENABLED_OPTION_NAME ) || false;
	}

	public function set_tracking_enabled( $enable ) {
		update_option( self::TRACKING_ENABLED_OPTION_NAME, $enable );
	}

	public function current_user_can_manage_tracking() {
		return current_user_can( 'manage_usage_tracking' );
	}

	public function opt_in_dialog_text() {
		return 'Please enable Usage Tracking!';
	}
}
