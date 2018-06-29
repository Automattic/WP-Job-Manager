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
		$this->base_dir            = trailingslashit( $base_dir );
		$this->is_rest_api_enabled = defined( 'WPJM_REST_API_ENABLED' ) && ( true === constant( 'WPJM_REST_API_ENABLED' ) );
		$file                      = $this->base_dir . 'lib/wpjm_rest/class-wp-job-manager-rest-bootstrap.php';
		if ( file_exists( $file ) && $this->is_rest_api_enabled ) {
			include_once $file;
			$this->wpjm_rest_api = WP_Job_Manager_REST_Bootstrap::create();
			$this->wpjm_rest_api
				->environment()
				->get_event_dispatcher()
				->add_action( 'environment_before_start', array( $this, 'define_api' ) );
			$this->wpjm_rest_api->run();
		}
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
		$this->define_api( $this->wpjm_rest_api->environment() );
		return $this;
	}

	/**
	 * Define our REST API Models and Controllers
	 *
	 * @param WP_Job_Manager_REST_Environment $env The Environment.
	 *
	 * @throws WP_Job_Manager_REST_Exception Thrown during error while processing of request.
	 */
	public function define_api( $env ) {
		if ( ! is_a( $env, 'WP_Job_Manager_REST_Environment' ) ) {
			return;
		}

		include_once 'class-wp-job-manager-models-settings.php';
		include_once 'class-wp-job-manager-models-status.php';
		include_once 'class-wp-job-manager-filters-status.php';
		include_once 'class-wp-job-manager-data-stores-status.php';
		include_once 'class-wp-job-manager-controllers-status.php';
		include_once 'class-wp-job-manager-models-job-listings-custom-fields.php';
		include_once 'class-wp-job-manager-models-job-types-custom-fields.php';
		include_once 'class-wp-job-manager-models-job-categories-custom-fields.php';
		include_once 'class-wp-job-manager-registrable-job-listings.php';
		include_once 'class-wp-job-manager-registrable-taxonomy-type.php';
		include_once 'class-wp-job-manager-registrable-job-types.php';
		include_once 'class-wp-job-manager-registrable-job-categories.php';

		// Models.
		$env->define_model( 'WP_Job_Manager_Models_Settings' )
			->with_data_store( new WP_Job_Manager_REST_Data_Store_Option( $env->model( 'WP_Job_Manager_Models_Settings' ) ) );
		$env->define_model( 'WP_Job_Manager_Models_Status' )
			->with_data_store( new WP_Job_Manager_Data_Stores_Status( $env->model( 'WP_Job_Manager_Models_Status' ) ) );
		$env->define_model( 'WP_Job_Manager_Filters_Status' );
		$env->define_model( 'WP_Job_Manager_Models_Job_Listings_Custom_Fields' );
		$env->define_model( 'WP_Job_Manager_Models_Job_Types_Custom_Fields' );
		$env->define_model( 'WP_Job_Manager_Models_Job_Categories_Custom_Fields' );

		// Endpoints.
		$env->rest_api( 'wpjm/v1' )
			->add_endpoint( new WP_Job_Manager_REST_Controller_Settings( '/settings', 'WP_Job_Manager_Models_Settings' ) )
			->add_endpoint( new WP_Job_Manager_Controllers_Status( '/status', 'WP_Job_Manager_Models_Status' ) );
		$env->add_registrable(
			new WP_Job_Manager_Registrable_Job_Listings(
				'job_listing',
				'WP_Job_Manager_Models_Job_Listings_Custom_Fields',
				'fields'
			)
		);
		$env->add_registrable( new WP_Job_Manager_Registrable_Job_Types() );
		$env->add_registrable( new WP_Job_Manager_Registrable_Job_Categories() );
	}
}

