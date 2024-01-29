<?php
/**
 * File containing the class WP_Job_Manager_Stats
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is responsible for initializing all aspects of stats for wpjm.
 */
class WP_Job_Manager_Stats {
	/**
	 * Holds the one instance of stats.
	 *
	 * @var null|WP_Job_Manager_Stats
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Get the instance.
	 *
	 * @return WP_Job_Manager_Stats
	 */
	public static function instance(): WP_Job_Manager_Stats {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Do initialization of all the things needed for stats.
	 */
	public function init() {
		// Do init stuff.
	}
}
