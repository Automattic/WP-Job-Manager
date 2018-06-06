<?php

require 'includes/class-wp-job-manager-data-exporter.php';

class WP_Job_Manager_Data_Exporter_Test extends WP_UnitTestCase {
	/**
	 * Setup user meta
	 *
	 * @param array $args
	 */
	private function setupUserMeta( $args ) {
		$user_id = $this->factory()->user->create(
			array(
				'user_login' => 'johndoe',
				'user_email' => 'johndoe@example.com',
				'role' => 'subscriber',
			)
		);

		if ( isset( $args['_company_logo' ] ) ) {
			$args['_company_logo'] = $this->factory()->post->create(
				array(
					'post_type' => 'attachment'
				)
			);
		}

		foreach ( $args as $key => $value ) {
			update_user_meta( $user_id, $key, $value );
		}
	}

	/**
	 * @dataProvider data_provider
	 */
	public function test_user_data_exporter( $args, $expected ) {
		$this->setupUserMeta( $args );
		$exporter = new WP_Job_Manager_Data_Exporter();

		$result = $exporter->user_data_exporter( 'johndoe@example.com' );

		$this->assertEquals( $expected, $result );
	}

	public function data_provider(){
		return [
			[
				array(
					'_company_logo' => 'https://example.com/company/logo',
					'_company_name' => 'Example',
					'_company_website' => 'https://example.com/',
					'_company_tagline' => 'Just another tagline',
					'_company_twitter' => 'https://twitter.com/example?ref_src=twsrc%5Egoogle%7Ctwcamp%5Eserp%7Ctwgr%5Eauthor',
					'_company_video' => 'https://example.com/company/video', 
				),
				array(
					'data' => array(
						'group_id'		 => 'wpjm-user-data',
						'group_label'	 => __( 'WP Job Manager User Data' ),
						'data'			 => array(
							'Company Logo' => array(
								'name' => 'Label',
								'value' => 'https://example.com/company/logo',
								),
							'Company Name' => array(
								'name' => 'Label',
								'value' => 'Example',
								),
							'Company Website' => array(
								'name' => 'Label',
								'value' => 'https://example.com/',
								),
							'Company Tagline' => array(
								'name' => 'Label',
								'value' => 'Just another tagline',
								),
							'Company Twitter' => array(
								'name' => 'Label',
								'value' => 'https://twitter.com/example?ref_src=twsrc%5Egoogle%7Ctwcamp%5Eserp%7Ctwgr%5Eauthor',
								),
							'Company Video' => array(
								'name' => 'Label',
								'value' => 'https://example.com/company/video',
								),
						)
					),
					'done' => true,
				)
			], // End of first set of parameters
			[
				array(
					'_company_name' => 'Example',
					'_company_website' => 'https://example.com/',
					'_company_tagline' => 'Just another tagline',
				),
				array(
					'data' => array(
						'group_id'		 => 'wpjm-user-data',
						'group_label'	 => __( 'WP Job Manager User Data' ),
						'data'			 => array(
							'Company Name' => array(
								'name' => 'Label',
								'value' => 'Example',
								),
							'Company Website' => array(
								'name' => 'Label',
								'value' => 'https://example.com/',
								),
							'Company Tagline' => array(
								'name' => 'Label',
								'value' => 'Just another tagline',
								),
						)
					),
					'done' => true,
				)
			] // End of second set of parameters
		];
	}
}
