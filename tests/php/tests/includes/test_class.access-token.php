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

		$reflection_object = new ReflectionObject( $access_token );
		$tick_method = $reflection_object->getMethod( 'token_tick' );
		$tick_method->setAccessible(true);
		$tick = $tick_method->invoke( $access_token );

		$generate_token_method = ( new ReflectionObject( $access_token ) )->getMethod( 'generate_token' );
		$generate_token_method->setAccessible( true );

		$token_previous_day = $generate_token_method->invoke( $access_token, $tick - 1 );
		$token_2_days_ago   = $generate_token_method->invoke( $access_token, $tick - 2 );

		self::assertTrue( ( new Access_Token( [ 'user_id' => 10] ))->verify( $token, 2 ) );
		self::assertTrue( ( new Access_Token( [ 'user_id' => 10] ))->verify( $token_previous_day, 2 ) );
		self::assertFalse( ( new Access_Token( [ 'user_id' => 10] ))->verify( $token_2_days_ago, 2 ) );
	}

	public function test_incorrect_payload_not_verified() {
		$access_token = new Access_Token( [ 'user_id' => 10, 'email' => 'email@email.com' ] );
		$token = $access_token->create();

		self::assertTrue( ( new Access_Token( [ 'email' => 'email@email.com', 'user_id' => 10 ] ))->verify( $token, 1 ) );
		self::assertFalse( ( new Access_Token( [ 'user_id' => 110, 'email' => 'email@email.com' ] ) )->verify( $token, 1 ) );
	}
}
