<?php
/**
 * Helper to mock global PHP or WordPress functions.
 *
 * Caller code should be in the WP_Job_Manager namespace.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

class Function_Mocks {
	public static $active_mocks = [];

	public static function mock( $name, $mock_function ) {
		self::$active_mocks[ $name ] = $mock_function;
	}

	public static function tearDown() {
		self::$active_mocks = [];
	}

	public static function run( $name, $args, $fallback ) {
		if ( ! empty( self::$active_mocks[ $name ] ) ) {
			return call_user_func_array( self::$active_mocks[ $name ], $args );
		} else {
			return call_user_func_array( $fallback, $args );
		}
	}
}

/**
 * Mocks global time().
 *
 * @return int
 */
function time(): int {
	return Function_Mocks::run( 'time', [], '\time' );
}

/**
 * Mocks setcookie().
 */
function setcookie( ...$args ) {
	return Function_Mocks::run( 'setcookie', $args, '\setcookie' );

}

