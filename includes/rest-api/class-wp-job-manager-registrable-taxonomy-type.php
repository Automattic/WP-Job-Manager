<?php
/**
 * Exposes Taxonomy REST Api
 *
 * @package WPJM/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_Registrable_Taxonomy_Type
 */
abstract class WP_Job_Manager_Registrable_Taxonomy_Type implements WP_Job_Manager_REST_Interfaces_Registrable {

	/**
	 * The Model Prototype
	 *
	 * @var WP_Job_Manager_REST_Model
	 */
	private $model_prototype;

	/**
	 * REST Field Name
	 *
	 * @var string
	 */
	private $rest_field_name;

	/**
	 * Gets the taxonomy type to register.
	 *
	 * @return string Taxonomy type to expose.
	 */
	abstract public function get_taxonomy_type();

	/**
	 * Gets the REST API base slug.
	 *
	 * @return string Slug for REST API base.
	 */
	abstract public function get_rest_base();

	/**
	 * Gets the REST API model class name.
	 *
	 * @return string Class name for the taxonomy type's model.
	 */
	abstract public function get_model_class_name();

	/**
	 * Register Job Categories
	 *
	 * @param WP_Job_Manager_REST_Environment $environment The Environment to use.
	 * @throws WP_Job_Manager_REST_Exception Throws.
	 *
	 * @return bool|WP_Error true if valid otherwise error.
	 */
	public function register( $environment ) {
		global $wp_taxonomies;

		$taxonomy_type         = $this->get_taxonomy_type();
		$this->rest_field_name = 'fields';

		if ( ! isset( $wp_taxonomies[ $taxonomy_type ] ) ) {
			return false;
		}

		if ( ! empty( $wp_taxonomies[ $taxonomy_type ]->mixtape_show_in_rest ) ) {
			return true;
		}

		$wp_taxonomies[ $taxonomy_type ]->mixtape_show_in_rest = true;
		$wp_taxonomies[ $taxonomy_type ]->show_in_rest         = true;
		$wp_taxonomies[ $taxonomy_type ]->rest_base            = $this->get_rest_base();

		$this->model_prototype = $environment->model( $this->get_model_class_name() );

		if ( ! $this->model_prototype ) {
			return new WP_Error( 'model-not-found' );
		}
		register_rest_field(
			$taxonomy_type,
			$this->rest_field_name,
			array(
				'get_callback'    => array( $this, 'get_taxonomy_term' ),
				'update_callback' => array( $this, 'update_taxonomy_term' ),
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
		$fields     = $this->model_prototype->get_fields();
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
			'title'      => $this->model_prototype->get_name(),
			'type'       => 'object',
			'properties' => (array) apply_filters( 'mixtape_rest_api_schema_properties', $properties, $this->model_prototype ),
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
	public function get_taxonomy_term( $object, $field_name, $request, $object_type ) {
		if ( $this->get_taxonomy_type() !== $object_type ) {
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
	 * @throws WP_Job_Manager_REST_Exception On Error.
	 */
	private function get_model( $object_id ) {
		$data = array();
		foreach ( $this->model_prototype->get_fields( WP_Job_Manager_REST_Field_Declaration::META ) as $field_declaration ) {
			$field_name = $field_declaration->get_name();
			if ( metadata_exists( 'term', $object_id, $field_name ) ) {
				$meta                = get_term_meta( $object_id, $field_name, true );
				$data[ $field_name ] = $meta;
			}
		}

		return $this->model_prototype->create(
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
	 * @param object          $object Object.
	 * @param string          $field_name Field Name.
	 * @param WP_REST_Request $request Request.
	 * @param string          $object_type Object Type.
	 *
	 * @return mixed|string
	 * @throws WP_Job_Manager_REST_Exception If type not there.
	 */
	public function update_taxonomy_term( $data, $object, $field_name, $request, $object_type ) {
		if ( $this->get_taxonomy_type() !== $object_type ) {
			return null;
		}

		if ( $this->rest_field_name !== $field_name ) {
			return null;
		}

		if ( ! is_a( $object, 'WP_Term' ) ) {
			return null;
		}

		$rest_base = $this->get_rest_base();
		$term_id   = absint( $object->term_id );
		if ( ! $term_id ) {
			// No way to update this. Bail.
			return new WP_Error(
				$rest_base . '-error-invalid-id', $rest_base . '-error-invalid-id',
				array(
					'status' => 400,
				)
			);
		}
		$existing_model = $this->get_model( $term_id );

		$updated = $existing_model->update_from_array( $data );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$maybe_validation_error = $updated->sanitize()->validate();
		if ( is_wp_error( $maybe_validation_error ) ) {
			return $maybe_validation_error;
		}

		$serialized_data = $updated->serialize( WP_Job_Manager_REST_Field_Declaration::META );

		foreach ( $serialized_data as $field_name => $val ) {
			if ( metadata_exists( 'term', $term_id, $field_name ) ) {
				update_term_meta( $term_id, $field_name, $val );
			} else {
				add_term_meta( $term_id, $field_name, $val );
			}
		}

		return true;
	}
}
