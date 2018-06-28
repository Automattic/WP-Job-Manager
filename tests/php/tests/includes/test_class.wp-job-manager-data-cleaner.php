<?php

require 'includes/class-wp-job-manager-data-cleaner.php';

class WP_Job_Manager_Data_Cleaner_Test extends WP_UnitTestCase {
	// Posts.
	private $post_ids;
	private $biography_ids;
	private $job_listing_ids;

	// Taxonomies.
	private $job_listing_types;
	private $categories;
	private $ages;

	// Pages.
	private $regular_page_ids;
	private $submit_job_form_page_id;
	private $job_dashboard_page_id;
	private $jobs_page_id;

	// Users.
	private $regular_user_id;
	private $employer_user_id;

	/**
	 * Add some posts to run tests against. Any that are associated with WPJM
	 * should be trashed on cleanup. The others should not be trashed.
	 */
	private function setupPosts() {
		// Create some regular posts.
		$this->post_ids = $this->factory->post->create_many(
			2, array(
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		// Create an unrelated CPT to ensure its posts do not get deleted.
		register_post_type(
			'biography', array(
				'label'       => 'Biographies',
				'description' => 'A biography of a famous person (for testing)',
				'public'      => true,
			)
		);
		$this->biography_ids = $this->factory->post->create_many(
			4, array(
				'post_status' => 'publish',
				'post_type'   => 'biography',
			)
		);

		// Create some Job Listings.
		$this->job_listing_ids = $this->factory->post->create_many(
			8, array(
				'post_status' => 'publish',
				'post_type'   => 'job_listing',
			)
		);
	}

	/**
	 * Add some taxonomies to run tests against. Any that are associated with
	 * WPJM should be deleted on cleanup. The others should not be deleted.
	 */
	private function setupTaxonomyTerms() {
		// Setup some job types.
		$this->job_listing_types = array();

		for ( $i = 1; $i <= 3; $i++ ) {
			$this->job_listing_types[] = wp_insert_term( 'Job Type ' . $i, 'job_listing_type' );
		}

		wp_set_object_terms(
			$this->course_ids[0],
			array(
				$this->job_listing_types[0]['term_id'],
				$this->job_listing_types[1]['term_id'],
			),
			'job_listing_type'
		);
		wp_set_object_terms(
			$this->course_ids[1],
			array(
				$this->job_listing_types[1]['term_id'],
				$this->job_listing_types[2]['term_id'],
			),
			'job_listing_type'
		);
		wp_set_object_terms(
			$this->course_ids[2],
			array(
				$this->job_listing_types[0]['term_id'],
				$this->job_listing_types[1]['term_id'],
				$this->job_listing_types[2]['term_id'],
			),
			'job_listing_type'
		);

		// Setup some categories.
		$this->categories = array();

		for ( $i = 1; $i <= 3; $i++ ) {
			$this->categories[] = wp_insert_term( 'Category ' . $i, 'category' );
		}

		wp_set_object_terms(
			$this->course_ids[0],
			array(
				$this->categories[0]['term_id'],
				$this->categories[1]['term_id'],
			),
			'category'
		);
		wp_set_object_terms(
			$this->post_ids[0],
			array(
				$this->categories[1]['term_id'],
				$this->categories[2]['term_id'],
			),
			'category'
		);
		wp_set_object_terms(
			$this->biography_ids[2],
			array(
				$this->categories[0]['term_id'],
				$this->categories[1]['term_id'],
				$this->categories[2]['term_id'],
			),
			'category'
		);

		// Setup a custom taxonomy.
		register_taxonomy( 'age', 'biography' );

		$this->ages = array(
			wp_insert_term( 'Old', 'age' ),
			wp_insert_term( 'New', 'age' ),
		);

		wp_set_object_terms( $this->biography_ids[0], $this->ages[0]['term_id'], 'age' );
		wp_set_object_terms( $this->biography_ids[1], $this->ages[1]['term_id'], 'age' );

		// Add a piece of termmeta for every term.
		$terms = array_merge( $this->job_listing_types, $this->categories, $this->ages );
		foreach ( $terms as $term ) {
			$key   = 'the_term_id';
			$value = 'The ID is ' . $term['term_id'];
			update_term_meta( $term['term_id'], $key, $value );
		}
	}

	/**
	 * Add some pages to run tests against. Any that are associated with WPJM
	 * should be trashed on cleanup. The others should not be trashed.
	 */
	private function setupPages() {
		// Create some regular pages.
		$this->regular_page_ids = $this->factory->post->create_many(
			2, array(
				'post_type'  => 'page',
				'post_title' => 'Normal page',
			)
		);

		// Create the Submit Job page.
		$this->submit_job_form_page_id = $this->factory->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Submit Job Page',
			)
		);
		update_option( 'job_manager_submit_job_form_page_id', $this->submit_job_form_page_id );

		// Create the Job Dashboard page.
		$this->job_dashboard_page_id = $this->factory->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Job Dashboard Page',
			)
		);
		update_option( 'job_manager_job_dashboard_page_id', $this->job_dashboard_page_id );

		// Create the Submit Job page.
		$this->jobs_page_id = $this->factory->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Jobs Page',
			)
		);
		update_option( 'job_manager_jobs_page_id', $this->jobs_page_id );
	}

	/**
	 * Add some users to run tests against. The roles and capabilities
	 * associated with WPJM should be deleted on cleanup. The others should
	 * not be deleted.
	 */
	private function setupUsers() {
		// Ensure the role is created.
		WP_Job_Manager_Install::install();

		// Create a regular user and assign some caps.
		$this->regular_user_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$regular_user          = get_user_by( 'id', $this->regular_user_id );
		$regular_user->add_cap( 'edit_others_posts' );
		$regular_user->add_cap( 'manage_job_listings' );

		// Create a teacher user and assign some caps.
		$this->employer_user_id = $this->factory->user->create( array( 'role' => 'employer' ) );
		$employer_user          = get_user_by( 'id', $this->employer_user_id );
		$employer_user->add_cap( 'edit_others_posts' );
		$employer_user->add_cap( 'manage_job_listings' );

		// Add a WPJM cap to an existing role.
		$role = get_role( 'editor' );
		$role->add_cap( 'manage_job_listing' );
	}

	/**
	 * Set up for tests.
	 */
	public function setUp() {
		parent::setUp();

		$this->setupPosts();
		$this->setupTaxonomyTerms();
		$this->setupPages();
		$this->setupUsers();
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

	/**
	 * Ensure the data for WPJM taxonomies and terms are deleted.
	 *
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_all
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_taxonomies
	 */
	public function testJobManagerTaxonomiesDeleted() {
		global $wpdb;

		WP_Job_Manager_Data_Cleaner::cleanup_all();

		foreach ( $this->job_listing_types as $job_listing_type ) {
			$term_id          = $job_listing_type['term_id'];
			$term_taxonomy_id = $job_listing_type['term_taxonomy_id'];

			// Ensure the data is deleted from all the relevant DB tables.
			$this->assertEquals(
				array(), $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * from $wpdb->termmeta WHERE term_id = %s",
						$term_id
					)
				), 'WPJM term meta should be deleted'
			);

			$this->assertEquals(
				array(), $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * from $wpdb->terms WHERE term_id = %s",
						$term_id
					)
				), 'WPJM term should be deleted'
			);

			$this->assertEquals(
				array(), $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * from $wpdb->term_taxonomy WHERE term_taxonomy_id = %s",
						$term_taxonomy_id
					)
				), 'WPJM term taxonomy should be deleted'
			);

			$this->assertEquals(
				array(), $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * from $wpdb->term_relationships WHERE term_taxonomy_id = %s",
						$term_taxonomy_id
					)
				), 'WPJM term relationships should be deleted'
			);
		}
	}

	/**
	 * Ensure the data for non-WPJM taxonomies and terms are not deleted.
	 *
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_all
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_taxonomies
	 */
	public function testOtherTaxonomiesUntouched() {
		global $wpdb;

		WP_Job_Manager_Data_Cleaner::cleanup_all();

		// Check "Category 1".
		$this->assertEquals(
			array( $this->biography_ids[2] ),
			$this->getPostIdsWithTerm( $this->categories[0]['term_id'], 'category' ),
			'Category 1 should not be deleted'
		);

		// Check "Category 2". Sort the arrays because the ordering doesn't.
		// matter.
		$expected = array( $this->post_ids[0], $this->biography_ids[2] );
		$actual   = $this->getPostIdsWithTerm( $this->categories[1]['term_id'], 'category' );
		sort( $expected );
		sort( $actual );
		$this->assertEquals(
			$expected,
			$actual,
			'Category 2 should not be deleted'
		);

		// Check "Category 3". Sort the arrays because the ordering doesn't.
		// matter.
		$expected = array( $this->post_ids[0], $this->biography_ids[2] );
		$actual   = $this->getPostIdsWithTerm( $this->categories[2]['term_id'], 'category' );
		sort( $expected );
		sort( $actual );
		$this->assertEquals(
			$expected,
			$actual,
			'Category 3 should not be deleted'
		);

		// Check "Old" biographies.
		$this->assertEquals(
			array( $this->biography_ids[0] ),
			$this->getPostIdsWithTerm( $this->ages[0]['term_id'], 'age' ),
			'"Old" should not be deleted'
		);

		// Check "New" biographies.
		$this->assertEquals(
			array( $this->biography_ids[1] ),
			$this->getPostIdsWithTerm( $this->ages[1]['term_id'], 'age' ),
			'"New" should not be deleted'
		);
	}

	/**
	 * Ensure the WPJM pages are trashed, and the other pages are not.
	 *
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_all
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_pages
	 */
	public function testJobManagerPagesTrashed() {
		WP_Job_Manager_Data_Cleaner::cleanup_all();

		$this->assertEquals( 'trash', get_post_status( $this->submit_job_form_page_id ), 'Submit Job page should be trashed' );
		$this->assertEquals( 'trash', get_post_status( $this->job_dashboard_page_id ), 'Job Dashboard page should be trashed' );
		$this->assertEquals( 'trash', get_post_status( $this->jobs_page_id ), 'Jobs page should be trashed' );

		foreach ( $this->regular_page_ids as $page_id ) {
			$this->assertNotEquals( 'trash', get_post_status( $page_id ), 'Regular page should not be trashed' );
		}
	}

	/**
	 * Ensure the WPJM options are deleted and the others aren't.
	 *
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_all
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_options
	 */
	public function testJobManagerOptionsDeleted() {
		// Set a couple WPJM options.
		update_option( 'job_manager_usage_tracking_opt_in_hide', '1' );
		update_option( 'wp_job_manager_version', '1.10.0' );
		update_site_option( 'job_manager_helper', '{}' );

		// Set a couple other options.
		update_option( 'my_option_1', 'Value 1' );
		update_option( 'my_option_2', 'Value 2' );

		WP_Job_Manager_Data_Cleaner::cleanup_all();

		// Ensure the WPJM options are deleted.
		$this->assertFalse( get_option( 'job_manager_usage_tracking_opt_in_hide' ), 'Option job_manager_usage_tracking_opt_in_hide should be deleted' );
		$this->assertFalse( get_option( 'wp_job_manager_version' ), 'Option wp_job_manager_version should be deleted' );
		$this->assertFalse( get_site_option( 'job_manager_helper' ), 'Site option job_manager_helper should be deleted' );

		// Ensure the non-WPJM options are intact.
		$this->assertEquals( 'Value 1', get_option( 'my_option_1' ), 'Option my_option_1 should not be deleted' );
		$this->assertEquals( 'Value 2', get_option( 'my_option_2' ), 'Option my_option_2 should not be deleted' );
	}

	/**
	 * Ensure the WPJM transients are deleted from the DB.
	 *
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_all
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_transients
	 */
	public function testJobManagerTransientsDeleted() {
		set_transient( '_job_manager_activation_redirect', 'value', 3600 );
		set_transient( 'jm_random_transient', 'value', 3600 );
		set_transient( 'other_transient', 'value', 3600 );

		WP_Job_Manager_Data_Cleaner::cleanup_all();

		// Flush transients from cache.
		wp_cache_flush();

		$prefix         = '_transient_';
		$timeout_prefix = '_transient_timeout_';

		// Ensure the transients and their timeouts were deleted.
		$this->assertFalse( get_option( "{$prefix}_job_manager_activation_redirect" ), 'WPJM _job_manager_activation_redirect transient' );
		$this->assertFalse( get_option( "{$timeout_prefix}_job_manager_activation_redirect" ), 'WPJM _job_manager_activation_redirect transient timeout' );
		$this->assertFalse( get_option( "{$prefix}jm_random_transient" ), 'WPJM jm_random_transient transient' );
		$this->assertFalse( get_option( "{$timeout_prefix}jm_random_transient" ), 'WPJM jm_random_transient transient timeout' );

		// Ensure the other transient and its timeout was not deleted.
		$this->assertNotFalse( get_option( "{$prefix}other_transient" ), 'Non-WPJM transient' );
		$this->assertNotFalse( get_option( "{$timeout_prefix}other_transient" ), 'Non-WPJM transient' );
	}

	/**
	 * Ensure the WPJM roles and caps are deleted.
	 *
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_all
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_roles_and_caps
	 */
	public function testJobManagerRolesAndCapsDeleted() {
		WP_Job_Manager_Data_Cleaner::cleanup_all();

		// Refresh user info.
		wp_cache_flush();

		$regular_user = get_user_by( 'id', $this->regular_user_id );
		$this->assertTrue( in_array( 'author', $regular_user->roles, true ), 'Author role should not be removed' );
		$this->assertTrue( $regular_user->has_cap( 'edit_others_posts' ), 'Non-WPJM cap should not be removed from user' );
		$this->assertFalse( $regular_user->has_cap( 'manage_job_listings' ), 'WPJM cap should be removed from user' );

		$employer_user = get_user_by( 'id', $this->employer_user_id );
		$this->assertFalse( in_array( 'employer', $employer_user->roles, true ), 'Employer role should be removed from user' );
		$this->assertFalse( array_key_exists( 'employer', $employer_user->caps ), 'Employer role should be removed from user caps' );
		$this->assertTrue( $employer_user->has_cap( 'edit_others_posts' ), 'Non-WPJM cap should not be removed from employer' );
		$this->assertFalse( $employer_user->has_cap( 'manage_job_listings' ), 'WPJM cap should be removed from employer' );

		$role = get_role( 'editor' );
		$this->assertFalse( $role->has_cap( 'manage_job_listings' ), 'WPJM cap should be removed from role' );

		$role = get_role( 'employer' );
		$this->assertNull( $role, 'Employer role should be removed overall' );
	}

	/**
	 * Ensure the WPJM user meta are deleted from the DB.
	 *
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_all
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_user_meta
	 */
	public function testCleanupUserMeta() {
		$user_id = $this->factory->user->create( array( 'role' => 'author' ) );

		$keep_meta_keys = array(
			'company_logo',
			'company_name',
			'company_website',
			'company_tagline',
			'company_twitter',
			'company_video',
		);

		$remove_meta_keys = array(
			'_company_logo',
			'_company_name',
			'_company_website',
			'_company_tagline',
			'_company_twitter',
			'_company_video',
		);

		foreach ( array_merge( $keep_meta_keys, $remove_meta_keys ) as $meta_key ) {
			update_user_meta( $user_id, $meta_key, 'test_value' );
			$this->assertTrue( 'test_value' === get_user_meta( $user_id, $meta_key, true ) );
		}

		WP_Job_Manager_Data_Cleaner::cleanup_all();
		wp_cache_flush();

		foreach ( $keep_meta_keys as $meta_key ) {
			$this->assertTrue( 'test_value' === get_user_meta( $user_id, $meta_key, true ), sprintf( 'The user meta key "%s" was supposed to be preserved.', $meta_key ) );
		}

		foreach ( $remove_meta_keys as $meta_key ) {
			$this->assertTrue( '' === get_user_meta( $user_id, $meta_key, true ), sprintf( 'The user meta key "%s" was supposed to be removed.', $meta_key ) );
		}
	}

	/**
	 * Ensure the WPJM cron jobs are unscheduled, and all the others are not.
	 *
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_all
	 * @covers WP_Job_Manager_Data_Cleaner::cleanup_cron_jobs
	 */
	public function testJobManagerCronJobsRemoved() {
		$wpjm_jobs     = array(
			'job_manager_check_for_expired_jobs',
			'job_manager_delete_old_previews',
			'job_manager_clear_expired_transients',
			'job_manager_usage_tracking_send_usage_data',
		);
		$non_wpjm_jobs = array(
			'another_job',
			'random_job',
		);

		foreach ( array_merge( $wpjm_jobs, $non_wpjm_jobs ) as $job ) {
			wp_schedule_event( time() + 3600, 'daily', $job );
		}

		WP_Job_Manager_Data_Cleaner::cleanup_all();

		// Ensure the WPJM jobs are no longer scheduled.
		foreach ( $wpjm_jobs as $job ) {
			$this->assertFalse( wp_next_scheduled( $job ), "WPJM Job $job should no longer be scheduled" );
		}

		// Ensure the non-WPJM jobs are no longer scheduled.
		foreach ( $non_wpjm_jobs as $job ) {
			$this->assertNotFalse( wp_next_scheduled( $job ), "Non-WPJM Job $job should still be scheduled" );
		}
	}

	/* Helper functions. */

	private function getPostIdsWithTerm( $term_id, $taxonomy ) {
		return get_posts(
			array(
				'fields'    => 'ids',
				'post_type' => 'any',
				'tax_query' => array(
					array(
						'field'    => 'term_id',
						'terms'    => $term_id,
						'taxonomy' => $taxonomy,
					),
				),
			)
		);
	}
}
