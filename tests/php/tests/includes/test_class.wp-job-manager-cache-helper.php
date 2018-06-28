<?php

class WP_Test_WP_Job_Manager_Cache_Helper extends WPJM_BaseTest {
	const GROUP_CACHE_KEY_JOB_LISTINGS     = 'get_job_listings';
	const GROUP_CACHE_KEY_PREFIX_JOB_TERMS = 'jm_get_';


	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Cache_Helper::flush_get_job_listings_cache
	 */
	public function test_flush_get_job_listings_cache_explicit_trigger() {
		$post = $this->factory->job_listing->create();

		$initial_version = WP_Job_Manager_Cache_Helper::get_transient_version( self::GROUP_CACHE_KEY_JOB_LISTINGS );
		$this->assertGreaterThanOrEqual( time() - 1, $initial_version );
		$this->assertLessThanOrEqual( time(), $initial_version );

		$this->wee_sleep();
		$middle_version = WP_Job_Manager_Cache_Helper::get_transient_version( self::GROUP_CACHE_KEY_JOB_LISTINGS );
		$this->assertEquals( $initial_version, $middle_version );

		// Manually trigger it.
		WP_Job_Manager_Cache_Helper::flush_get_job_listings_cache( $post );

		$after_version = WP_Job_Manager_Cache_Helper::get_transient_version( self::GROUP_CACHE_KEY_JOB_LISTINGS );
		$this->assertLessThan( $after_version, $initial_version );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Cache_Helper::flush_get_job_listings_cache
	 */
	public function test_flush_get_job_listings_cache_action_trigger() {
		/**
		 * @var WP_Post $post
		 */
		$post = get_post( $this->factory->job_listing->create() );

		$initial_version = WP_Job_Manager_Cache_Helper::get_transient_version( self::GROUP_CACHE_KEY_JOB_LISTINGS );
		$this->assertGreaterThanOrEqual( time() - 1, $initial_version );
		$this->assertLessThanOrEqual( time(), $initial_version );

		$this->wee_sleep();
		$middle_version = WP_Job_Manager_Cache_Helper::get_transient_version( self::GROUP_CACHE_KEY_JOB_LISTINGS );
		$this->assertEquals( $initial_version, $middle_version );

		// This should trigger `WP_Job_Manager_Cache_Helper::flush_get_job_listings_cache()`.
		$post->post_title = 'Cool Dinosaur';
		wp_update_post( $post );

		$after_version = WP_Job_Manager_Cache_Helper::get_transient_version( self::GROUP_CACHE_KEY_JOB_LISTINGS );
		$this->assertLessThan( $after_version, $initial_version );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Cache_Helper::flush_get_job_listings_cache
	 */
	public function test_flush_get_job_listings_cache_action_bad_trigger() {
		/**
		 * @var WP_Post $post
		 */
		$post = get_post( $this->factory->post->create() );

		$initial_version = WP_Job_Manager_Cache_Helper::get_transient_version( self::GROUP_CACHE_KEY_JOB_LISTINGS );
		$this->assertGreaterThanOrEqual( time() - 1, $initial_version );
		$this->assertLessThanOrEqual( time(), $initial_version );

		$this->wee_sleep();
		$middle_version = WP_Job_Manager_Cache_Helper::get_transient_version( self::GROUP_CACHE_KEY_JOB_LISTINGS );
		$this->assertEquals( $initial_version, $middle_version );

		// This should NOT trigger `WP_Job_Manager_Cache_Helper::flush_get_job_listings_cache()`.
		$post->post_title = 'Cool Dinosaur';
		wp_update_post( $post );

		$after_version = WP_Job_Manager_Cache_Helper::get_transient_version( self::GROUP_CACHE_KEY_JOB_LISTINGS );
		$this->assertEquals( $after_version, $initial_version );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Cache_Helper::job_manager_my_job_do_action
	 */
	public function test_job_manager_my_job_do_action() {
		$initial_version = WP_Job_Manager_Cache_Helper::get_transient_version( self::GROUP_CACHE_KEY_JOB_LISTINGS );
		$this->assertGreaterThanOrEqual( time() - 1, $initial_version );
		$this->assertLessThanOrEqual( time(), $initial_version );

		$this->wee_sleep();
		$middle_version = WP_Job_Manager_Cache_Helper::get_transient_version( self::GROUP_CACHE_KEY_JOB_LISTINGS );
		$this->assertEquals( $initial_version, $middle_version );

		// Manually trigger with bad action.
		WP_Job_Manager_Cache_Helper::job_manager_my_job_do_action( 'bad_action' );
		$after_version = WP_Job_Manager_Cache_Helper::get_transient_version( self::GROUP_CACHE_KEY_JOB_LISTINGS );
		$this->assertEquals( $after_version, $initial_version );

		// Manually trigger with good action 'mark_filled'.
		WP_Job_Manager_Cache_Helper::job_manager_my_job_do_action( 'mark_filled' );
		$after_version_mark_filled = WP_Job_Manager_Cache_Helper::get_transient_version( self::GROUP_CACHE_KEY_JOB_LISTINGS );
		$this->assertLessThan( $after_version_mark_filled, $initial_version );

		$this->wee_sleep();

		// Manually trigger with good action 'mark_not_filled'.
		WP_Job_Manager_Cache_Helper::job_manager_my_job_do_action( 'mark_not_filled' );
		$after_version_mark_not_filled = WP_Job_Manager_Cache_Helper::get_transient_version( self::GROUP_CACHE_KEY_JOB_LISTINGS );
		$this->assertLessThan( $after_version_mark_not_filled, $after_version_mark_filled );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Cache_Helper::set_term
	 */
	public function test_set_term() {
		/**
		 * @var WP_Post $post
		 */
		$post = get_post( $this->factory->post->create() );

		/**
		 * @var WP_Term $term
		 */
		$term = get_term( $this->factory->term->create() );

		$taxonomy_slug     = 'post_tag';
		$cache_key         = self::GROUP_CACHE_KEY_PREFIX_JOB_TERMS . sanitize_text_field( $taxonomy_slug );
		$taxonomy_term_ids = array( $term->ID );

		$initial_version = WP_Job_Manager_Cache_Helper::get_transient_version( $cache_key );
		$this->assertGreaterThanOrEqual( time() - 1, $initial_version );
		$this->assertLessThanOrEqual( time(), $initial_version );

		$this->wee_sleep();
		$middle_version = WP_Job_Manager_Cache_Helper::get_transient_version( $cache_key );
		$this->assertEquals( $initial_version, $middle_version );

		// wp_set_object_terms( $post->ID, $term->ID, $taxonomy_slug );.
		WP_Job_Manager_Cache_Helper::set_term( $post->ID, 'a_test_term', array( $term ), $taxonomy_slug );

		$after_version = WP_Job_Manager_Cache_Helper::get_transient_version( $cache_key );
		$this->assertLessThan( $after_version, $initial_version );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Cache_Helper::edited_term
	 */
	public function test_edited_term() {
		/**
		 * @var WP_Post $post
		 */
		$post = get_post( $this->factory->post->create() );

		/**
		 * @var WP_Term $term
		 */
		$term = get_term( $this->factory->term->create() );

		$taxonomy_slug     = 'post_tag';
		$cache_key         = self::GROUP_CACHE_KEY_PREFIX_JOB_TERMS . sanitize_text_field( $taxonomy_slug );
		$taxonomy_term_ids = array( $term->ID );

		$initial_version = WP_Job_Manager_Cache_Helper::get_transient_version( $cache_key );
		$this->assertGreaterThanOrEqual( time() - 1, $initial_version );
		$this->assertLessThanOrEqual( time(), $initial_version );

		$this->wee_sleep();
		$middle_version = WP_Job_Manager_Cache_Helper::get_transient_version( $cache_key );
		$this->assertEquals( $initial_version, $middle_version );

		WP_Job_Manager_Cache_Helper::edited_term( $post->ID, array( $term ), $taxonomy_slug );

		$after_version = WP_Job_Manager_Cache_Helper::get_transient_version( $cache_key );
		$this->assertLessThan( $after_version, $initial_version );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Cache_Helper::get_transient_version
	 */
	public function test_get_transient_version() {
		$test_group      = 'test_group';
		$initial_version = WP_Job_Manager_Cache_Helper::get_transient_version( $test_group );
		$this->assertGreaterThanOrEqual( time() - 1, $initial_version );
		$this->assertLessThanOrEqual( time(), $initial_version );

		$this->wee_sleep();
		$middle_version = WP_Job_Manager_Cache_Helper::get_transient_version( $test_group );
		$this->assertEquals( $initial_version, $middle_version );

		$refresh_version = WP_Job_Manager_Cache_Helper::get_transient_version( $test_group, true );
		$this->assertLessThan( $refresh_version, $initial_version );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Cache_Helper::get_listings_count
	 */
	public function test_get_listings_count_default_args() {
		global $wpdb;
		$posts_pending   = $this->factory->job_listing->create_many( 3, array( 'post_status' => 'pending' ) );
		$posts_published = $this->factory->job_listing->create_many( 5, array( 'post_status' => 'publish' ) );
		$posts_shifted   = array();

		$initial_count = WP_Job_Manager_Cache_Helper::get_listings_count();
		$this->assertEquals( count( $posts_pending ), $initial_count );

		$this->wee_sleep();

		// Create posts with normal actions fired.
		$posts_pending_round_two = $this->factory->job_listing->create_many( 2, array( 'post_status' => 'pending' ) );

		$middle_count = WP_Job_Manager_Cache_Helper::get_listings_count();
		$this->assertEquals( count( $posts_pending ) + count( $posts_pending_round_two ), $middle_count );

		// Covertly change post from published to pending.
		$this->wee_sleep();
		$published_post_id = $posts_shifted[] = array_pop( $posts_published );
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $published_post_id ) );

		$second_middle_count = WP_Job_Manager_Cache_Helper::get_listings_count();
		$this->assertEquals( $middle_count, $second_middle_count );

		// Call the function that should have fired earlier.
		WP_Job_Manager_Cache_Helper::maybe_clear_count_transients( 'pending', 'publish', get_post( $published_post_id ) );

		$final_count = WP_Job_Manager_Cache_Helper::get_listings_count();
		$this->assertEquals( count( $posts_pending ) + count( $posts_pending_round_two ) + count( $posts_shifted ), $final_count );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Cache_Helper::get_listings_count
	 */
	public function test_get_listings_count_nonstandard_args() {
		global $wpdb;
		add_filter( 'wpjm_count_cache_supported_post_types', array( $this, 'helper_add_post_type' ) );
		add_filter( 'wpjm_count_cache_supported_statuses', array( $this, 'helper_add_pending_status' ) );
		$posts_pending   = $this->factory->post->create_many( 4, array( 'post_status' => 'pending' ) );
		$posts_published = $this->factory->post->create_many( 2, array( 'post_status' => 'publish' ) );
		$posts_shifted   = array();

		$initial_count = WP_Job_Manager_Cache_Helper::get_listings_count( 'post', 'publish' );
		$this->assertEquals( count( $posts_published ), $initial_count );

		$this->wee_sleep();

		// Create posts with normal actions fired.
		$posts_published_round_two = $this->factory->post->create_many( 2, array( 'post_status' => 'publish' ) );

		$middle_count = WP_Job_Manager_Cache_Helper::get_listings_count( 'post', 'publish' );
		$this->assertEquals( count( $posts_published ) + count( $posts_published_round_two ), $middle_count );

		// Covertly change post from published to pending.
		$this->wee_sleep();
		$published_post_id = $posts_shifted[] = array_pop( $posts_published );
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $published_post_id ) );

		$second_middle_count = WP_Job_Manager_Cache_Helper::get_listings_count( 'post', 'publish' );
		$this->assertEquals( $middle_count, $second_middle_count );

		$final_count = WP_Job_Manager_Cache_Helper::get_listings_count( 'post', 'publish', true );
		$this->assertEquals( count( $posts_published ) + count( $posts_published_round_two ) + count( $posts_shifted ), $final_count );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Cache_Helper::maybe_clear_count_transients
	 */
	public function test_maybe_clear_count_transients() {
		global $wpdb;
		$posts_pending   = $this->factory->job_listing->create_many( 3, array( 'post_status' => 'pending' ) );
		$posts_published = $this->factory->job_listing->create_many( 5, array( 'post_status' => 'publish' ) );
		$posts_expired   = $this->factory->job_listing->create_many( 5, array( 'post_status' => 'expired' ) );
		$posts_shifted   = array();

		$expired_count   = WP_Job_Manager_Cache_Helper::get_listings_count( 'job_listing', 'expired' );
		$published_count = WP_Job_Manager_Cache_Helper::get_listings_count( 'job_listing', 'publish' );
		$initial_count   = WP_Job_Manager_Cache_Helper::get_listings_count();
		$this->assertEquals( count( $posts_pending ), $initial_count );

		// Covertly change post from published to pending.
		$this->wee_sleep();
		$published_post_id = $posts_shifted[] = array_pop( $posts_published );
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $published_post_id ) );

		$published_to_expired_post_id = array_pop( $posts_published );
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'expired' ), array( 'ID' => $published_to_expired_post_id ) );

		$second_middle_count = WP_Job_Manager_Cache_Helper::get_listings_count();
		$this->assertEquals( count( $posts_pending ), $second_middle_count );

		// Unhandled status.
		WP_Job_Manager_Cache_Helper::maybe_clear_count_transients( 'expired', 'publish', get_post( $published_post_id ) );
		$middle_expired_count = WP_Job_Manager_Cache_Helper::get_listings_count( 'job_listing', 'expired' );
		$this->assertEquals( $expired_count, $middle_expired_count );

		add_filter( 'wpjm_count_cache_supported_statuses', array( $this, 'helper_add_expired_status' ) );

		WP_Job_Manager_Cache_Helper::maybe_clear_count_transients( 'expired', 'publish', get_post( $published_post_id ) );
		$second_middle_expired_count = WP_Job_Manager_Cache_Helper::get_listings_count( 'job_listing', 'expired' );
		$this->assertLessThan( $second_middle_expired_count, $middle_expired_count );

		// Legit call for method.
		WP_Job_Manager_Cache_Helper::maybe_clear_count_transients( 'pending', 'publish', get_post( $published_post_id ) );

		$final_count = WP_Job_Manager_Cache_Helper::get_listings_count();
		$this->assertEquals( count( $posts_pending ) + count( $posts_shifted ), $final_count );
	}


	public function helper_add_expired_status( $post_status ) {
		$post_status[] = 'expired';
		return $post_status;
	}

	public function helper_add_pending_status( $post_status ) {
		$post_status[] = 'publish';
		return $post_status;
	}

	public function helper_add_post_type( $post_types ) {
		$post_types[] = 'post';
		return $post_types;
	}

	private function wee_sleep() {
		$sec = $bump_sec = time();
		while ( $sec === $bump_sec ) {
			usleep( 10000 );
			$bump_sec = time();
		}
	}
}
