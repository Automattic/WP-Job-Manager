<?php

namespace WP_Job_Manager;

class WP_Test_Guest_User extends \WPJM_BaseTest {

	public static ?int $time = null;

	public function setUp() : void {
		parent::setUp();
		Function_Mocks::mock( 'time', fn() =>
			self::$time ?? \time()
		);
	}

	public function tearDown() : void {
		self::$time = null;
		parent::tearDown();
	}

	/**
	 * Test that a guest user can be created.
	 */
	public function test_create() {
		// Arrange: Prepare the necessary objects and values.
		$email = 'test@example.com';

		// Act: Call the method being tested.
		$guest_user = Guest_User::create( $email );

		// Assert: Check that the method's output is as expected.
		$this->assertInstanceOf( Guest_User::class, $guest_user );
		$this->assertEquals( $email, $guest_user->user_email );
	}

	/**
	 * Test that guest user creation fails with invalid email.
	 */
	public function test_create_with_invalid_email() {
		// Arrange.
		$email = 'testexample.com';

		// Act.
		$guest_user = Guest_User::create( $email );

		// Assert.
		$this->assertfalse( $guest_user );
	}

	/**
	 * Test that a guest user can be looked up by a valid token.
	 */
	public function test_get_by_token() {
		// Arrange: Create a guest user and get a token for it.
		$email      = 'test@example.com';
		$guest_user = Guest_User::create( $email );
		$token      = $guest_user->create_token();

		// Act: Get the guest user by token.
		$retrieved_guest_user = Guest_User::get_by_token( $token );

		// Assert: Check that the guest user is found.
		$this->assertInstanceOf( Guest_User::class, $retrieved_guest_user );
		$this->assertEquals( $email, $retrieved_guest_user->user_email );
	}

	/**
	 * Test invalid token not returning the guest user.
	 */
	public function test_invalid_token() {
		$guest_user = Guest_User::create( 'test@example.com' );
		$token      = $guest_user->create_token();

		$token = substr( $token, 0, -5 ) . 'aaaaa';

		// Act: Get the guest user by token.
		$retrieved_guest_user = Guest_User::get_by_token( $token );

		// Assert: Check that the guest user is found.
		$this->assertFalse( $retrieved_guest_user );
	}

	/**
	 * Test valid token of a deleted user returns false.
	 */
	public function test_token_of_deleted_user() {
		$guest_user = Guest_User::create( 'test@example.com' );
		$token      = $guest_user->create_token();

		wp_delete_post( $guest_user->ID );

		// Act: Get the guest user by token.
		$retrieved_guest_user = Guest_User::get_by_token( $token );

		// Assert: Check that the guest user is found.
		$this->assertFalse( $retrieved_guest_user );
	}

	/**
	 * Test expired token not returning the guest user.
	 */
	public function test_expired_token() {
		$guest_user = Guest_User::create( 'test@example.com' );
		$token      = $guest_user->create_token();

		self::$time = time() + 50 * DAY_IN_SECONDS;

		// Act: Get the guest user by token.
		$retrieved_guest_user = Guest_User::get_by_token( $token );

		// Assert: Check that the guest user is found.
		$this->assertFalse( $retrieved_guest_user );
	}

	/**
	 * Test that a guest user can be looked up by ID.
	 */
	public function test_load_by_id() {
		// Arrange: Create a guest user.
		$email    = 'test@example.com';
		$guest_id = Guest_User::create( $email )->ID;

		// Act: Load the guest user by ID.
		$loaded_guest_user = Guest_User::load( $guest_id );

		// Assert: Check that the loaded guest user is the same as the created guest user.
		$this->assertInstanceOf( Guest_User::class, $loaded_guest_user );
		$this->assertEquals( $guest_id, $loaded_guest_user->ID );
	}

	/**
	 * Test that a guest user can be looked up by email.
	 */
	public function test_load_by_email() {
		// Arrange: Create a guest user.
		$email = 'test@example.com';
		Guest_User::create( $email );

		// Act: Load the guest user by email.
		$loaded_guest_user = Guest_User::load( $email );

		// Assert: Check that the loaded guest user is the same as the created guest user.
		$this->assertInstanceOf( Guest_User::class, $loaded_guest_user );
		$this->assertEquals( $email, $loaded_guest_user->user_email );
	}

	/**
	 * Test that a guest user can be looked up by ID.
	 */
	public function test_load_with_invalid_email() {
		// Arrange.
		$email    = 'nosuchuser@example.com';

		// Act.
		$guest_user = Guest_User::load( $email );

		// Assert.
		$this->assertFalse( $guest_user );
	}

}
