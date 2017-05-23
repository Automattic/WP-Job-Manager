<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // End if().

/**
 * Class Mixtape_Model_Field_Declaration
 */
class WPJM_REST_Model_Field_Declaration {
	const FIELD = 'field';
	const META = 'meta';
	const DERIVED = 'derived';
	const TAXONOMY = 'taxonomy';
	const OPTION = 'option';
	const TAG = 'tag';

	private $before_return;
	private $map_from;
	private $type;
	private $name;
	private $primary;
	private $required;
	private $supported_outputs;
	private $description;
	private $data_transfer_name;
	private $validations;
	private $default_value;
	private $choices;
	/**
	 * @var null|WPJM_REST_Interfaces_Type
	 */
	private $type_definition;

	private $on_serialize;

	private $accepted_data_store_hints = array(
		self::FIELD,
		self::META,
		self::DERIVED,
		self::TAXONOMY,
		self::OPTION,
		self::TAG,
	);
	private $on_deserialize;
	private $sanitize;
	private $before_model_set;

	public function __construct( $args ) {
		if ( ! isset( $args['name'] ) || empty( $args['name'] ) || ! is_string( $args['name'] ) ) {
			throw new WPJM_REST_Exception( 'every field declaration should have a (non-empty) name string' );
		}
		if ( ! isset( $args['type'] ) || ! in_array( $args['type'], $this->accepted_data_store_hints, true ) ) {
			throw new WPJM_REST_Exception( 'every field should have a type (one of ' . implode( ',', $this->accepted_data_store_hints ) . ')' );
		}

		$this->name                = $args['name'];
		$this->type                = $args['type'];
		$this->type_definition     = $this->value_or_default( $args, 'type_definition', WPJM_REST_Type::any() );
		$this->map_from            = $this->value_or_default( $args, 'map_from' );
		$this->before_return       = $this->value_or_default( $args, 'before_return' );
		$this->sanitize            = $this->value_or_default( $args, 'sanitize' );
		$this->on_serialize        = $this->value_or_default( $args, 'on_serialize' );
		$this->on_deserialize      = $this->value_or_default( $args, 'on_deserialize' );
		$this->primary             = $this->value_or_default( $args, 'primary', false );
		$this->required            = $this->value_or_default( $args, 'required', false );
		$this->supported_outputs   = $this->value_or_default( $args, 'supported_outputs', array( 'json' ) );
		$this->data_transfer_name  = $this->value_or_default( $args, 'data_transfer_name', $this->get_name() );
		$this->default_value       = $this->value_or_default( $args, 'default_value' );
		$this->description         = $this->value_or_default( $args, 'description', '' );
		$this->choices             = $this->value_or_default( $args, 'choices', null );
		$this->validations         = $this->value_or_default( $args, 'validations', array() );
		$this->before_model_set    = $this->value_or_default( $args, 'before_model_set' );
	}

	/**
	 * @return null|array()
	 */
	public function get_choices() {
		return $this->choices;
	}

	public function get_sanitize() {
		return $this->sanitize;
	}

	private function value_or_default( $args, $name, $default = null ) {
		return isset( $args[ $name ] ) ? $args[ $name ] : $default;
	}

	public function is_meta_field() {
		return $this->type === self::META;
	}

	public function is_derived_field() {
		return $this->type === self::DERIVED;
	}

	public function is_field() {
		return $this->type === self::FIELD;
	}

	public function get_default_value() {
		if ( isset( $this->default_value ) && ! empty( $this->default_value ) ) {
			return ( is_array( $this->default_value ) && is_callable( $this->default_value ) ) ? call_user_func( $this->default_value ) : $this->default_value;
		}

		return $this->type_definition->default_value();
	}

	public function cast_value( $value ) {
		return $this->type_definition->cast( $value );
	}

	public function supports_output_type( $type ) {
		return in_array( $type, $this->supported_outputs, true );
	}

	public function as_item_schema_property() {
		$schema = $this->type_definition->schema();
		$schema['context'] = array( 'view', 'edit' );
		$schema['description'] = $this->get_description();

		if ( $this->get_choices() ) {
			$schema['enum'] = (array) $this->get_choices();
		}
		return $schema;
	}

	/**
	 * @return null
	 */
	public function get_map_from() {
		if ( isset( $this->map_from ) && ! empty( $this->map_from ) ) {
			return $this->map_from;
		}

		return $this->get_name();
	}

	/**
	 * @return mixed
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * @return mixed
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * @return bool
	 */
	public function is_primary() {
		return (bool) $this->primary;
	}

	/**
	 * @return bool
	 */
	public function is_required() {
		return (bool) $this->required;
	}

	/**
	 * @return string
	 */
	public function get_description() {
		if ( isset( $this->description ) && ! empty( $this->description ) ) {
			return $this->description;
		}
		$name = ucfirst( str_replace( '_', ' ', $this->get_name() ) );
		return $name;
	}

	/**
	 * @return string
	 */
	public function get_data_transfer_name() {
		return isset( $this->data_transfer_name ) ? $this->data_transfer_name : $this->get_name();
	}

	/**
	 * @return array
	 */
	public function get_validations() {
		return $this->validations;
	}

	public function get_before_return() {
		return $this->before_return;
	}

	public function get_serializer() {
		return $this->on_serialize;
	}

	public function get_deserializer() {
		return $this->on_deserialize;
	}

	/**
	 * @return WPJM_REST_Interfaces_Type
	 */
	function get_type_definition() {
		return $this->type_definition;
	}

	public function before_model_set() {
		return $this->before_model_set;
	}
}
