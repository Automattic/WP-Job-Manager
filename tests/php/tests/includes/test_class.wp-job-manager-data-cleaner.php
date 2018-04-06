<?php

require 'includes/class-wp-job-manager-data-cleaner.php';

class WP_Job_Manager_Data_Cleaner_Test extends WP_UnitTestCase {
	// Posts.
	private $post_ids;
	private $biography_ids;
	private $job_listing_ids;

	/**
	 * Add some posts to run tests against. Any that are associated with WPJM
	 * should be trashed on cleanup. The others should not be trashed.
	 */
	private function setupPosts() {
		// Create some regular posts.
		$this->post_ids = $this->factory->post->create_many( 2, array(
			'post_status' => 'publish',
			'post_type'   => 'post',
		) );

		// Create an unrelated CPT to ensure its posts do not get deleted.
		register_post_type( 'biography', array(
			'label'       => 'Biographies',
			'description' => 'A biography of a famous person (for testing)',
			'public'      => true,
		) );
		$this->biography_ids = $this->factory->post->create_many( 4, array(
			'post_status' => 'publish',
			'post_type'   => 'biography',
		) );

		// Create some Job Listings.
		$this->job_listing_ids = $this->factory->post->create_many( 8, array(
			'post_status' => 'publish',
			'post_type'   => 'job_listing',
		) );
	}

	/**
	 * Set up for tests.
	 */
	public function setUp() {
		parent::setUp();

		$this->setupPosts();
	}

	/**
	 * Ensure the WPJM posts are moved to trash.
	 *
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_all
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_custom_post_types
	 */
	public function testJobManagerPostsTrashed() {
		WP_Job_Manager_Data_Cleaner::cleanup_all();

		foreach ( $this->job_listing_ids as $id ) {
			$post = get_post( $id );
			$this->assertEquals( 'trash', $post->post_status, 'WPJM post should be trashed' );
		}
	}

	/**
	 * Ensure the non-WPJM posts are not moved to trash.
	 *
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_all
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_custom_post_types
	 */
	public function testOtherPostsUntouched() {
		WP_Job_Manager_Data_Cleaner::cleanup_all();

		$ids = array_merge( $this->post_ids, $this->biography_ids );
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			$this->assertNotEquals( 'trash', $post->post_status, 'Non-WPJM post should not be trashed' );
		}
	}
}
