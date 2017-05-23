<?php
/**
 * The FluentInterface Define
 *
 * @package Mixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixtape_FluentInterface_Define
 */
class WPJM_REST_FluentInterface_Define {
	/**
	 * The environment.
	 *
	 * @var WPJM_REST_Environment
	 */
	private $environment;

	/**
	 * Mixtape_FluentInterface_Define constructor.
	 *
	 * @param WPJM_REST_Environment $environment The Environment.
	 */
	function __construct( $environment ) {
		$this->environment = $environment;
	}

	/**
	 * Define a new Model
	 *
	 * @param null|WPJM_REST_Interfaces_Model_Declaration $declaration Possibly a declaration.
	 *
	 * @return WPJM_REST_Model_Definition_Builder
	 */
	function model( $declaration = null ) {
		$builder = new WPJM_REST_Model_Definition_Builder();
		if ( null !== $declaration ) {
			$builder->with_declaration( $declaration );
		}
		$this->environment->push_builder( 'models', $builder->with_environment( $this->environment ) );
		return $builder;
	}

	/**
	 * Define a new DataStore.
	 *
	 * @return WPJM_REST_Data_Store_Builder
	 */
	public function data_store() {
		return new WPJM_REST_Data_Store_Builder();
	}

	/**
	 * Define a new Type
	 *
	 * @param string                  $identifier The type name.
	 * @param WPJM_REST_Interfaces_Type $instance The type.
	 *
	 * @return WPJM_REST_Type_Registry
	 * @throws WPJM_REST_Exception Throw in case $instance isn't a Mixtape_Interfaces_Type.
	 */
	function type( $identifier, $instance ) {
		return $this->environment->type()->define( $identifier, $instance );
	}

	/**
	 * Define a new REST API Bundle.
	 *
	 * @param null|string|WPJM_REST_Interfaces_Controller_Bundle $maybe_bundle_or_prefix The bundle name.
	 * @return WPJM_REST_Controller_Bundle_Builder
	 */
	function rest_api( $maybe_bundle_or_prefix = null ) {
		if ( is_a( $maybe_bundle_or_prefix, 'WPJM_REST_Interfaces_Controller_Bundle') ) {
			$builder = new WPJM_REST_Controller_Bundle_Builder( $maybe_bundle_or_prefix );
		} else {
			$builder = new WPJM_REST_Controller_Bundle_Builder();
			if ( is_string( $maybe_bundle_or_prefix ) ) {
				$builder->with_prefix( $maybe_bundle_or_prefix );
			}
			$builder->with_environment( $this->environment );
		}

		$this->environment->push_builder( 'bundles', $builder );
		return $builder;
	}
}
