<?php
/**
 * String type
 *
 * @package Mixtape/Type
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPJM_REST_Type_String
 */
class WPJM_REST_Type_String extends WPJM_REST_Type {
	/**
	 * WPJM_REST_Type_String constructor.
	 */
	function __construct() {
		parent::__construct( 'string' );
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed $value Val.
	 * @return string
	 */
	function sanitize( $value ) {
		return sanitize_text_field( $value );
	}

	/**
	 * Default
	 *
	 * @return string
	 */
	function default_value() {
		return '';
	}

	/**
	 * Cast
	 *
	 * @param mixed $value Val.
	 * @return string
	 */
	function cast( $value ) {
		if ( is_array( $value ) ) {
			$cast_ones = array();
			foreach ( $value as $v ) {
				$cast_ones[] = $this->cast( $v );
			}
			return '(' . implode( ',', $cast_ones ) . ')';
		}
		return (string) $value;
	}
}
