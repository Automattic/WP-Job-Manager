<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixtape_Model_Declaration_Settings
 * Represents a single setting field
 */
class WPJM_REST_Model_Declaration_Settings extends WPJM_REST_Model_Declaration
	implements WPJM_REST_Interfaces_Permissions_Provider {

	/**
	 * @return array
	 * @throws WPJM_REST_Exception
	 */
	function get_settings() {
		WPJM_REST_Expect::that( false, 'Override this' );
	}

	protected function default_for_attribute( $field_data, $attribute ) {
		return null;
	}

	/**
	 * @param string                                             $field_name
	 * @param WPJM_REST_Model_Field_Declaration_Builder            $field_builder
	 * @param array                                              $field_data
	 * @param WPJM_REST_Model_Field_Declaration_Collection_Builder $def
	 */
	protected function on_field_setup( $field_name, $field_builder, $field_data, $def ) {
	}

	/**
	 * @param WPJM_REST_Model_Field_Declaration_Collection_Builder $def
	 * @return array
	 */
	function declare_fields( $def ) {
		$settings_per_group = $this->get_settings();
		$fields = array();

		foreach ( $settings_per_group as $group_name => $group_data ) {
			$group_fields = $group_data[1];

			foreach ( $group_fields as $field_data ) {
				$field_builder = $this->field_declaration_builder_from_data( $def, $field_data );
				$fields[] = $field_builder;
			}
		}
		return $fields;
	}

	function bool_to_bit( $value ) {
		return ( ! empty( $value ) && 'false' !== $value ) ? '1' : '';
	}

	function bit_to_bool( $value ) {
		return ( ! empty( $value ) && '0' !== $value ) ? true : false;
	}

	function get_id( $model ) {
		return strtolower( get_class( $this ) );
	}

	function set_id( $model, $new_id ) {
		return $this;
	}

	/**
	 * @param WPJM_REST_Model_Field_Declaration_Collection_Builder $def
	 * @param array                                              $field_data
	 * @return WPJM_REST_Model_Field_Declaration_Builder
	 */
	private function field_declaration_builder_from_data( $def, $field_data ) {
		$field_name = $field_data['name'];
		$field_builder = $def->field( $field_name );
		$default_value = isset( $field_data['std'] ) ? $field_data['std'] : $this->default_for_attribute( $field_data, 'std' );
		$label = isset( $field_data['label'] ) ? $field_data['label'] : $field_name;
		$description = isset( $field_data['desc'] ) ? $field_data['desc'] : $label;
		$setting_type = isset( $field_data['type'] ) ? $field_data['type'] : null;
		$choices = isset( $field_data['options'] ) ? array_keys( $field_data['options'] ) : null;
		$field_type = 'string';

		if ( 'checkbox' === $setting_type ) {
			$field_type = 'boolean';
			if ( $default_value ) {
				// convert our default value as well.
				$default_value = $this->bit_to_bool( $default_value );
			}
			$field_builder
				->with_serializer( 'bool_to_bit' )
				->with_deserializer( 'bit_to_bool' );

		} elseif ( 'select' === $setting_type ) {
			$field_type = 'string';
		} else {
			// try to guess numeric fields, although this is not perfect.
			if ( is_numeric( $default_value ) ) {
				$field_type = is_float( $default_value ) ? 'float' : 'integer';
			}
		}

		if ( $default_value ) {
			$field_builder->with_default( $default_value );
		}
		$field_builder
			->description( $description )
			->dto_name( $field_name )
			->typed( $def->type( $field_type ) );
		if ( $choices ) {
			$field_builder->choices( $choices );
		}

		$this->on_field_setup( $field_name, $field_builder, $field_data, $def );
		return $field_builder;
	}

	/**
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function permissions_check( $request, $action ) {
		return true;
	}
}
