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
	 * Add some taxonomies to run tests against. Any that are associated with
	 * WPJM should be deleted on cleanup. The others should not be deleted.
	 */
	private function setupTaxonomyTerms() {
		// Setup some job types.
		$this->job_listing_types = array();

		for ( $i = 1; $i <= 3; $i++ ) {
			$this->job_listing_types[] = wp_insert_term( 'Job Type ' . $i, 'job_listing_type' );
		}

		wp_set_object_terms( $this->course_ids[0],
			array(
				$this->job_listing_types[0]['term_id'],
				$this->job_listing_types[1]['term_id'],
			),
			'job_listing_type'
		);
		wp_set_object_terms( $this->course_ids[1],
			array(
				$this->job_listing_types[1]['term_id'],
				$this->job_listing_types[2]['term_id'],
			),
			'job_listing_type'
		);
		wp_set_object_terms( $this->course_ids[2],
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

		wp_set_object_terms( $this->course_ids[0],
			array(
				$this->categories[0]['term_id'],
				$this->categories[1]['term_id'],
			),
			'category'
		);
		wp_set_object_terms( $this->post_ids[0],
			array(
				$this->categories[1]['term_id'],
				$this->categories[2]['term_id'],
			),
			'category'
		);
		wp_set_object_terms( $this->biography_ids[2],
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
		$this->regular_page_ids = $this->factory->post->create_many( 2, array(
			'post_type'  => 'page',
			'post_title' => 'Normal page',
		) );

		// Create the Submit Job page.
		$this->submit_job_form_page_id = $this->factory->post->create( array(
			'post_type'  => 'page',
			'post_title' => 'Submit Job Page',
		) );
		update_option( 'job_manager_submit_job_form_page_id', $this->submit_job_form_page_id );

		// Create the Job Dashboard page.
		$this->job_dashboard_page_id = $this->factory->post->create( array(
			'post_type'  => 'page',
			'post_title' => 'Job Dashboard Page',
		) );
		update_option( 'job_manager_job_dashboard_page_id', $this->job_dashboard_page_id );

		// Create the Submit Job page.
		$this->jobs_page_id = $this->factory->post->create( array(
			'post_type'  => 'page',
			'post_title' => 'Jobs Page',
		) );
		update_option( 'job_manager_jobs_page_id', $this->jobs_page_id );
	}

	/**
	 * Set up for tests.
	 */
	public function setUp() {
		parent::setUp();

		$this->setupPosts();
		$this->setupTaxonomyTerms();
		$this->setupPages();
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
			$this->assertEquals( array(), $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * from $wpdb->termmeta WHERE term_id = %s",
					$term_id
				)
			), 'WPJM term meta should be deleted' );

			$this->assertEquals( array(), $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * from $wpdb->terms WHERE term_id = %s",
					$term_id
				)
			), 'WPJM term should be deleted' );

			$this->assertEquals( array(), $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * from $wpdb->term_taxonomy WHERE term_taxonomy_id = %s",
					$term_taxonomy_id
				)
			), 'WPJM term taxonomy should be deleted' );

			$this->assertEquals( array(), $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * from $wpdb->term_relationships WHERE term_taxonomy_id = %s",
					$term_taxonomy_id
				)
			), 'WPJM term relationships should be deleted' );
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

		// Check "Category 2". Sort the arrays because the ordering doesn't
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

		// Check "Category 3". Sort the arrays because the ordering doesn't
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

	/* Helper functions. */

	private function getPostIdsWithTerm( $term_id, $taxonomy ) {
		return get_posts( array(
			'fields'    => 'ids',
			'post_type' => 'any',
			'tax_query' => array(
				array(
					'field'    => 'term_id',
					'terms'    => $term_id,
					'taxonomy' => $taxonomy,
				),
			),
		) );
	}
}
