<?php
/**
 * Typed Array
 *
 * A container types
 *
 * @package Mixtape/Type
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPJM_REST_Type_TypedArray
 */
class WPJM_REST_Type_TypedArray extends WPJM_REST_Type {

	/**
	 * The type this array contains
	 *
	 * @var WPJM_REST_Interfaces_Type
	 */
	private $item_type_definition;

	/**
	 * Mixtape_TypeDefinition_TypedArray constructor.
	 *
	 * @param WPJM_REST_Interfaces_Type $item_type_definition The type.
	 */
	function __construct( $item_type_definition ) {
		parent::__construct( 'array:' . $item_type_definition->name() );
		$this->item_type_definition = $item_type_definition;
	}

	/**
	 * Get the default value
	 *
	 * @return array
	 */
	public function default_value() {
		return array();
	}

	/**
	 * Cast the value to be a typed array
	 *
	 * @param mixed $value an array of mixed.
	 * @return array
	 */
	public function cast( $value ) {
		$new_value = array();

		foreach ( $value as $v ) {
			$new_value[] = $this->item_type_definition->cast( $v );
		}
		return (array) $new_value;
	}

	/**
	 * Get this type's JSON Schema
	 *
	 * @return array
	 */
	function schema() {
		$schema = parent::schema();
		$schema['type'] = 'array';
		$schema['items'] = $this->item_type_definition->schema();
		return $schema;
	}
}
