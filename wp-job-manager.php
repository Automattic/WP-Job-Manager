<?php
/**
 * Plugin Name: WP Job Manager
 * Plugin URI: https://wpjobmanager.com/
 * Description: Manage job listings from the WordPress admin panel, and allow users to post jobs directly to your site.
 * Version: 1.32.3
 * Author: Automattic
 * Author URI: https://wpjobmanager.com/
 * Requires at least: 4.7.0
 * Tested up to: 5.1
 * Text Domain: wp-job-manager
 * Domain Path: /languages/
 * License: GPL2+
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'JOB_MANAGER_VERSION', '1.32.3' );
define( 'JOB_MANAGER_MINIMUM_WP_VERSION', '4.7.0' );
define( 'JOB_MANAGER_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'JOB_MANAGER_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'JOB_MANAGER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once dirname( __FILE__ ) . '/includes/class-wp-job-manager.php';

/**
 * Add post type for Job Manager.
 *
 * @param array $types
 * @return array
 */
function job_manager_add_post_types( $types ) {
	$types[] = 'job_listing';
	return $types;
}
add_filter( 'post_types_to_delete_with_user', 'job_manager_add_post_types', 10 );

/**
 * Main instance of WP Job Manager.
 *
 * Returns the main instance of WP Job Manager to prevent the need to use globals.
 *
 * @since  1.26
 * @return WP_Job_Manager
 */
function WPJM() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
	return WP_Job_Manager::instance();
}

$GLOBALS['job_manager'] = WPJM();
