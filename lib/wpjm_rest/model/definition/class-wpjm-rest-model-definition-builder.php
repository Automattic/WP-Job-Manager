<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPJM_REST_Model_Definition_Builder implements WPJM_REST_Interfaces_Builder {
	private $declaration;
	private $data_store;
	private $environment;
	/**
	 * @var WPJM_REST_Interfaces_Permissions_Provider
	 */
	private $permissions_provider;

	function __construct() {
		$this->with_data_store( new WPJM_REST_Data_Store_Nil() );
	}

	/**
	 * @param WPJM_REST_Interfaces_Model_Declaration|WPJM_REST_Interfaces_Permissions_Provider $declaration
	 * @return WPJM_REST_Model_Definition_Builder $this
	 */
	function with_declaration( $declaration ) {
		if ( is_string( $declaration ) && class_exists( $declaration ) ) {
			$declaration = new $declaration();
		}
		WPJM_REST_Expect::is_a( $declaration, 'WPJM_REST_Interfaces_Model_Declaration');
		$this->declaration = $declaration;
		if ( is_a( $declaration, 'WPJM_REST_Interfaces_Permissions_Provider') ) {
			$this->with_permissions_provider( $declaration );
		}
		return $this;
	}

	/**
	 * @param null|WPJM_REST_Interfaces_Builder $data_store
	 * @return WPJM_REST_Model_Definition_Builder $this
	 */
	function with_data_store( $data_store = null ) {
		$this->data_store = $data_store;
		return $this;
	}

	/**
	 * @param WPJM_REST_Interfaces_Permissions_Provider $permissions_provider
	 */
	function with_permissions_provider( $permissions_provider ) {
		$this->permissions_provider = $permissions_provider;
	}

	/**
	 * @param WPJM_REST_Environment $environment
	 * @return WPJM_REST_Model_Definition_Builder $this
	 */
	function with_environment( $environment ) {
		$this->environment = $environment;
		return $this;
	}

	/**
	 * @return WPJM_REST_Model_Definition
	 */
	function build() {
		return new WPJM_REST_Model_Definition( $this->environment, $this->declaration, $this->data_store, $this->permissions_provider );
	}
}
