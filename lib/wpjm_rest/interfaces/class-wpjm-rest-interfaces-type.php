<?php
/**
 * Type
 *
 * @package Mixtape/Type
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface WPJM_REST_Interfaces_Type
 */
interface WPJM_REST_Interfaces_Type {
	/**
	 * Cast value to be Type
	 *
	 * @param mixed $value The value that needs casting.
	 *
	 * @return mixed
	 */
	public function cast( $value );
	/**
	 * The default value
	 *
	 * @return null
	 */
	public function default_value();
	/**
	 * The type's name
	 *
	 * @return string
	 */
	public function name();
	/**
	 * Sanitize this value
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return mixed
	 */
	public function sanitize( $value );
	/**
	 * Get this type's JSON Schema.
	 *
	 * @return array
	 */
	public function schema();
}
