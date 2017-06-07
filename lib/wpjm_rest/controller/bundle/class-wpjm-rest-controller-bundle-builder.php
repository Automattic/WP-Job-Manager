<?php

class WPJM_REST_Controller_Bundle_Builder implements WPJM_REST_Interfaces_Builder {

	private $bundle_prefix;
	private $environment;
	private $endpoint_builders = array();
	/**
	 * @var WPJM_REST_Controller_Bundle|null
	 */
	private $bundle = null;

	/**
	 * WPJM_REST_Controller_Bundle_Builder constructor.
	 *
	 * @param WPJM_REST_Interfaces_Controller_Bundle|null $bundle
	 */
	function __construct( $bundle = null ) {
		$this->bundle = $bundle;
	}

	/**
	 * @return WPJM_REST_Interfaces_Controller_Bundle
	 */
	public function build() {
		if ( is_a( $this->bundle, 'WPJM_REST_Interfaces_Controller_Bundle') ) {
			return $this->bundle;
		}
		return new WPJM_REST_Controller_Bundle_Definition( $this->environment, $this->bundle_prefix, $this->endpoint_builders );
	}

	/**
	 * @param $bundle_prefix
	 * @return WPJM_REST_Controller_Bundle_Builder $this
	 */
	public function with_prefix( $bundle_prefix ) {
		$this->bundle_prefix = $bundle_prefix;
		return $this;
	}

	/**
	 * @param $env
	 * @return WPJM_REST_Controller_Bundle_Builder $this
	 */
	public function with_environment( $env ) {
		$this->environment = $env;
		return $this;
	}

	/**
	 * @return WPJM_REST_Controller_Builder
	 */
	public function endpoint() {
		$endpoint = new WPJM_REST_Controller_Builder();

		$this->endpoint_builders[] = $endpoint->with_environment( $this->environment );
		return $endpoint;
	}
}
