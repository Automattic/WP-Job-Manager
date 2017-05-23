<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPJM_REST_Type_Number extends WPJM_REST_Type {

	function __construct() {
		parent::__construct( 'number' );
	}

	function default_value() {
		return 0.0;
	}

	function cast( $value ) {
		if ( ! is_numeric( $value ) ) {
			return $this->default_value();
		}
		return floatval( $value );
	}

	function sanitize( $value ) {
		return $this->cast( $value );
	}
}
