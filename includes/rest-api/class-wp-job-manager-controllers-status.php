<?php
/**
 * Declaration of our Status Model
 *
 * @package WPJM/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_Controllers_Status
 */
class WP_Job_Manager_Controllers_Status extends WP_Job_Manager_REST_Controller_Model
	implements WP_Job_Manager_REST_Interfaces_Controller {


	/**
	 * Setup
	 */
	public function setup() {
		$this->add_route( '/' )
			->add_action( $this->action( 'index', 'index' ) );

		$this->add_route( '/(?P<key>[a-zA-Z_]+)' )
			->add_action( $this->action( 'show', 'show' ) )
			->add_action( $this->action( 'update', 'update' ) );
	}

	/**
	 * Index handler
	 *
	 * @param  WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function index( $request ) {
		$params = $request->get_params();
		$filter = $this->environment()
			->model( 'WP_Job_Manager_Filters_Status' )
			->new_from_array( $params );

		if ( is_wp_error( $filter ) ) {
			return $this->bad_request( $filter );
		}

		$configuration = $this->get_model_factory()
			->get_data_store()
			->get_entity( null );

		if ( empty( $configuration ) ) {
			return $this->not_found( __( 'Not Found', 'wp-job-manager' ) );
		}

		$dto = $this->prepare_dto( $configuration );
		$keys = $filter->get( 'keys' );
		if ( empty( $keys ) ) {
			return $this->ok( $dto );
		}

		$filtered_params = array();
		foreach ( $keys as $key ) {
			if ( isset( $params[ $key ] ) ) {
				$filtered_params[ $key ] = $params[ $key ];
			}
		}
		return $this->ok( $filtered_params );
	}

	/**
	 * Show handler
	 *
	 * @param  WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function show( $request ) {
		$key = $request->get_param( 'key' );
		$configuration = $this->get_model_factory()
			->get_data_store()
			->get_entity( null );

		if ( ! $configuration->has( $key ) ) {
			return $this->not_found( 'Invalid key: ' . $key );
		}

		return $configuration->get( $key );
	}

	/**
	 * Update handler
	 *
	 * @param  WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function update( $request ) {
		$key = $request->get_param( 'key' );
		$value = $request->get_param( 'value' );
		if ( empty( $value ) ) {
			if ( ! function_exists( 'json_decode' ) ) {
				include_once ABSPATH . WPINC . 'compat.php';
			}
			$body = $request->get_body();
			$value = json_decode( $body, true );
		}
		$thing_to_update = array(
		 $key => $value,
		);

		$configuration = $this->get_model_factory()
			->get_data_store()
			->get_entity( '' );
		$configuration->update_from_array( $thing_to_update );
		$result = $this->get_model_factory()
			->get_data_store()
			->upsert( $configuration );

		if ( is_wp_error( $result ) ) {
			return $this->respond( $result, 500 );
		}

		$dto = $this->prepare_dto( $configuration );

		if ( WP_REST_Server::CREATABLE === $request->get_method() ) {
			return $this->created( $dto );
		}

		return $this->ok( $dto );
	}
}

