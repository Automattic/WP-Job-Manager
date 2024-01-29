<?php
/**
 * File containing the class WP_Job_Manager_Stats
 *
 * @package wp-job-manager
 */

use WP_Job_Manager\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is responsible for initializing all aspects of stats for wpjm.
 */
class WP_Job_Manager_Stats {
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
		// Do init stuff.
	}
}
