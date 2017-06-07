<?php
/**
 * Declaration of our Settings Model
 *
 * @package WPJM/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_Models_Settings
 */
class WP_Job_Manager_Models_Settings extends WPJM_REST_Model_Declaration_Settings
	implements WPJM_REST_Interfaces_Permissions_Provider {

	/**
	 * Fields that have to be valid page ids.
	 *
	 * @var array
	 */
	private $fields_requiring_page_id_validation = array(
		'job_manager_submit_job_form_page_id',
		'job_manager_job_dashboard_page_id',
		'job_manager_jobs_page_id',
	);

	/**
	 * Get Job Manager Settings
	 *
	 * @return array
	 */
	function get_settings() {
		if ( ! class_exists( 'WP_Job_Manager_Settings' ) ) {
			$parent = dirname( dirname( __FILE__ ) );
			if ( ! function_exists( 'get_editable_roles' ) ) {
				// WP_Job_Manager_Settings needs this for user roles.
				include_once( ABSPATH . 'wp-admin/includes/user.php' );
			}
			include_once( $parent . '/admin/class-wp-job-manager-settings.php' );
		}

		return WP_Job_Manager_Settings::instance()->get_settings();
	}

	/**
	 * Adds validations to fields requiring page ids.
	 * Sets field's dto name.
	 *
	 * @param string                                               $field_name The fields name.
	 * @param WPJM_REST_Model_Field_Declaration_Builder            $field_builder The field builder.
	 * @param array                                                $field_data The field data.
	 * @param WPJM_REST_Model_Field_Declaration_Collection_Builder $def The definition.
	 */
	protected function on_field_setup( $field_name, $field_builder, $field_data, $def ) {
		$field_builder->dto_name( str_replace( 'job_manager_' , '', $field_name ) );

		if ( in_array( $field_name, $this->fields_requiring_page_id_validation, true ) ) {
			$field_builder->typed( $def->type( 'integer' ) )
				->validated_by( 'validate_page_id_belongs_to_valid_page' );
		}
	}

	/**
	 * Validates that a page_id points to a valid page.
	 *
	 * @param WPJM_REST_Model_ValidationData $validation_data The data.
	 * @return bool|WP_Error
	 */
	function validate_page_id_belongs_to_valid_page( $validation_data ) {
		$id = $validation_data->get_value();
		$content = get_post( $id );

		if ( ! empty( $content ) && 'page' === $content->post_type ) {
			return true;
		}

		return new WP_Error( 'invalid-page-id', __( 'Invalid page ID provided', 'wp-job-manager' ) );
	}

	/**
	 * Permissions for accessing Settings.
	 *
	 * @param WP_REST_Request $request The request.
	 * @param string          $action The action.
	 *
	 * @return bool
	 */
	function permissions_check( $request, $action ) {
		return current_user_can( 'manage_options' );
	}
}
