<?php
/**
 * Declaration of our Configuration Model
 *
 * @package WPJM/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_Controllers_Configuration
 */
class WP_Job_Manager_Controllers_Configuration extends WPJM_REST_Controller_Model
	implements WPJM_REST_Interfaces_Controller {


	/**
	 * Setup
	 */
	function setup() {
		$this->add_route( '/' )
			->handler( 'index', 'index' );

		$this->add_route( '/(?P<key>[a-zA-Z_]+)' )
			->handler( 'show', 'show' )
			->handler( 'update', 'update' );
	}

	/**
	 * Index handler
	 *
	 * @param  WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	function index( $request ) {
		$filter = $this->environment()
			->model( 'WP_Job_Manager_Filters_Configuration' )
			->new_from_array( $request->get_params() );

		if ( is_wp_error( $filter ) ) {
			$this->bad_request( $filter );
		}

		$configuration = $this->get_model_definition()
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
	function show( $request ) {
		$key = $request->get_param( 'key' );
		$configuration = $this->get_model_definition()
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
	function update( $request ) {
		$key = $request->get_param( 'key' );
		$value = $request->get_param( 'value' );
		if ( empty( $value ) ) {
			$value = json_decode( $request->get_body(), true );
		}
		$thing_to_update = array(
		 $key => $value,
		);

		$configuration = $this->get_model_definition()
			->get_data_store()
			->get_entity( '' );
		$this->get_model_definition()
			->update_model_from_array( $configuration, $thing_to_update );
		$result = $this->get_model_definition()
			->get_data_store()
			->upsert( $configuration );

		if ( is_wp_error( $result ) ) {
			   return $this->respond( $result, 500 );
		}

		$dto = $this->prepare_dto( $configuration );

		return WP_REST_Server::CREATABLE === $request->get_method() ?
		 $this->created( $dto ) :
		 $this->ok( $dto );
	}
}

