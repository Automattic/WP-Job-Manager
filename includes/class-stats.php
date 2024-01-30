<?php
/**
 * File containing the class WP_Job_Manager_Stats
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is responsible for initializing all aspects of stats for wpjm.
 */
class Stats {
	use Singleton;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Do initialization of all the things needed for stats.
	 */
	public function init() {
		$this->initialize_wpdb();
	}

	/**
	 * Initialize the alias for the stats table on the wpdb object.
	 *
	 * @return void
	 */
	private function initialize_wpdb() {
		global $wpdb;
		$wpdb->job_manager_stats = $wpdb->prefix . 'job_manager_stats';
		$wpdb->tables[]          = 'job_manager_stats';
	}

	/**
	 * Perform plugin activation-related stats actions.
	 *
	 * @return void
	 */
	public function activate() {
	}
}
