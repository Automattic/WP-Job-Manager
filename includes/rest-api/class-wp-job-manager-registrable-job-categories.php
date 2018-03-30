<?php
/**
 * Exposes Job Categories Taxonomy REST Api
 *
 * @package WPJM/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_Registrable_Job_Categories
 */
class WP_Job_Manager_Registrable_Job_Categories extends WP_Job_Manager_Registrable_Taxonomy_Type {
	/**
	 * Gets the taxonomy type to register.
	 *
	 * @return string Taxonomy type to expose.
	 */
	public function get_taxonomy_type() {
		return 'job_listing_category';
	}

	/**
	 * Gets the REST API base slug.
	 *
	 * @return string Slug for REST API base.
	 */
	public function get_rest_base() {
		return 'job-categories';
	}

	/**
	 * Gets the REST API model class name.
	 *
	 * @return string Class name for the taxonomy type's model.
	 */
	public function get_model_class_name() {
		return 'WP_Job_Manager_Models_Job_Categories_Custom_Fields';
	}
}
