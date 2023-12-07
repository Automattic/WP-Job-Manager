<?php

namespace WP_Job_Manager;

use WP_UnitTestCase;

/**
 * Mocks global time().
 *
 * @return int
 */
function time() : int {
	return WP_Test_Access_Token::$time ?: \time();
}
class WP_Test_Access_Token extends WP_UnitTestCase {

	public static ?int $time;

	protected function tearDown() : void {
		self::$time = null;
		parent::tearDown();
	}

	public function test_correct_token_verified_succesfully() {
		$access_token = new Access_Token( [ 'user_id' => 10 ] );
		$token = $access_token->create();

		self::assertTrue( ( new Access_Token( [ 'user_id' => 10] ) )->verify( $token ) );
		self::assertFalse( ( new Access_Token( [ 'user_id' => 10] ) )->verify( 'aRandomString' ) );
	}

	public function test_expired_token_not_verified() {
		$access_token = new Access_Token( [ 'user_id' => 10 ] );
		$token = $access_token->create( time() + 200 );

		self::assertTrue( ( new Access_Token( [ 'user_id' => 10] ) )->verify( $token ) );

		self::$time = time() + 500;

		self::assertFalse( ( new Access_Token( [ 'user_id' => 10] ) )->verify( $token ) );
	}

	public function test_incorrect_payload_not_verified() {
		$access_token = new Access_Token( [ 'user_id' => 10, 'email' => 'email@email.com' ] );
		$token = $access_token->create();

		self::assertTrue( ( new Access_Token( [ 'email' => 'email@email.com', 'user_id' => 10 ] ) )->verify( $token ) );
		self::assertFalse( ( new Access_Token( [ 'user_id' => 110, 'email' => 'email@email.com' ] ) )->verify( $token ) );
	}

	public function test_token_no_expiration_always_verified() {
		$access_token = new Access_Token( [ 'user_id' => 10 ] );
		$token = $access_token->create();
		$token_with_expiry = $access_token->create( time() + 1000 );

		self::$time = time() + 10000000;
		self::assertTrue( ( new Access_Token( [ 'user_id' => 10 ] ) )->verify( $token ) );
		self::assertFalse( ( new Access_Token( [ 'user_id' => 10 ] ) )->verify( $token_with_expiry ) );
	}
}
