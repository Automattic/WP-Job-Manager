<?php

class WPJM_REST_Controller_Builder implements WPJM_REST_Interfaces_Builder {

	private $controller_class;
	private $environment;
	private $bundle;
	private $model_definition = null;
	private $base = '';
	private $actions = array();

	function __construct() {
	}

	function for_model( $definition ) {
		$this->model_definition = $definition;
		return $this;
	}

	public function with_bundle( $bundle_prefix ) {
		$this->bundle = $bundle_prefix;
		return $this;
	}

	public function with_environment( $env ) {
		$this->environment = $env;
		return $this;
	}

	public function with_class( $controller_class ) {
		if ( ! class_exists( $controller_class ) ) {
			throw new WPJM_REST_Exception( 'class ' . $controller_class . ' does not exist' );
		}
		$this->controller_class = $controller_class;
		return $this;
	}

	public function crud( $base = null ) {
		if ( $base ) {
			$this->with_base( $base );
		}
		return $this->with_class( 'WPJM_REST_Controller_CRUD' );
	}

	public function settings() {
		return $this->with_class( 'WPJM_REST_Controller_Settings' );
	}

	public function build() {
		$controller_class = $this->controller_class;
		if ( ! class_exists( $controller_class ) ) {
			throw new WPJM_REST_Exception( 'class ' . $controller_class . ' does not exist' );
		}
		if ( $this->model_definition ) {
			return new $controller_class( $this->bundle, $this->base, $this->model_definition );
		}
		return new $controller_class( $this->bundle, $this->environment );
	}

	public function with_actions( $actions ) {
		$this->actions = $actions;
		return $this;
	}

	public function with_base( $base ) {
		$this->base = $base;
		return $this;
	}
}
