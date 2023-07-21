<?php
/**
 * File containing the class WP_Job_Manager_Promoted_Jobs.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles promoted jobs functionality.
 *
 * @since $$next-version$$
 */
class WP_Job_Manager_Promoted_Jobs {

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  $$next-version$$
	 */
	private static $instance = null;

	/**
	 * Name for post meta that identify a `job_listing` post as promoted.
	 *
	 * @var string
	 */
	const PROMOTED_META_KEY = '_promoted';

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  $$next-version$$
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'include_dependencies' ] );
		add_action( 'rest_api_init', [ $this, 'rest_init' ] );
	}

	/**
	 * Includes promoted jobs dependencies.
	 *
	 * @access private
	 * @return void
	 */
	public function include_dependencies() {
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/promoted-jobs/class-wp-job-manager-promoted-jobs-api.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/promoted-jobs/class-wp-job-manager-promoted-jobs-notifications.php';
	}

	/**
	 * Loads the REST API functionality.
	 */
	public function rest_init() {
		( new WP_Job_Manager_Promoted_Jobs_API() )->register_routes();
	}

}

WP_Job_Manager_Promoted_Jobs::instance();
