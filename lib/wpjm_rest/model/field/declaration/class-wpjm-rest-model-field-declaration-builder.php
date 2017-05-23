<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // End if().

/**
 * Class Mixtape_Model_Field_Declaration_Builder
 * Builds a Mixtape_Model_Field_Declaration
 */
class WPJM_REST_Model_Field_Declaration_Builder {

	function __construct() {
		$this->args = array(
			'name'               => '',
			'type'               => WPJM_REST_Model_Field_Declaration::FIELD,
			'type_definition'    => WPJM_REST_Type::any(),
			'required'           => false,
			'map_from'           => null,
			'before_return'      => null,
			'sanitize'           => null,
			'on_serialize'       => null,
			'on_deserialize'     => null,
			'default_value'      => null,
			'data_transfer_name' => null,
			'supported_outputs'  => array( 'json' ),
			'description'        => null,
			'validations'        => array(),
			'choices'            => null,
			'contexts'           => array( 'view', 'edit' ),
			'before_model_set'   => null,
		);
	}
	public function build() {
		return new WPJM_REST_Model_Field_Declaration( $this->args );
	}

	public function with_default( $default_value ) {
		return $this->with( 'default_value', $default_value );
	}

	public function named( $name ) {
		return $this->with( 'name', $name );
	}

	public function with_data_store_type( $type ) {
		return $this->with( 'type', $type );
	}

	public function map_from( $mapped_from ) {
		return $this->with( 'map_from', $mapped_from );
	}

	public function sanitized_by( $sanitize ) {
		return $this->with( 'sanitize', $sanitize );
	}

	public function with_serializer( $serializer ) {
		return $this->with( 'on_serialize', $serializer );
	}

	public function with_deserializer( $deserializer ) {
		return $this->with( 'on_deserialize', $deserializer );
	}

	public function required( $required = true ) {
		return $this->with( 'required', $required );

	}

	public function with_supported_outputs( $supported_outputs = array() ) {
		return $this->with( 'supported_outputs', (array) $supported_outputs );
	}

	public function not_visible() {
		return $this->with_supported_outputs( array() );
	}

	/**
	 * Set the type definition of this field declaration
	 *
	 * @param WPJM_REST_Interfaces_Type $value_type
	 * @return WPJM_REST_Model_Field_Declaration_Builder $this
	 * @throws WPJM_REST_Exception
	 */
	public function typed( $value_type ) {
		if ( ! is_a( $value_type, 'WPJM_REST_Interfaces_Type') ) {
			throw new WPJM_REST_Exception( get_class( $value_type ) . ' is not a Mixtape_Interfaces_Type' );
		}
		return $this->with( 'type_definition', $value_type );
	}

	public function dto_name( $dto_name ) {
		return $this->with( 'data_transfer_name', $dto_name );
	}

	public function description( $description ) {
		return $this->with( 'description', $description );
	}

	public function validated_by( $validations ) {
		return $this->with( 'validations', is_array( $validations ) ? $validations : array( $validations ) );
	}

	public function before_set( $before_model_set ) {
		return $this->with( 'before_model_set', $before_model_set );
	}


	public function choices( $choices ) {
		if ( empty( $choices ) ) {
			return $this;
		}
		return $this->with( 'choices', is_array( $choices ) ? $choices : array( $choices ) );
	}

	private function with( $name, $value ) {
		$this->args[ $name ] = $value;
		return $this;
	}

	public function derived( $func ) {
		return $this->with_data_store_type( WPJM_REST_Model_Field_Declaration::DERIVED )->map_from( $func );
	}
}
