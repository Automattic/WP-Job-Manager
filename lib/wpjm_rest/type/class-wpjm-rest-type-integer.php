<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPJM_REST_Type_Integer extends WPJM_REST_Type {

	private $unsigned;

	public function __construct( $unsigned = false ) {
		$this->unsigned = $unsigned;
		parent::__construct( 'integer' );
	}

	public function default_value() {
		return 0;
	}

	public function cast( $value ) {
		if ( ! is_numeric( $value ) ) {
			return $this->default_value();
		}
		return $this->unsigned ? absint( $value ) : intval( $value, 10 );
	}

	function sanitize( $value ) {
		return $this->cast( $value );
	}
}
