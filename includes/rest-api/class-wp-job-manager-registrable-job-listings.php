<?php
/**
 * Used for RESTifying the job_listing post type
 *
 * Adds custom fields. Needs a model definition that will provide the extra fields.
 *
 * @package WPJM/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MT_Controller_Extension
 */
class WP_Job_Manager_Registrable_Job_Listings implements WP_Job_Manager_REST_Interfaces_Registrable {

	/**
	 * Environment
	 *
	 * @var WP_Job_Manager_REST_Environment
	 */
	private $environment;

	/**
	 * Object to extend
	 *
	 * @var string
	 */
	private $object_to_extend;

	/**
	 * Model def.
	 *
	 * @var WP_Job_Manager_REST_Model_Factory
	 */
	private $model_factory;

	/**
	 * Model Definition name, This should be a valid Model definition at registration time, otherwise register throws
	 *
	 * @var string
	 */
	private $model_class;

	/**
	 * REST Field name, the field name we will nest under
	 *
	 * @var string
	 */
	private $rest_field_name;

	/**
	 * Constructor.
	 *
	 * @param string $object_to_extend Post type.
	 * @param string $model_class Model Class name.
	 * @param string $rest_field_name The REST field name.
	 */
	public function __construct( $object_to_extend, $model_class, $rest_field_name ) {
		$this->model_class      = $model_class;
		$this->object_to_extend = $object_to_extend;
		$this->rest_field_name  = $rest_field_name;
	}

	/**
	 * Register This Controller
	 *
	 * @param WP_Job_Manager_REST_Environment $environment The Environment to use.
	 * @throws WP_Job_Manager_REST_Exception Throws.
	 *
	 * @return bool|WP_Error true if valid otherwise error.
	 */
	public function register( $environment ) {
		global $wp_post_types;
		$post_type_name = $this->object_to_extend;
		if ( ! isset( $wp_post_types[ $post_type_name ] ) ) {
			return false;
		}

		if ( ! empty( $wp_post_types[ $post_type_name ]->mixtape_show_in_rest ) ) {
			return true;
		}

		// Optionally customize the rest_base or controller class.
		$wp_post_types[ $post_type_name ]->mixtape_show_in_rest  = true;
		$wp_post_types[ $post_type_name ]->show_in_rest          = true;
		$wp_post_types[ $post_type_name ]->rest_base             = 'job-listings';
		$wp_post_types[ $post_type_name ]->rest_controller_class = 'WP_REST_Posts_Controller';

		$this->environment   = $environment;
		$this->model_factory = $this->environment->model( $this->model_class );
		if ( ! $this->model_factory ) {
			return new WP_Error( 'model-not-found' );
		}
		register_rest_field(
			$this->object_to_extend,
			$this->rest_field_name,
			array(
				'get_callback'    => array( $this, 'get_fields' ),
				'update_callback' => array( $this, 'update_fields' ),
				'schema'          => $this->get_item_schema(),
			)
		);

		return true;
	}

	/**
	 * Get Item Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$fields     = $this->model_factory->get_fields();
		$properties = array();
		$required   = array();
		foreach ( $fields as $field_declaration ) {
			/**
			 * Our declaration
			 *
			 * @var WP_Job_Manager_REST_Field_Declaration $field_declaration
			 */
			$properties[ $field_declaration->get_data_transfer_name() ] = $field_declaration->as_item_schema_property();
			if ( $field_declaration->is_required() ) {
				$required[] = $field_declaration->get_data_transfer_name();
			}
		}
		$schema = array(
			'$schema'    => 'http://json-schema.org/schema#',
			'title'      => $this->model_factory->get_name(),
			'type'       => 'object',
			'properties' => (array) apply_filters( 'mixtape_rest_api_schema_properties', $properties, $this->model_factory ),
		);

		if ( ! empty( $required ) ) {
			$schema['required'] = $required;
		}

		return $schema;
	}

	/**
	 * Our Get Fields.
	 *
	 * @param array           $object Object.
	 * @param string          $field_name Field Name.
	 * @param WP_REST_Request $request Request.
	 * @param string          $object_type Object Type.
	 *
	 * @return mixed|string
	 * @throws WP_Job_Manager_REST_Exception If type not there.
	 */
	public function get_fields( $object, $field_name, $request, $object_type ) {
		if ( 'job_listing' !== $object_type ) {
			return null;
		}

		if ( $this->rest_field_name !== $field_name ) {
			return null;
		}

		$object_id = absint( $object['id'] );
		$model     = $this->get_model( $object_id );
		return $model->to_dto();
	}

	/**
	 * Get a model if exists
	 *
	 * @param int $object_id Object ID.
	 * @return WP_Job_Manager_REST_Interfaces_Model
	 */
	private function get_model( $object_id ) {
		$data = array();
		foreach ( $this->model_factory->get_fields() as $field_declaration ) {
			$field_name = $field_declaration->get_name();
			if ( metadata_exists( 'post', $object_id, $field_name ) ) {
				$meta                = get_post_meta( $object_id, $field_name, true );
				$data[ $field_name ] = $meta;
			}
		}

		return $this->model_factory->create(
			$data,
			array(
				'deserialize' => true,
			)
		);
	}

	/**
	 * Our Reader.
	 *
	 * @param mixed           $data Data.
	 * @param array|object    $object Object.
	 * @param string          $field_name Field Name.
	 * @param WP_REST_Request $request Request.
	 * @param string          $object_type Object Type.
	 *
	 * @return mixed|string
	 * @throws WP_Job_Manager_REST_Exception If type not there.
	 */
	public function update_fields( $data, $object, $field_name, $request, $object_type ) {
		if ( 'job_listing' !== $object_type ) {
			return null;
		}

		if ( $this->rest_field_name !== $field_name ) {
			return null;
		}

		$object_id      = absint( $object->ID );
		$existing_model = $this->get_model( $object_id );

		$updated = $existing_model->update_from_array( $data );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$maybe_validation_error = $updated->validate();
		if ( is_wp_error( $maybe_validation_error ) ) {
			return $maybe_validation_error;
		}

		$serialized_data = $updated->serialize( WP_Job_Manager_REST_Field_Declaration::META );

		foreach ( $serialized_data as $field_name => $val ) {
			if ( metadata_exists( 'post', $object_id, $field_name ) ) {
				update_post_meta( $object_id, $field_name, $val );
			} else {
				add_post_meta( $object_id, $field_name, $val );
			}
		}

		return true;
	}
}
