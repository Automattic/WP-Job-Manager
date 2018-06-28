<?php
require_once JOB_MANAGER_PLUGIN_DIR . '/includes/helper/class-wp-job-manager-helper-options.php';
require_once JOB_MANAGER_PLUGIN_DIR . '/includes/helper/class-wp-job-manager-helper-api.php';

class WPJM_Helper_Base_Test extends WPJM_BaseTest {
	protected function plugin_data_with_update() {
		return array(
			'test' => array(
				'_product_slug' => 'test',
				'_filename'     => 'test/test.php',
				'Version'       => '1.0.0',
				'Name'          => 'Test',
			),
		);
	}

	protected function plugin_data_without_update() {
		return array(
			'test' => array(
				'_product_slug' => 'test',
				'_filename'     => 'test/test.php',
				'Version'       => '1.1.0',
				'Name'          => 'Test',
			),
		);
	}

	protected function result_plugin_information() {
		return array(
			'slug'          => 'test',
			'plugin'        => 'test/test.php',
			'version'       => '1.1.0',
			'last_updated'  => '',
			'author'        => 'Test',
			'requires'      => '4.1',
			'tested'        => '4.8',
			'sections'      => array(),
			'homepage'      => 'http://test.dev',
			'download_link' => 'http://test.dev',
		);
	}

	protected function getMockHelper( $plugins = null ) {
		if ( null === $plugins ) {
			$plugins = $this->plugin_data_with_update();
		}

		WP_Job_Manager_Helper_Options::update( 'test', 'licence_key', '1234' );
		WP_Job_Manager_Helper_Options::update( 'test', 'email', 'test@local.dev' );

		$mock = $this->getMockBuilder( 'WP_Job_Manager_Helper' )
					 ->setMethods( array( 'get_installed_plugins', '_get_api' ) )
					 ->getMock();
		$api  = $this->getMockHelperApi();
		$this->setProtectedProperty( $mock, 'api', $api );
		$mock->method( 'get_installed_plugins' )->willReturn( $plugins );
		$mock->method( '_get_api' )->willReturn( $api );
		return $mock;
	}

	protected function getMockHelperApi() {
		$mock = $this->getMockBuilder( 'WP_Job_Manager_Helper_API' )
					 ->setMethods( array( 'plugin_update_check', 'plugin_information', 'activate', 'deactivate' ) )
					 ->getMock();

		$mock->method( 'plugin_update_check' )->willReturn(
			array(
				'slug'        => 'test',
				'plugin'      => 'test',
				'new_version' => '1.1.0',
				'url'         => 'http://test.dev',
				'package'     => 'http://test.dev',
			)
		);
		$mock->method( 'plugin_information' )->willReturn( $this->result_plugin_information() );
		$mock->method( 'activate' )->willReturn(
			array(
				'success'   => true,
				'activated' => true,
				'remaining' => -1,
			)
		);
		$mock->method( 'deactivate' )->willReturn(
			array(
				'success' => true,
			)
		);
		return $mock;
	}

	protected function setProtectedProperty( $object, $property, $value ) {
		$reflection          = new ReflectionClass( $object );
		$reflection_property = $reflection->getProperty( $property );
		$reflection_property->setAccessible( true );
		$reflection_property->setValue( $object, $value );
	}
}
