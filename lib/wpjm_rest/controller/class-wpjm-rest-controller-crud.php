<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // End if().

class WPJM_REST_Controller_CRUD extends WPJM_REST_Controller_Model {

	public function register() {
		$prefix = $this->controller_bundle->get_bundle_prefix();

		register_rest_route( $prefix, $this->base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			),
			array(
				'methods'         => WP_REST_Server::CREATABLE,
				'callback'        => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'            => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
			),
		) );
		register_rest_route( $prefix,  $this->base . '/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			),
			array(
				'methods'         => WP_REST_Server::EDITABLE,
				'callback'        => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'            => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			array(
				'methods'         => WP_REST_Server::DELETABLE,
				'callback'        => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'            => $this->get_endpoint_args_for_item_schema( WP_REST_Server::DELETABLE ),
			),
		) );
	}

	public function get_items( $request ) {
		$item_id = isset( $request['id'] ) ? absint( $request['id'] ) : null;

		if ( null === $item_id ) {
			$models = $this->get_model_data_store()->get_entities();
			$data = $this->prepare_dto( $models );
			return $this->succeed( $data );
		}

		$model = $this->model_definition->get_data_store()->get_entity( $item_id );
		if ( empty( $model ) ) {
			return $this->not_found( __( 'Model not found' ) );
		}

		return $this->succeed( $this->prepare_dto( $model ) );
	}

	public function get_item( $request ) {
		return $this->get_items( $request );
	}


	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function create_item( $request ) {
		$is_update = false;
		return $this->create_or_update( $request, $is_update );
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function update_item( $request ) {
		$is_update = true;
		return $this->create_or_update( $request, $is_update );
	}

	/**
	 * @param WP_REST_Request $request
	 * @param bool            $is_update
	 * @return WP_REST_Response
	 */
	protected function create_or_update( $request, $is_update = false ) {
		$model_to_update = null;
		if ( $is_update ) {
			$id = isset( $request['id'] ) ? absint( $request['id'] ) : null;
			if ( ! empty( $id ) ) {
				$model_to_update = $this->model_definition->get_data_store()->get_entity( $id );
				if ( empty( $model_to_update ) ) {
					return $this->not_found( 'Model does not exist' );
				}
			}
		}

		if ( $is_update && $model_to_update ) {
			$model = $this->model_definition->merge_updates_from_request( $model_to_update, $request, $is_update );
		} else {
			$model = $model = $this->get_model_definition()->new_from_request( $request );
		}

		if ( is_wp_error( $model ) ) {
			$wp_err = $model;
			return $this->fail_with( $wp_err );
		}

		$validation = $model->validate();
		if ( is_wp_error( $validation ) ) {
			return $this->fail_with( $validation );
		}

		$id_or_error = $this->model_data_store->upsert( $model );

		if ( is_wp_error( $id_or_error ) ) {
			return $this->fail_with( $id_or_error );
		}

		$dto = $this->prepare_dto( array(
			'id' => absint( $id_or_error ),
		) );

		return $is_update ? $this->succeed( $dto ) : $this->created( $dto );
	}

	public function delete_item( $request ) {
		$id = isset( $request['id'] ) ? absint( $request['id'] ) : null;
		if ( empty( $id ) ) {
			return $this->fail_with( 'No Model ID provided' );
		}
		$model = $this->model_definition->get_data_store()->get_entity( $id );
		if ( null === $model ) {
			return $this->not_found( 'Model does not exist' );
		}
		$result = $this->model_data_store->delete( $model );
		return $this->succeed( $result );
	}

	/**
	 * @param WPJM_REST_Model $model
	 * @return array
	 */
	protected function model_to_dto( $model ) {
		$result = parent::model_to_dto( $model );
		$result['_links'] = $this->add_links( $model );
		return $result;
	}

	/**
	 * @param WPJM_REST_Model $model
	 * @return array
	 */
	protected function add_links( $model ) {
		$base_url = rest_url() . $this->controller_bundle->get_bundle_prefix() . $this->base . '/';

		$result = array(
			'collection' => array(
				array(
					'href' => esc_url( $base_url ),
				),
			),
		);
		if ( $model->get_id() ) {
			$result['self'] = array(
				array(
					'href' => esc_url( $base_url . $model->get_id() ),
				),
			);
		}
		if ( $model->has( 'author' ) ) {
			$result['author'] = array(
				array(
					'href' => esc_url( rest_url() . 'wp/v2/users/' . $model->get( 'author' ) ),
				),
			);
		}
		return $result;
	}
}
