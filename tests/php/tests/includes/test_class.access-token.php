<?php

namespace WP_Job_Manager;

use ReflectionObject;
use WP_UnitTestCase;

class WP_Test_Access_Token extends WP_UnitTestCase {

	public function test_correct_token_verified_succesfully() {
		$access_token = new Access_Token( [ 'user_id' => 10 ] );
		$token = $access_token->create();

		self::assertTrue( ( new Access_Token( [ 'user_id' => 10] ))->verify( $token, 1 ) );
		self::assertFalse( ( new Access_Token( [ 'user_id' => 10] ))->verify( 'aRandomString', 1 ) );
	}

	public function test_expired_token_not_verified() {
		$access_token = new Access_Token( [ 'user_id' => 10 ] );
		$token = $access_token->create();

		$generate_token = ( new ReflectionObject( $access_token ) )->getMethod( 'generate_token' );
		$generate_token->setAccessible( true );
		$tick = wp_nonce_tick();

		$token_12_hours = $generate_token->invoke( $access_token, $tick - 1 );
		$token_24_hours = $generate_token->invoke( $access_token, $tick - 2 );
		$token_36_hours = $generate_token->invoke( $access_token, $tick - 3 );
		$token_48_hours = $generate_token->invoke( $access_token, $tick - 4 );
		$token_60_hours = $generate_token->invoke( $access_token, $tick - 5 );

		self::assertTrue( ( new Access_Token( [ 'user_id' => 10] ))->verify( $token, 2 ) );
		self::assertTrue( ( new Access_Token( [ 'user_id' => 10] ))->verify( $token_12_hours, 2 ) );
		self::assertTrue( ( new Access_Token( [ 'user_id' => 10] ))->verify( $token_24_hours, 2 ) );
		self::assertTrue( ( new Access_Token( [ 'user_id' => 10] ))->verify( $token_36_hours, 2 ) );
		self::assertFalse( ( new Access_Token( [ 'user_id' => 10] ))->verify( $token_48_hours, 2 ) );
		self::assertFalse( ( new Access_Token( [ 'user_id' => 10] ))->verify( $token_60_hours, 2 ) );
	}

	public function test_incorrect_payload_not_verified() {
		$access_token = new Access_Token( [ 'user_id' => 10, 'email' => 'email@email.com' ] );
		$token = $access_token->create();

		self::assertTrue( ( new Access_Token( [ 'email' => 'email@email.com', 'user_id' => 10 ] ))->verify( $token, 1 ) );
		self::assertFalse( ( new Access_Token( [ 'user_id' => 110, 'email' => 'email@email.com' ] ) )->verify( $token, 1 ) );
	}
}
