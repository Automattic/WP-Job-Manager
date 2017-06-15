<?php
/**
 * Declaration of our Configuration Model
 *
 * @package WPJM/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_Models_Settings
 */
class WP_Job_Manager_Models_Configuration extends WPJM_REST_Model_Declaration
	implements WPJM_REST_Interfaces_Permissions_Provider {


	/**
	 * Declare our fields
	 *
	 * @param  WPJM_REST_Model_Field_Declaration_Collection_Builder $def Def.
	 * @return array
	 * @throws WPJM_REST_Exception Exc.
	 */
	function declare_fields( $def ) {
		return array(
		 $def->field( 'run_page_setup', 'Should we run page setup' )
			 ->typed( $def->type( 'boolean' ) ),
		);
	}

	/**
	 * Handle Permissions for a REST Controller Action
	 *
	 * @param  WP_REST_Request $request The request.
	 * @param  string          $action  The action (e.g. index, create update etc).
	 * @return bool
	 */
	public function permissions_check( $request, $action ) {
		if ( in_array( $action, array( 'index', 'show' ), true ) ) {
			return true;
		}
		return current_user_can( 'manage_options' );
	}
}

