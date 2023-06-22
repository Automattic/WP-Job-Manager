<?php

require_once JOB_MANAGER_PLUGIN_DIR . '/includes/helper/class-wp-job-manager-com-auth-token.php';

/**
 * @group helper
 * @group helper-base
 */
class WP_Test_WP_Job_Manager_Com_Auth_Token extends WPJM_BaseTest {

	public function testInstance_WhenCalled_ReturnSameInstance() {
		// Arrange.
		$instance  = WP_Job_Manager_Com_Auth_Token::instance();

		// Act.

		// Assert.
		$this->assertInstanceOf( 'WP_Job_Manager_Com_Auth_Token', $instance );
	}

	public function testInstance_WhenCalled_ReturnCorrectType() {
		// Arrange.
		$instance  = WP_Job_Manager_Com_Auth_Token::instance();
		$instance2 = WP_Job_Manager_Com_Auth_Token::instance();

		// Act.

		// Assert.
		$this->assertSame( $instance2, $instance );
	}

	public function testGenerate_WhenPassedInvalidObjectType_ShouldReturnError() {
		// Arrange.
		$instance = WP_Job_Manager_Com_Auth_Token::instance();

		// Act.
		$result = $instance->generate( 'comment', 1);

		// Assert.
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( 'wpjobmanager-com-invalid-type' , $result->get_error_code() );
	}


	public function testGenerate_WhenPassedInvalidObjectID_ShouldReturnError() {
		// Arrange.
		$instance = WP_Job_Manager_Com_Auth_Token::instance();

		// Act.
		$result = $instance->generate( 'user', "test");

		// Assert.
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( 'wpjobmanager-com-token-not-saved' , $result->get_error_code() );
	}

	public function testGenerate_WhenPassedUser_ShouldPersistMeta() {
		// Arrange.
		$instance = WP_Job_Manager_Com_Auth_Token::instance();
		$user = $this->factory->user->create_and_get();

		// Act.
		$result = $instance->generate( 'user', $user->ID);

		// Assert.
		$this->assertIsString( $result );
		$this->assertNotEmpty( get_user_meta( $user->ID, WP_Job_Manager_Com_Auth_Token::META_KEY ) );
	}

	public function testGenerate_WhenPassedPost_ShouldPersistMeta() {
		// Arrange.
		$instance = WP_Job_Manager_Com_Auth_Token::instance();
		$post = $this->factory->post->create_and_get();

		// Act.
		$result = $instance->generate( 'post', $post->ID);

		// Assert.
		$this->assertIsString( $result );
		$this->assertNotEmpty( get_post_meta( $post->ID, WP_Job_Manager_Com_Auth_Token::META_KEY ) );
	}

	public function testValidate_WhenPassedInvalidObjectType_ShouldReturnFalse() {
		// Arrange.
		$instance = WP_Job_Manager_Com_Auth_Token::instance();

		// Act.
		$result = $instance->validate( 'comment', 1, 'test' );

		// Assert.
		$this->assertFalse( $result );
	}

	public function testValidate_WhenPassedInvalidObjectID_ShouldReturnFalse() {
		// Arrange.
		$instance = WP_Job_Manager_Com_Auth_Token::instance();

		// Act.
		$result = $instance->validate( 'user', 'test', 'test' );

		// Assert.
		$this->assertFalse( $result );
	}

	public function testValidate_WhenPassedUserWithoutMeta_ShouldReturnFalse() {
		// Arrange.
		$instance = WP_Job_Manager_Com_Auth_Token::instance();
		$user = $this->factory->user->create_and_get();

		// Act.
		$result = $instance->validate( 'user', $user->ID, 'test' );

		// Assert.
		$this->assertFalse( $result );
	}

	public function testValidate_WhenPassedUserWithMetaButInvalidToken_ShouldReturnFalse() {
		// Arrange.
		$instance = WP_Job_Manager_Com_Auth_Token::instance();
		$user = $this->factory->user->create_and_get();
		$token = $instance->generate('user', $user->ID);

		// Act.
		$result = $instance->validate( 'user', $user->ID, $token. 'a' );

		// Assert.
		$this->assertFalse( $result );
	}

	public function testValidate_WhenPassedPostWithMetaButInvalidToken_ShouldReturnFalse() {
		// Arrange.
		$instance = WP_Job_Manager_Com_Auth_Token::instance();
		$post = $this->factory->post->create_and_get();
		$token = $instance->generate('post', $post->ID);

		// Act.
		$result = $instance->validate( 'post', $post->ID, $token. 'a' );

		// Assert.
		$this->assertFalse( $result );
	}

