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
		$file = $this->base_dir . 'lib/wpjm_rest/class-wp-job-manager-rest-bootstrap.php';
		if ( ! file_exists( $file ) ) {
			return new WP_Error( 'mixtape-missing' );
		}

		include_once $file;

		$this->wpjm_rest_api = WP_Job_Manager_REST_Bootstrap::create();
		if ( empty( $this->wpjm_rest_api ) ) {
			return new WP_Error( 'rest-api-bootstrap-failed' );
		}
		$this->wpjm_rest_api->load();

		include_once 'class-wp-job-manager-models-settings.php';
		include_once 'class-wp-job-manager-models-status.php';
		include_once 'class-wp-job-manager-filters-status.php';
		include_once 'class-wp-job-manager-data-stores-status.php';
		include_once 'class-wp-job-manager-controllers-status.php';
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
		$err = $this->bootstrap();
		if ( is_wp_error( $err ) ) {
			// Silently don't initialize the rest api if we get a wp_error.
			return $this;
		}
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
			->with_data_store( new WP_Job_Manager_REST_Data_Store_Option( $env->model( 'WP_Job_Manager_Models_Settings' ) ) );
		$env->define_model( 'WP_Job_Manager_Models_Status' )
			->with_data_store( new WP_Job_Manager_Data_Stores_Status( $env->model( 'WP_Job_Manager_Models_Status' ) ) );
		$env->define_model( 'WP_Job_Manager_Filters_Status' );

		// Endpoints.
		$env->rest_api( 'wpjm/v1' )
			->add_endpoint( new WP_Job_Manager_REST_Controller_Settings( '/settings', 'WP_Job_Manager_Models_Settings' ) )
			->add_endpoint( new WP_Job_Manager_Controllers_Status( '/status', 'WP_Job_Manager_Models_Status' ) );
	}
}

