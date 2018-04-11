<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

// Cleanup all data.
require 'includes/class-wp-job-manager-data-cleaner.php';

if ( ! is_multisite() ) {

	// Only do deletion if the setting is true.
	$do_deletion = get_option( 'job_manager_delete_data_on_uninstall' );
	if ( $do_deletion ) {
		WP_Job_Manager_Data_Cleaner::cleanup_all();
	}
} else {
	global $wpdb;

	$blog_ids         = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
	$original_blog_id = get_current_blog_id();

	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( $blog_id );

		// Only do deletion if the setting is true.
		$do_deletion = get_option( 'job_manager_delete_data_on_uninstall' );
		if ( $do_deletion ) {
			WP_Job_Manager_Data_Cleaner::cleanup_all();
		}
	}

	switch_to_blog( $original_blog_id );
}

wp_clear_scheduled_hook( 'job_manager_delete_old_previews' );
wp_clear_scheduled_hook( 'job_manager_check_for_expired_jobs' );

$options = array(
	'wp_job_manager_version',
	'job_manager_per_page',
	'job_manager_hide_filled_positions',
	'job_manager_enable_categories',
	'job_manager_enable_default_category_multiselect',
	'job_manager_category_filter_type',
	'job_manager_user_requires_account',
	'job_manager_enable_registration',
	'job_manager_registration_role',
	'job_manager_submission_requires_approval',
	'job_manager_user_can_edit_pending_submissions',
	'job_manager_submission_duration',
	'job_manager_allowed_application_method',
	'job_manager_submit_job_form_page_id',
	'job_manager_job_dashboard_page_id',
	'job_manager_jobs_page_id',
	'job_manager_installed_terms',
	'job_manager_submit_page_slug',
	'job_manager_job_dashboard_page_slug',
	'job_manager_google_maps_api_key',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

include dirname( __FILE__ ) . '/includes/class-wp-job-manager-usage-tracking.php';
WP_Job_Manager_Usage_Tracking::get_instance()->clear_options();
