<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

wp_clear_scheduled_hook( 'job_manager_delete_old_previews' );
wp_clear_scheduled_hook( 'job_manager_check_for_expired_jobs' );

wp_trash_post( get_option( 'job_manager_submit_job_form_page_id' ) );
wp_trash_post( get_option( 'job_manager_job_dashboard_page_id' ) );
wp_trash_post( get_option( 'job_manager_jobs_page_id' ) );

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