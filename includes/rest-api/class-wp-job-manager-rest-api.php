<?php
/**
 * The REST API Initializer
 *
 * @package WPJM/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_REST_API
 */
class WP_Job_Manager_REST_API {

	/**
	 * Is the api enabled?
	 *
	 * @var bool
	 */
	private $is_rest_api_enabled;
	/**
	 * Our bootstrap
	 *
	 * @var WP_Job_Manager_REST_Bootstrap
	 */
	private $wpjm_rest_api;
	/**
	 * The plugin base dir
	 *
	 * @var string
	 */
	private $base_dir;

	/**
	 * WP_Job_Manager_REST_API constructor.
	 *
	 * @param string $base_dir The base dir.
	 */
	public function __construct( $base_dir ) {
		$this->base_dir = trailingslashit( $base_dir );
		$this->is_rest_api_enabled = defined( 'WPJM_REST_API_ENABLED' ) && ( true === constant( 'WPJM_REST_API_ENABLED' ) );
	}

	/**
	 * Bootstrap our REST Api
	 */
	private function bootstrap() {
		include_once $this->base_dir . 'lib/wpjm_rest/class-wp-job-manager-rest-bootstrap.php';

		$this->wpjm_rest_api = WP_Job_Manager_REST_Bootstrap::create()->load();

		include_once 'class-wp-job-manager-models-settings.php';
		include_once 'class-wp-job-manager-models-status.php';
		include_once 'class-wp-job-manager-filters-status.php';
		include_once 'class-wp-job-manager-data-stores-status.php';
		include_once 'class-wp-job-manager-controllers-status.php';
		include_once 'class-wp-job-manager-permissions-any.php';
	}

	/**
	 * Get WP_Job_Manager_REST_Bootstrap
	 *
	 * @return WP_Job_Manager_REST_Bootstrap
	 */
	public function get_bootstrap() {
		return $this->wpjm_rest_api;
	}

	/**
	 * Initialize the REST API
	 *
	 * @return WP_Job_Manager_REST_API $this
	 */
	public function init() {
		if ( ! $this->is_rest_api_enabled ) {
			return $this;
		}
		$this->bootstrap();
		$this->define_api( $this->wpjm_rest_api->environment() );
		$this->wpjm_rest_api->environment()
			->start();
		return $this;
	}

	/**
	 * Define our REST API Models and Controllers
	 *
	 * @param WP_Job_Manager_REST_Environment $env The Environment.
	 */
	public function define_api( $env ) {
		// Models.
		$env->define_model( 'WP_Job_Manager_Models_Settings' )
			->with_data_store( $env->data_store( 'WP_Job_Manager_REST_Data_Store_Option' ) );
		$env->define_model( 'WP_Job_Manager_Models_Status' )
			->with_data_store( $env->data_store( 'WP_Job_Manager_Data_Stores_Status' ) );
		$env->define_model( 'WP_Job_Manager_Filters_Status' )
			->with_permissions_provider( new WP_Job_Manager_Permissions_Any() );

		// Endpoints.
		$wpjm_v1 = $env->rest_api( 'wpjm/v1' );
		$wpjm_v1->endpoint()
			->for_model( $env->model( 'WP_Job_Manager_Models_Settings' ) )
			->with_base( '/settings' )
			->with_class( 'WP_Job_Manager_REST_Controller_Settings' );
		$wpjm_v1->endpoint()
			->for_model( $env->model( 'WP_Job_Manager_Models_Status' ) )
			->with_base( '/status' )
			->with_class( 'WP_Job_Manager_Controllers_Status' );
	}
}

