<?php
/**
 * Mixtape_Expect
 *
 * Asserts about invariants
 *
 * @package Mixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixtape_Expect
 */
class WPJM_REST_Expect {
	/**
	 * Expect a certain class
	 *
	 * @param mixed  $thing The thing to test.
	 * @param string $class_name The class.
	 *
	 * @throws WPJM_REST_Exception Fail if we got an unexpected class.
	 */
	static function is_a( $thing, $class_name ) {
		self::is_object( $thing );
		self::that( is_a( $thing, $class_name ), 'Expected ' . $class_name . ', got ' . get_class( $thing ) );
	}

	/**
	 * Expect that thing is an object
	 *
	 * @param mixed $thing The thing.
	 * @throws WPJM_REST_Exception Throw if not an object.
	 */
	static function is_object( $thing ) {
		self::that( is_object( $thing ), 'Variable is is not an Object' );
	}

	/**
	 * Express an invariant.
	 *
	 * @param bool   $cond The boolean condition that needs to hold.
	 * @param string $fail_message In case of failure, the reason this failed.
	 *
	 * @throws WPJM_REST_Exception Fail if condition doesn't hold.
	 */
	static function that( $cond, $fail_message ) {
		if ( ! $cond ) {
			throw new WPJM_REST_Exception( $fail_message );
		}
	}
}
