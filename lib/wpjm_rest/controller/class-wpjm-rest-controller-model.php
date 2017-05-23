<?php

/**
 * Class WPJM_REST_Controller_Model
 * Knows about models
 */
class WPJM_REST_Controller_Model extends WPJM_REST_Controller {
	/**
	 * @var WPJM_REST_Model_Definition
	 */
	protected $model_definition;
	/**
	 * @var WPJM_REST_Model_Declaration
	 */
	protected $model_declaration;
	/**
	 * @var WPJM_REST_Interfaces_Data_Store
	 */
	protected $model_data_store;

	/**
	 * Mixtape_Rest_Api_Controller_CRUD constructor.
	 *
	 * @param WPJM_REST_Controller_Bundle $controller_bundle
	 * @param WPJM_REST_Model_Definition           $model_definition
	 */
	public function __construct( $controller_bundle, $base, $model_definition ) {
		$this->base = $base;
		$environment = $model_definition->environment();
		parent::__construct( $controller_bundle, $environment );
		$this->model_definition = $model_definition;
		$this->model_declaration = $this->model_definition->get_model_declaration();
		$this->model_data_store = $this->model_definition->get_data_store();
	}


	protected function get_model_definition() {
		return $this->model_definition;
	}

	/**
	 * Retrieves the item's schema, conforming to JSON Schema.
	 *
	 * @access public
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		$model_definition = $this->get_model_definition();
		$fields = $model_definition->get_field_declarations();
		$properties = array();
		$required = array();
		foreach ( $fields as $field_declaration ) {
			/** @var WPJM_REST_Model_Field_Declaration $field_declaration */
			$properties[ $field_declaration->get_data_transfer_name() ] = $field_declaration->as_item_schema_property();
			if ( $field_declaration->is_required() ) {
				$required[] = $field_declaration->get_data_transfer_name();
			}
		}
		$schema = array(
			'$schema' => 'http://json-schema.org/schema#',
			'title' => $model_definition->get_name(),
			'type' => 'object',
			'properties' => (array) apply_filters( 'mixtape_rest_api_schema_properties', $properties, $this->get_model_definition() ),
		);

		if ( ! empty( $required ) ) {
			$schema['required'] = $required;
		}

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * @return WPJM_REST_Model_Declaration
	 */
	protected function get_model_declaration() {
		return $this->model_declaration;
	}

	/**
	 * @return WPJM_REST_Interfaces_Data_Store
	 */
	protected function get_model_data_store() {
		return $this->model_data_store;
	}

	/**
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function get_items_permissions_check( $request ) {
		return $this->permissions_check( $request, 'index' );
	}

	/**
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function get_item_permissions_check( $request ) {
		return $this->permissions_check( $request, 'show' );
	}

	/**
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function create_item_permissions_check( $request ) {
		return $this->permissions_check( $request, 'create' );
	}

	/**
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function update_item_permissions_check( $request ) {
		return $this->permissions_check( $request, 'update' );
	}

	/**
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->permissions_check( $request, 'delete' );
	}

	/**
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	private function permissions_check( $request, $action ) {
		return $this->get_model_definition()->permissions_check( $request, $action );
	}

	/**
	 * @param array|WPJM_REST_Model_Collection|WPJM_REST_Model $entity
	 * @return array
	 */
	protected function prepare_dto( $entity ) {
		if ( is_array( $entity ) ) {
			return $entity;
		}

		if ( is_a( $entity, 'WPJM_REST_Model_Collection') ) {
			$results = array();
			foreach ( $entity->get_items() as $model ) {
				$results[] = $this->model_to_dto( $model );
			}
			return $results;
		}

		if ( is_a( $entity, 'WPJM_REST_Model') ) {
			return $this->model_to_dto( $entity );
		}

		return $entity;
	}

	/**
	 * @param WPJM_REST_Model $model
	 * @return array
	 */
	protected function model_to_dto( $model ) {
		return $this->get_model_definition()->model_to_dto( $model );
	}

	/**
	 * Prepare the item for create or update operation.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_Error|object $prepared_item
	 */
	protected function prepare_item_for_database( $request ) {
		return $this->get_model_definition()->new_from_request( $request );
	}

	protected function get_base_url() {
		return rest_url( $this->controller_bundle->get_bundle_prefix(), $this->base );
	}
}
