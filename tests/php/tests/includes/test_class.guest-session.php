<?php

namespace WP_Job_Manager;

class WP_Test_Guest_Session extends \WPJM_BaseTest {

	public static ?int $time    = null;
	public static      $cookies = [];

	//setup
	public function setUp(): void {
		parent::setUp();
		Function_Mocks::mock( 'setcookie', fn( $name, $value ) => self::$cookies[ $name ] = $value );
		Function_Mocks::mock( 'time', fn() => self::$time ?? \time() );
		$_COOKIE = self::$cookies;
		$_GET    = [];
	}

	public function tearDown(): void {
		self::$time = null;
		parent::tearDown();

		$reflection = new \ReflectionClass( Guest_Session::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
		$instance->setAccessible( false );

	}

	/**
	 * Test that a guest user is found if there is a valid URL token.
	 */
	public function test_resolve_user_from_url_token() {
		// Arrange: Create a guest user and add token to the request.
		$email                            = 'test@example.com';
		$guest_user                       = Guest_User::create( $email );
		$_GET[ Guest_Session::QUERY_VAR ] = $guest_user->create_token();

		// Act: Get the current guest user.
		$current_guest = Guest_Session::get_current_guest();

		// Assert: Check that the current guest user is the same as the created guest user.
		$this->assertInstanceOf( Guest_User::class, $current_guest );
		$this->assertEquals( $email, $current_guest->user_email );
	}

	/**
	 * Test that a guest user is found if there is a valid cookie token.
	 */
	public function test_resolve_user_from_cookie() {
		// Arrange: Create a guest user and add token to a cookie.
		$email                                 = 'test@example.com';
		$guest_user                            = Guest_User::create( $email );
		$_COOKIE[ Guest_Session::COOKIE_NAME ] = $guest_user->create_token();

		// Act: Get the current guest user.
		$current_guest = Guest_Session::get_current_guest();

		// Assert: Check that the current guest user is the same as the created guest user.
		$this->assertInstanceOf( Guest_User::class, $current_guest );
		$this->assertEquals( $email, $current_guest->user_email );
	}

	/**
	 * Test that a guest user is not returned if there is no token.
	 */
	public function test_get_current_guest_no_session() {
		// Act: Get the current guest user.
		$current_guest = Guest_Session::get_current_guest();

		// Assert: Check that there is no current guest user.
		$this->assertFalse( $current_guest );
	}

	/**
	 * Test that a guest user is not returned if the token is invalid.
	 */
	public function test_get_current_guest_invalid_token() {
		// Arrange: Set an invalid token in the session.
		$_COOKIE[ Guest_Session::COOKIE_NAME ] = 'invalid_token';

		// Act: Get the current guest user.
		$current_guest = Guest_Session::get_current_guest();

		// Assert: Check that there is no current guest user.
		$this->assertFalse( $current_guest );
	}

	/**
	 * Test that a guest user is not returned if the user has been deleted.
	 */
	public function test_get_current_guest_non_existent_user() {
		// Arrange: Create a guest user and get a token for it, then delete the guest user.
		$email      = 'test@example.com';
		$guest_user = Guest_User::create( $email );
		$token      = $guest_user->create_token();
		wp_delete_post( $guest_user->ID );
		$_COOKIE[ Guest_Session::COOKIE_NAME ] = $token;

		// Act: Get the current guest user.
		$current_guest = Guest_Session::get_current_guest();

		// Assert: Check that there is no current guest user.
		$this->assertFalse( $current_guest );
	}

	/**
	 * Test that a guest user is not returned if the token has expired.
	 */
	public function test_get_current_guest_expired_token() {
		// Arrange: Create a guest user and get a token for it, then set the time to after the token's expiry.
		$email                                 = 'test@example.com';
		$guest_user                            = Guest_User::create( $email );
		$token                                 = $guest_user->create_token();
		self::$time                            = time() + 50 * DAY_IN_SECONDS;
		$_COOKIE[ Guest_Session::COOKIE_NAME ] = $token;

		// Act: Get the current guest user.
		$current_guest = Guest_Session::get_current_guest();

		// Assert: Check that there is no current guest user.
		$this->assertFalse( $current_guest );
	}


}