	public function testValidate_WhenPassedUserWithMetaAndValidTokenTwice_ShouldReturnFalse() {
		// Arrange.
		$instance = WP_Job_Manager_Com_Auth_Token::instance();
		$user = $this->factory->user->create_and_get();
		$token = $instance->generate('user', $user->ID);

		// Act.
		$instance->validate( 'user', $user->ID, $token );
		$result = $instance->validate( 'user', $user->ID, $token );

		// Assert.
		$this->assertFalse( $result );
	}

	public function testValidate_WhenPassedPostWithMetaAndValidTokenTwice_ShouldReturnFalse() {
		// Arrange.
		$instance = WP_Job_Manager_Com_Auth_Token::instance();
		$post = $this->factory->post->create_and_get();
		$token = $instance->generate('post', $post->ID);

		// Act.
		$instance->validate( 'post', $post->ID, $token );
		$result = $instance->validate( 'post', $post->ID, $token );

		// Assert.
		$this->assertFalse( $result );
	}

	public function testValidate_WhenCalledWithUser_ShouldDeleteExpiredTokens() {
		// Arrange.
		$instance = WP_Job_Manager_Com_Auth_Token::instance();
		$user = $this->factory->user->create_and_get();
		$instance->generate('user', $user->ID);
		$this->expire_tokens( 'user', $user->ID );

		// Act.
		$instance->validate( 'user', $user->ID, 'test' );

		// Assert.
		$this->assertEmpty( get_metadata( 'user', $user->ID, WP_Job_Manager_Com_Auth_Token::META_KEY ) );
	}

	public function testValidate_WhenCalledWithPost_ShouldDeleteExpiredTokens() {
		// Arrange.
		$instance = WP_Job_Manager_Com_Auth_Token::instance();
		$post = $this->factory->post->create_and_get();
		$instance->generate('post', $post->ID);
		$this->expire_tokens( 'post', $post->ID );

		// Act.
		$instance->validate( 'post', $post->ID, 'test' );

		// Assert.
		$this->assertEmpty( get_metadata( 'user', $post->ID, WP_Job_Manager_Com_Auth_Token::META_KEY ) );
	}

	public function testValidate_WhenPassedValidUserButTokenIsExpired_ShouldReturnFalse() {
		// Arrange.
		$instance = WP_Job_Manager_Com_Auth_Token::instance();
		$user = $this->factory->user->create_and_get();
		$token = $instance->generate('user', $user->ID);
		$this->expire_tokens( 'user', $user->ID );

		// Act.
		$result = $instance->validate( 'user', $user->ID, $token );

		// Assert.
		$this->assertFalse( $result );
	}

	public function testValidate_WhenPassedValidPostButTokenIsExpired_ShouldReturnFalse() {
		// Arrange.
		$instance = WP_Job_Manager_Com_Auth_Token::instance();
		$post = $this->factory->post->create_and_get();
		$token = $instance->generate('post', $post->ID);
		$this->expire_tokens( 'post', $post->ID );

		// Act.
		$result = $instance->validate( 'post', $post->ID, $token );

		// Assert.
		$this->assertFalse( $result );
	}

	private function expire_tokens ( $object_type, $object_id ) {
		$metadatas = get_metadata( $object_type, $object_id, WP_Job_Manager_Com_Auth_Token::META_KEY );
		foreach ( $metadatas as $metadata ) {
			$new_metadata = [
				'token' => $metadata['token'],
				'ts' => $metadata['ts'] - HOUR_IN_SECONDS
			];
			update_metadata( $object_type, $object_id, WP_Job_Manager_Com_Auth_Token::META_KEY, $new_metadata, $metadata);
		}
	}

	public function testValidate_WhenPassedUserWithMetaAndValidToken_ShouldReturnTrue() {
		// Arrange.
		$instance = WP_Job_Manager_Com_Auth_Token::instance();
		$user = $this->factory->user->create_and_get();
		$token = $instance->generate('user', $user->ID);

		// Act.
		$result = $instance->validate( 'user', $user->ID, $token );

		// Assert.
		$this->assertTrue( $result );
	}

	public function testValidate_WhenPassedPostWithMetaAndValidToken_ShouldReturnTrue() {
		// Arrange.
		$instance = WP_Job_Manager_Com_Auth_Token::instance();
		$post = $this->factory->post->create_and_get();
		$token = $instance->generate('post', $post->ID);

		// Act.
		$result = $instance->validate( 'post', $post->ID, $token );

		// Assert.
		$this->assertTrue( $result );
	}
}
