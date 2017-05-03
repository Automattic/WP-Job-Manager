<?php

class WPJM_BaseTest extends WP_UnitTestCase {
	protected static function factory() {
		static $factory = null;
		if ( ! $factory ) {
			$factory = new WPJM_Factory();
		}
		return $factory;
	}
}
