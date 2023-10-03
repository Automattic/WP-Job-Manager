<?php
/**
 * Uninstall file for the plugin. Runs when plugin is deleted in WordPress Admin.
 *
 * @package wp-job-manager
 */

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
} elseif ( function_exists( 'get_sites' ) ) {
	$blog_ids = get_sites(
		[
			'fields'            => 'ids',
			'update_site_cache' => false,
		]
	);

	$original_blog_id = get_current_blog_id();

	foreach ( $blog_ids as $current_blog_id ) {
		switch_to_blog( $current_blog_id );

		// Only do deletion if the setting is true.
		$do_deletion = get_option( 'job_manager_delete_data_on_uninstall' );
		if ( $do_deletion ) {
			WP_Job_Manager_Data_Cleaner::cleanup_all();
		}
	}

	switch_to_blog( $original_blog_id );
}

require dirname( __FILE__ ) . '/includes/class-wp-job-manager-usage-tracking.php';
WP_Job_Manager_Usage_Tracking::get_instance()->clear_options();
