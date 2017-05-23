<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPJM_REST_Model_Field_Declaration_Collection_Builder {
	/**
	 * @var array
	 */
	private $field_declarations;
	/**
	 * @var WPJM_REST_Environment
	 */
	private $environment;

	function __construct( $environment ) {
		$this->field_declarations = array();
		$this->environment = $environment;
	}

	/**
	 * @param WPJM_REST_Model_Field_Declaration_Builder $field
	 * @return WPJM_REST_Model_Field_Declaration_Collection_Builder $this
	 */
	function add( $field ) {
		$this->field_declarations[] = $field;
		return $this;
	}

	function build() {
		return $this->field_declarations;
	}

	function field( $name = null, $description = null, $data_store_type = 'field' ) {
		$builder = new WPJM_REST_Model_Field_Declaration_Builder();
		if ( ! empty( $name ) ) {
			$builder->named( $name );
		}
		if ( ! empty( $description ) ) {
			$builder->description( $description );
		}
		if ( 'field' !== $data_store_type ) {
			$builder->with_data_store_type( $data_store_type );
		}
		return $builder;
	}

	/**
	 * @param $type_name
	 * @return WPJM_REST_Interfaces_Type
	 * @throws WPJM_REST_Exception
	 */
	function type( $type_name ) {
		return $this->environment->get()->type( $type_name );
	}
}
