<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include dirname( __FILE__ ) . '/../lib/usage-tracking/class-usage-tracking-base.php';

/**
 * WPJM Usage Tracking subclass.
 **/
class WP_Job_Manager_Usage_Tracking extends WP_Job_Manager_Usage_Tracking_Base {

	const WPJM_TRACKING_INFO_URL = 'https://wpjobmanager.com/document/what-data-does-wpjm-track';

	/*
	 * Implementation for abstract functions.
	 */

	public static function get_instance() {
		return self::get_instance_for_subclass( get_class() );
	}

	protected function get_prefix() {
		return 'wpjm';
	}

	protected function get_tracking_enabled() {
		// TODO
	}

	protected function set_tracking_enabled( $enable ) {
		// TODO
	}

	protected function current_user_can_manage_tracking() {
		return current_user_can( 'manage_options' );
	}

	protected function opt_in_dialog_text() {
		return sprintf( __( "We'd love if you helped us make WP Job Manager better by allowing us to collect
			<a href=\"%s\" target=\"_blank\">usage tracking data</a>.
			No sensitive information is collected, and you can opt out at any time.",
			'wp-job-manager' ), self::WPJM_TRACKING_INFO_URL );
	}
}
