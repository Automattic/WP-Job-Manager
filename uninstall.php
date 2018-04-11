<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

// Cleanup all data.
require 'includes/class-wp-job-manager-data-cleaner.php';

if ( ! is_multisite() ) {
	WP_Job_Manager_Data_Cleaner::cleanup_all();
} else {
	global $wpdb;

	$blog_ids         = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
	$original_blog_id = get_current_blog_id();

	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( $blog_id );
		WP_Job_Manager_Data_Cleaner::cleanup_all();
	}

	switch_to_blog( $original_blog_id );
}

include dirname( __FILE__ ) . '/includes/class-wp-job-manager-usage-tracking.php';
WP_Job_Manager_Usage_Tracking::get_instance()->clear_options();
