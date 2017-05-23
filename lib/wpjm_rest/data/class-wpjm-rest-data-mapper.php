<?php
/**
 * Data Mapper
 *
 * @package WPJM_REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPJM_REST_Data_Mapper
 */
class WPJM_REST_Data_Mapper {
	/**
	 * @var WPJM_REST_Data_Serializer
	 */
	private $serializer;
	/**
	 * @var WPJM_REST_Model_Definition
	 */
	private $definition;

	function __construct( $definition, $serializer ) {
		$this->definition = $definition;
		$this->serializer = $serializer;
	}

	function raw_data_to_model_data( $data, $field_declarations ) {
		$raw_data = array();
		$post_array_keys = array_keys( $data );
		foreach ( $field_declarations as $declaration ) {
			/** @var WPJM_REST_Model_Field_Declaration $declaration */
			$key = $declaration->get_name();
			$mapping = $declaration->get_map_from();
			$value = null;
			if ( in_array( $key, $post_array_keys, true ) ) {
				// simplest case: we got a $key for this, so just map it
				$value = $this->serializer->deserialize( $declaration, $data[ $key ] );
			} elseif ( in_array( $mapping, $post_array_keys, true ) ) {
				$value = $this->serializer->deserialize( $declaration, $data[ $mapping ] );
			} else {
				$value = $declaration->get_default_value();
			}
			$raw_data[ $key ] = $declaration->cast_value( $value );
		}
		return $raw_data;
	}

	/**
	 * @param WPJM_REST_Interfaces_Model $model
	 * @param $field_type
	 * @return array
	 */
	function model_to_data( $model, $field_type = null ) {
		$field_values_to_insert = array();
		foreach ( $this->definition->get_field_declarations( $field_type ) as $field_declaration ) {
			/** @var WPJM_REST_Model_Field_Declaration $field_declaration */
			$what_to_map_to = $field_declaration->get_map_from();
			$value = $model->get( $field_declaration->get_name() );
			$field_values_to_insert[ $what_to_map_to ] = $this->serializer->serialize( $field_declaration, $value );
		}

		return $field_values_to_insert;
	}
}
