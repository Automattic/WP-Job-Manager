<?php
/**
 * The default Mixtape_Interfaces_Type Implementation
 *
 * @package Mixtape/Type
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixtape_Type
 */
class WPJM_REST_Type implements WPJM_REST_Interfaces_Type {
	/**
	 * The unique identifier of this type.
	 *
	 * @var string
	 */
	protected $identifier;
	/**
	 * Mixtape_Type constructor.
	 *
	 * @param string $identifier The identifier.
	 */
	function __construct( $identifier ) {
		$this->identifier = $identifier;
	}

	/**
	 * The name
	 *
	 * @return string
	 */
	function name() {
		return $this->identifier;
	}

	/**
	 * The default value
	 *
	 * @return null
	 */
	function default_value() {
		return null;
	}

	/**
	 * Cast value to be Type
	 *
	 * @param mixed $value The value that needs casting.
	 *
	 * @return mixed
	 */
	function cast( $value ) {
		return $value;
	}

	/**
	 * Sanitize this value
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return mixed
	 */
	function sanitize( $value ) {
		return $value;
	}

	/**
	 * Get this type's JSON Schema.
	 *
	 * @return array
	 */
	function schema() {
		return array(
			'type' => $this->name(),
		);
	}

	/**
	 * Get our "Any" type
	 *
	 * @return WPJM_REST_Type
	 */
	static function any() {
		return new self( 'any' );
	}
}
