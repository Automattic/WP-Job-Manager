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
	 * @var WPJM_REST_Bootstrap
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
	function __construct( $base_dir ) {
		$this->base_dir = trailingslashit( $base_dir );
		$this->is_rest_api_enabled = defined( 'WPJM_REST_API_ENABLED' ) && ( true === constant( 'WPJM_REST_API_ENABLED' ) );
	}

	/**
	 * Bootstrap our REST Api
	 */
	private function bootstrap() {
		include_once $this->base_dir . 'lib/wpjm_rest/class-wpjm-rest-bootstrap.php';

		$this->wpjm_rest_api = WPJM_REST_Bootstrap::create()->load();

		include_once 'class-wp-job-manager-models-settings.php';
		include_once 'class-wp-job-manager-models-configuration.php';
		include_once 'class-wp-job-manager-filters-configuration.php';
		include_once 'class-wp-job-manager-data-stores-configuration.php';
		include_once 'class-wp-job-manager-controllers-configuration.php';
		include_once 'class-wp-job-manager-permissions-any.php';
	}

	/**
	 * Get WPJM_REST_Bootstrap
	 *
	 * @return WPJM_REST_Bootstrap
	 */
	function get_bootstrap() {
		return $this->wpjm_rest_api;
	}

	/**
	 * Initialize the REST API
	 *
	 * @return WP_Job_Manager_REST_API $this
	 */
	function init() {
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
	 * @param WPJM_REST_Environment $env The Environment.
	 */
	function define_api( $env ) {
		// Models.
		$env->define_model( 'WP_Job_Manager_Models_Settings' )
			->with_data_store( $env->data_store( 'WPJM_REST_Data_Store_Option' ) );
		$env->define_model( 'WP_Job_Manager_Models_Configuration' )
			->with_data_store( $env->data_store( 'WP_Job_Manager_Data_Stores_Configuration' ) );
		$env->define_model( 'WP_Job_Manager_Filters_Configuration' )
			->with_permissions_provider( new WP_Job_Manager_Permissions_Any() );

		// Endpoints.
		$wpjm_v1 = $env->rest_api( 'wpjm/v1' );
		$wpjm_v1->endpoint()
			->for_model( $env->model( 'WP_Job_Manager_Models_Settings' ) )
			->with_base( '/settings' )
			->with_class( 'WPJM_REST_Controller_Settings' );
		$wpjm_v1->endpoint()
			->for_model( $env->model( 'WP_Job_Manager_Models_Configuration' ) )
			->with_base( '/configuration' )
			->with_class( 'WP_Job_Manager_Controllers_Configuration' );
	}
}

