<?php

namespace WP_Job_Manager;

use WP_UnitTestCase;

class WP_Test_Access_Token extends WP_UnitTestCase {

	public function test_correct_token_verified_succesfully() {
		$access_token = new Access_Token( [ 'user_id' => 10 ] );
		$token = $access_token->create();

		self::assertTrue( ( new Access_Token( [ 'user_id' => 10] ))->verify( $token ) );
		self::assertFalse( ( new Access_Token( [ 'user_id' => 10] ))->verify( 'aRandomString' ) );
	}

	public function test_expired_token_not_verified() {
		$access_token = new Access_Token( [ 'user_id' => 10 ], time() - 1000 );
		$token = $access_token->create();

		self::assertFalse( ( new Access_Token( [ 'user_id' => 10] ))->verify( $token ) );
		self::assertTrue( ( new Access_Token( [ 'user_id' => 10] ))->is_expired() );
	}

	public function test_incorrect_payload_not_verified() {
		$access_token = new Access_Token( [ 'user_id' => 10 ], time() + 1000 );
		$token = $access_token->create();

		self::assertFalse( ( new Access_Token( [ 'user_id' => 10 ] ) )->is_expired() );
		self::assertTrue( ( new Access_Token( [ 'user_id' => 10 ] ))->verify( $token ) );
		self::assertFalse( ( new Access_Token( [ 'user_id' => 110 ] ) )->verify( $token ) );
	}
}
