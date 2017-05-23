<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPJM_REST_Type_Registry {
	private $container_types = array(
		'array',
		'nullable',
	);
	private $types = null;

	/**
	 * @param string                  $identifier
	 * @param WPJM_REST_Interfaces_Type $instance
	 * @return WPJM_REST_Type_Registry $this
	 * @throws WPJM_REST_Exception
	 */
	public function define( $identifier, $instance ) {
		WPJM_REST_Expect::is_a( $instance, 'WPJM_REST_Interfaces_Type');
		$this->types[ $identifier ] = $instance;
		return $this;
	}

	/**
	 * @param string $type
	 * @return WPJM_REST_Interfaces_Type
	 * @throws WPJM_REST_Exception
	 */
	function definition( $type ) {
		$types = $this->get_types();

		if ( ! isset( $types[ $type ] ) ) {
			// maybe lazy-register missing compound type
			$parts = explode( ':', $type );
			if ( count( $parts ) > 1 ) {

				$container_type = $parts[0];
				if ( ! in_array( $container_type, $this->container_types, true ) ) {
					throw new WPJM_REST_Exception( $container_type . ' is not a known container type' );
				}

				$item_type = $parts[1];
				if ( empty( $item_type ) ) {
					throw new WPJM_REST_Exception( $type . ': invalid syntax' );
				}
				$item_type_definition = $this->definition( $item_type );

				if ( 'array' === $container_type ) {
					$this->define( $type, new WPJM_REST_Type_TypedArray( $item_type_definition ) );
					$types = $this->get_types();
				}

				if ( 'nullable' === $container_type ) {
					$this->define( $type, new WPJM_REST_Type_Nullable( $item_type_definition ) );
					$types = $this->get_types();
				}
			}
		}

		if ( ! isset( $types[ $type ] ) ) {
			throw new WPJM_REST_Exception();
		}
		return $types[ $type ];
	}

	private function get_types() {
		return apply_filters( 'mixtape_type_registry_get_types', $this->types, $this );
	}

	public function initialize( $environment ) {
		if ( null !== $this->types ) {
			return;
		}

		$this->types = apply_filters( 'mixtape_type_registry_register_types', array(
			'any'           => new WPJM_REST_Type( 'any' ),
			'string'        => new WPJM_REST_Type_String(),
			'integer'       => new WPJM_REST_Type_Integer(),
			'int'           => new WPJM_REST_Type_Integer(),
			'uint'          => new WPJM_REST_Type_Integer( true ),
			'number'        => new WPJM_REST_Type_Number(),
			'float'         => new WPJM_REST_Type_Number(),
			'boolean'       => new WPJM_REST_Type_Boolean(),
			'array'         => new WPJM_REST_Type_Array(),
		), $this, $environment );
	}
}
