<?php

class WP_Test_Autoload extends WPJM_BaseTest {

	public function test_composer_autoloads_namespaced_classes() {
		$this->assertTrue( trait_exists( 'WP_Job_Manager\\Singleton' ) );
		$this->assertTrue( class_exists( 'WP_Job_Manager\\Stats' ) );
		$this->assertTrue( class_exists( 'WP_Job_Manager\\UI\\UI' ) );
	}

	public function test_composer_autoloads_legacy_classes() {
		$this->assertTrue( class_exists( 'WP_Job_Manager_Cache_Helper' ) );
	}
}
