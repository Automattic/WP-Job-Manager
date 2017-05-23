<?php
/**
 * The FluentInterface Get
 *
 * @package Mixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixtape_FluentInterface_Get
 */
class WPJM_REST_FluentInterface_Get {
	/**
	 * The environment.
	 *
	 * @var WPJM_REST_Environment
	 */
	private $environment;

	/**
	 * Mixtape_FluentInterface_Get constructor.
	 *
	 * @param WPJM_REST_Environment $environment The Environment.
	 */
	function __construct( $environment ) {
		$this->environment = $environment;
	}

	/**
	 * Get a Type
	 *
	 * @param string $type The type.
	 * @return WPJM_REST_Interfaces_Type
	 * @throws WPJM_REST_Exception Throws if the type is unknown.
	 */
	function type( $type ) {
		return $this->environment->type()->definition( $type );
	}

	/**
	 * Get a Model
	 *
	 * @param string $class The Model Class.
	 * @return WPJM_REST_Model_Definition
	 * @throws WPJM_REST_Exception Throws if the model is unknown.
	 */
	function model( $class ) {
		return $this->environment->model_definition( $class );
	}

	/**
	 * Get a Bundle
	 *
	 * @param string $prefix The bundle name.
	 */
	function bundle( $prefix ) {
	}
}
