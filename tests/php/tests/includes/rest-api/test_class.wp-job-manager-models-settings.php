<?php
/**
 * @group rest
 */
class WP_Test_WP_Job_Manager_Models_Settings extends WPJM_REST_TestCase {

	public function test_exists() {
		$this->assertClassExists( 'WP_Job_Manager_Models_Settings' );
	}

	public function test_validate_fail_when_invalid_page_id_settings() {
		$setting_definition = $this->environment()->model( 'WP_Job_Manager_Models_Settings' );
		$settings           = $setting_definition->get_data_store()->get_entity( null );
		$this->assertNotNull( $settings );
		$settings_fields_with_page_ids = array(
			'job_manager_submit_job_form_page_id',
			'job_manager_job_dashboard_page_id',
			'job_manager_jobs_page_id',
		);
		foreach ( $settings_fields_with_page_ids as $field_name ) {
			$previous_value = $settings->get( $field_name );
			$settings->set( $field_name, -1 );
			$result = $settings->validate();
			$this->assertWPError( $result );
			$settings->set( $field_name, $previous_value );
			$this->assertModelValid( $settings );
		}
	}

	public function test_dto_name_for_field_does_not_remove_job_manager_prefix() {
		$setting_definition = $this->environment()->model( 'WP_Job_Manager_Models_Settings' );
		$settings           = $setting_definition->get_data_store()->get_entity( null );
		$this->assertNotNull( $settings );
		$dto = $settings->to_dto();
		$this->assertInternalType( 'array', $dto );
		$this->assertArrayHasKey( 'job_manager_per_page', $dto );
		foreach ( $dto as $key => $value ) {
			$this->assertContains( 'job_manager_', $key );
		}
	}
}

