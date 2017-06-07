<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPJM_REST_Data_Store_Option extends WPJM_REST_Data_Store_Abstract {
	/**
	 * @var stdClass a guard value to distinguish between get_option returning results or not
	 */
	private $does_not_exist_guard;

	function __construct( $definition, $data_provider = null ) {
		parent::__construct( $definition );
		$this->does_not_exist_guard = new stdClass();
	}

	public function get_entities( $filter = null ) {
		// there is only one option bag and one option bag global per data store
		return $this->get_entity( '' );
	}

	/**
	 * @param int $id the id of the entity
	 * @return WPJM_REST_Model
	 */
	public function get_entity( $id ) {
		$field_declarations = $this->get_definition()->get_field_declarations();
		$raw_data = array();
		foreach ( $field_declarations as $field_declaration ) {
			/** @var WPJM_REST_Model_Field_Declaration  $field_declaration */
			$option = get_option( $field_declaration->get_map_from(), $this->does_not_exist_guard );
			if ( $this->does_not_exist_guard !== $option ) {
				$raw_data[ $field_declaration->get_map_from() ] = $option;
			}
		}

		$data = $this->get_data_mapper()
			->raw_data_to_model_data( $raw_data, $field_declarations );
		return $this->get_definition()->create_instance( $data );
	}

	/**
	 * @param WPJM_REST_Interfaces_Model $model
	 * @param array                    $args
	 * @return mixed
	 */
	public function delete( $model, $args = array() ) {
		$options_to_delete = array_keys( $this->get_data_mapper()->model_to_data( $model ) );
		foreach ( $options_to_delete as $option_to_delete ) {
			if ( false !== get_option( $option_to_delete, false ) ) {
				delete_option( $option_to_delete );
			}
		}
		return true;
	}

	/**
	 * @param WPJM_REST_Interfaces_Model $model
	 * @return mixed
	 */
	public function upsert( $model ) {
		$fields_for_insert = $this->get_data_mapper()->model_to_data( $model );
		foreach ( $fields_for_insert as $option_name => $option_value ) {
			if ( $this->does_not_exist_guard !== get_option( $option_name, $this->does_not_exist_guard ) ) {
				update_option( $option_name, $option_value );
			} else {
				add_option( $option_name, $option_value );
			}
		}
	}
}
