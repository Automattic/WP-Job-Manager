<?php
/**
 * Boolean Type
 *
 * @package Mixtape/Type
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPJM_REST_Type_Boolean
 */
class WPJM_REST_Type_Boolean extends WPJM_REST_Type {

	/**
	 * WPJM_REST_Type_Boolean constructor.
	 */
	public function __construct() {
		parent::__construct( 'boolean' );
	}

	/**
	 * Default
	 *
	 * @return bool
	 */
	public function default_value() {
		return false;
	}

	/**
	 * Cast
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public function cast( $value ) {
		if ( 'false' === $value ) {
			return false;
		}
		return (bool) $value;
	}
}
