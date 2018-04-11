<?php
/**
 * Declaration of Job Categories Custom Fields Model
 *
 * @package WPJM/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_Models_Job_Categories_Custom_Fields
 */
class WP_Job_Manager_Models_Job_Categories_Custom_Fields extends WP_Job_Manager_REST_Model
	implements WP_Job_Manager_REST_Interfaces_Model {

	/**
	 * Declare Fields.
	 *
	 * @return array
	 */
	public function declare_fields() {
		return array();
	}
}
