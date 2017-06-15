<?php
/**
 * Declaration of our Configuration Filter
 *
 * @package WPJM/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_Models_Settings
 */
class WP_Job_Manager_Filters_Configuration extends WPJM_REST_Model_Declaration {

	/**
	 * Declare our fields
	 *
	 * @param  WPJM_REST_Model_Field_Declaration_Collection_Builder $def Def.
	 * @return array
	 * @throws WPJM_REST_Exception Exc.
	 */
	function declare_fields( $def ) {
		return array(
		 $def->field( 'keys', 'The configuration keys to return' )
			 ->typed( $def->type( 'array:string' ) )
			 ->before_set( 'explode_keys' )
			 ->with_default( array() ),
		);
	}

	/**
	 * Explode keys
	 *
	 * @param  MT_Interfaces_Model $model Model.
	 * @param  mixed               $keys  The keys.
	 * @return array
	 */
	function explode_keys( $model, $keys ) {
		if ( is_string( $keys ) ) {
			return explode( ',', $keys );
		}
		return $keys;
	}
}

