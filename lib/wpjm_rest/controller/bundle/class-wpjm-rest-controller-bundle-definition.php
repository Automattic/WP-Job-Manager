<?php

class WPJM_REST_Controller_Bundle_Definition extends WPJM_REST_Controller_Bundle {

	private $endpoint_builders;
	/**
	 * @var WPJM_REST_Environment
	 */
	private $environment;

	function __construct( $environment, $bundle_prefix, $endpoint_builders ) {
		$this->environment = $environment;
		$this->bundle_prefix = $bundle_prefix;
		$this->endpoint_builders = $endpoint_builders;
	}

	public function get_endpoints() {
		$endpoints = array();
		foreach ( $this->endpoint_builders as $builder ) {
			/** @var WPJM_REST_Rest_Api_Controller_CRUD_Builder $builder */
			$endpoint = $builder->with_bundle( $this )->with_environment( $this->environment )->build();
			$endpoints[] = $endpoint;
		}
		return $endpoints;
	}
}
