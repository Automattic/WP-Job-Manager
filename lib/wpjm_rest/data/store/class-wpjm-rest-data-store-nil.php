<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPJM_REST_Data_Store_Nil
 * Null object for datastores
 */
class WPJM_REST_Data_Store_Nil implements WPJM_REST_Interfaces_Data_Store {

	public function get_entities( $filter = null ) {
		return new WPJM_REST_Model_Collection( array() );
	}

	public function get_entity( $id ) {
		return null;
	}

	public function delete( $model, $args = array() ) {
		return true;
	}

	public function upsert( $model ) {
		return 0;
	}

	public function set_definition( $definition ) {
	}
}
