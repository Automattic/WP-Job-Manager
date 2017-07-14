<?php
/**
 * Declaration of our Status Filters (will be used in GET requests)
 *
 * @package WPJM/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_Filters_Status
 */
class WP_Job_Manager_Filters_Status extends WP_Job_Manager_REST_Model_Declaration {

	/**
	 * Declare our fields
	 *
	 * @param  WP_Job_Manager_REST_Environment $env Def.
	 * @return array
	 * @throws WP_Job_Manager_REST_Exception Exc.
	 */
	public function declare_fields( $env ) {
		return array(
		 $env->field( 'keys', 'The status keys to return' )
			 ->with_type( $env->type( 'array:string' ) )
			 ->with_before_set( array( $this, 'explode_keys' ) )
			 ->with_default( array() ),
		);
	}

	/**
	 * Explode keys
	 *
	 * @param  WP_Job_Manager_REST_Interfaces_Model $model Model.
	 * @param  mixed                                $keys  The keys.
	 * @return array
	 */
	public function explode_keys( $model, $keys ) {
		if ( is_string( $keys ) ) {
			return explode( ',', $keys );
		}
		return $keys;
	}
}

