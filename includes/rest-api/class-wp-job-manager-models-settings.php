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
class WP_Job_Manager_Models_Settings extends WP_Job_Manager_REST_Model_Settings
	implements WP_Job_Manager_REST_Interfaces_Permissions_Provider {


	/**
	 * Fields that have to be valid page ids.
	 *
	 * @var array|null
	 */
	private static $fields_requiring_page_id_validation = null;

	/**
	 * Get Job Manager Settings
	 *
	 * @return array
	 */
	public static function get_settings() {
		if ( ! class_exists( 'WP_Job_Manager_Settings' ) ) {
			$parent = dirname( dirname( __FILE__ ) );
			if ( ! function_exists( 'get_editable_roles' ) ) {
				// WP_Job_Manager_Settings needs this for user roles.
				include_once ABSPATH . 'wp-admin/includes/user.php';
			}
			include_once $parent . '/admin/class-wp-job-manager-settings.php';
		}

		return WP_Job_Manager_Settings::instance()->get_settings();
	}

	/**
	 * Adds validations to fields requiring page ids.
	 *
	 * @param string                                        $field_name    The fields name.
	 * @param WP_Job_Manager_REST_Field_Declaration_Builder $field_builder The field builder.
	 * @param array                                         $field_data    The field data.
	 * @param WP_Job_Manager_REST_Environment               $env           The definition.
	 */
	protected static function on_field_setup( $field_name, $field_builder, $field_data, $env ) {
		if ( in_array( $field_name, self::get_fields_requiring_page_id_validation(), true ) ) {
			$field_builder->with_type( $env->type( 'integer' ) )
				->with_validations( 'validate_page_id_belongs_to_valid_page' );
		}
	}

	/**
	 * Validates that a page_id points to a valid page.
	 *
	 * @param  int $id The id.
	 * @return bool|WP_Error
	 */
	public function validate_page_id_belongs_to_valid_page( $id ) {
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
	 * @param string          $action  The action.
	 *
	 * @return bool
	 */
	public static function permissions_check( $request, $action ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Lazy-load field names requiring Page ID Validation.
	 *
	 * @return array
	 */
	private static function get_fields_requiring_page_id_validation() {
		if ( null === self::$fields_requiring_page_id_validation ) {
			self::$fields_requiring_page_id_validation = (array) apply_filters(
				'wpjm_rest_api_settings_fields_requiring_page_id_validation',
				array(
				'job_manager_submit_job_form_page_id',
				'job_manager_job_dashboard_page_id',
				'job_manager_jobs_page_id',
				)
			);
		}

		return self::$fields_requiring_page_id_validation;
	}
}

