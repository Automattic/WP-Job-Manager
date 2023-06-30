<?php
/**
 * Plugin Name: WP Job Manager
 * Plugin URI: https://wpjobmanager.com/
 * Description: Manage job listings from the WordPress admin panel, and allow users to post jobs directly to your site.
 * Version: 1.40.2
 * Author: Automattic
 * Author URI: https://wpjobmanager.com/
 * Requires at least: 6.0
 * Tested up to: 6.2
 * Requires PHP: 7.4
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
define( 'JOB_MANAGER_VERSION', '1.40.2' );
define( 'JOB_MANAGER_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'JOB_MANAGER_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'JOB_MANAGER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

spl_autoload_register( 'wpjm_autoload_namespaced' );

require_once dirname( __FILE__ ) . '/includes/class-wp-job-manager-dependency-checker.php';
if ( ! WP_Job_Manager_Dependency_Checker::check_dependencies() ) {
	return;
}

require_once dirname( __FILE__ ) . '/includes/class-wp-job-manager.php';

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

// Activation - works with symlinks.
register_activation_hook( basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ), array( WPJM(), 'activate' ) );

// Cleanup on deactivation.
register_deactivation_hook( __FILE__, array( WPJM(), 'unschedule_cron_jobs' ) );
register_deactivation_hook( __FILE__, array( WPJM(), 'usage_tracking_cleanup' ) );

/**
 * Autoloader for new classes.
 *
 * @param string $class Fully qualified class name.
 *
 * @return void
 */
function wpjm_autoload_namespaced( $class ) {
	// Only load WPJM namespaced classes here.
	if ( ! str_starts_with( $class, 'WP_Job_Manager\\' ) ) {
		return;
	}

	$base_paths = array(
		'WP_Job_Manager\\Blocks' => 'blocks',
	);

	$prefix    = 'WP_Job_Manager';
	$base_path = 'includes';
	foreach ( $base_paths as $namespace => $path ) {
		if ( str_starts_with( $class, $namespace ) ) {
			$base_path = $path;
			$prefix    = $namespace;
			break;
		}
	}

	$file = str_replace( $prefix . '\\', '', $class );
	// Namespace to directory.
	$file = str_replace( '\\', '/', $file );
	// Underscore to dash.
	$file = str_replace( '_', '-', $file );
	// Uppercase boundary to dash.
	$file = strtolower( preg_replace( '/\B([A-Z])/', '-$1', $file ) );

	$file = JOB_MANAGER_PLUGIN_DIR . '/' . $base_path . '/' . $file . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
}
