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
	 * Associated post meta.
	 *
	 * @var string
	 */
	const META_KEY = '_promoted';

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
		add_action( 'rest_api_init', [ $this, 'rest_init' ] );
	}

	/**
	 * Loads the REST API functionality.
	 */
	public function rest_init() {
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/promoted-jobs/class-wp-job-manager-promoted-jobs-api.php';
		( new WP_Job_Manager_Promoted_Jobs_API() )->register_routes();
	}

}

WP_Job_Manager_Promoted_Jobs::instance();
