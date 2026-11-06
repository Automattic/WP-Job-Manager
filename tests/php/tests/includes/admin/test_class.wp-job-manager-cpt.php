<?php

require 'includes/admin/class-wp-job-manager-cpt.php';

class WP_Test_WP_Job_Manager_CPT extends WPJM_BaseTest {
	protected WP_Job_Manager_CPT $job_manager_cpt;

	public function setUp(): void {
		parent::setUp();

		// Ensure the hooks are set up.
		$this->job_manager_cpt = new WP_Job_Manager_CPT();
	}

	/**
	 * Ensure that filter_meta adds the correct filters to the query based on
	 * the URL parameters.
	 *
	 * @since 1.31.0
	 * @covers WP_Job_Manager_CPT::filter_meta
	 */
	public function test_filter_meta() {
		global $pagenow;

		// Create some listings.
		$listing_notfilled_notfeatured_id = $this->create_listing_with_meta(
			[
				'_filled'   => '0',
				'_featured' => '0',
			]
		);
		$listing_notfilled_featured_id    = $this->create_listing_with_meta(
			[
				'_filled'   => '0',
				'_featured' => '1',
			]
		);
		$listing_filled_notfeatured_id    = $this->create_listing_with_meta(
			[
				'_filled'   => '1',
				'_featured' => '0',
			]
		);
		$listing_filled_featured_id       = $this->create_listing_with_meta(
			[
				'_filled'   => '1',
				'_featured' => '1',
			]
		);

		// Simulate viewing the edit.php page.
		$pagenow = 'edit.php';

		// When no filters are given.
		$query = new WP_Query(
			[
				'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING,
				'fields'    => 'ids',
			]
		);
		$this->assertContains( $listing_notfilled_notfeatured_id, $query->posts );
		$this->assertContains( $listing_notfilled_featured_id, $query->posts );
		$this->assertContains( $listing_filled_notfeatured_id, $query->posts );
		$this->assertContains( $listing_filled_featured_id, $query->posts );

		// Filtering on Filled.
		$_GET['job_listing_filled'] = '1';
		$query                      = new WP_Query(
			[
				'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING,
				'fields'    => 'ids',
			]
		);
		$this->assertNotContains( $listing_notfilled_notfeatured_id, $query->posts );
		$this->assertNotContains( $listing_notfilled_featured_id, $query->posts );
		$this->assertContains( $listing_filled_notfeatured_id, $query->posts );
		$this->assertContains( $listing_filled_featured_id, $query->posts );

		// Filtering on Featured.
		$_GET['job_listing_filled']   = '';
		$_GET['job_listing_featured'] = '0';
		$query                        = new WP_Query(
			[
				'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING,
				'fields'    => 'ids',
			]
		);
		$this->assertContains( $listing_notfilled_notfeatured_id, $query->posts );
		$this->assertNotContains( $listing_notfilled_featured_id, $query->posts );
		$this->assertContains( $listing_filled_notfeatured_id, $query->posts );
		$this->assertNotContains( $listing_filled_featured_id, $query->posts );
	}

	/**
	 * Admin search matches raw and KSES entity-encoded title storage.
	 *
	 * @since $$next-version$$
	 * @covers WP_Job_Manager_CPT::search_meta
	 */
	public function test_search_meta_matches_entity_encoded_titles() {
		global $pagenow;

		$raw_title = 'R&D Engineer';
		$raw_id    = $this->factory->post->create(
			[
				'post_type'    => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_title'   => $raw_title,
				'post_content' => 'Raw content',
			]
		);
		$encoded_id = $this->factory->post->create(
			[
				'post_type'    => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_title'   => 'R&amp;D Engineer',
				'post_content' => 'Encoded content',
			]
		);
		$pagenow = 'edit.php';

		$query = new WP_Query(
			[
				'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING,
				's'         => $raw_title,
				'fields'    => 'ids',
			]
		);
		$this->assertContains( $raw_id, $query->posts );
		$this->assertContains( $encoded_id, $query->posts );
	}

	/**
	 * Admin search matches both storage forms when the entity-encoded term is typed.
	 *
	 * @since $$next-version$$
	 * @covers WP_Job_Manager_CPT::search_meta
	 */
	public function test_search_meta_matches_both_titles_for_encoded_term() {
		global $pagenow;

		$raw_id = $this->factory->post->create(
			[
				'post_type'    => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_title'   => 'R&D Engineer',
				'post_content' => 'Raw content',
			]
		);

		$encoded_id = $this->factory->post->create(
			[
				'post_type'    => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_title'   => 'R&amp;D Engineer',
				'post_content' => 'Encoded content',
			]
		);

		$pagenow = 'edit.php';

		$query = new WP_Query(
			[
				'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING,
				's'         => 'R&amp;D Engineer',
				'fields'    => 'ids',
			]
		);

		$this->assertContains( $raw_id, $query->posts );
		$this->assertContains( $encoded_id, $query->posts );
	}

	/**
	 * Admin search continues matching literal raw ampersands in post meta.
	 *
	 * @since $$next-version$$
	 * @covers WP_Job_Manager_CPT::search_meta
	 */
	public function test_search_meta_matches_raw_ampersand_meta() {
		global $pagenow;

		$id = $this->create_listing_with_meta( [ '_company_name' => 'Research & Development' ] );
		$pagenow = 'edit.php';

		$query = new WP_Query(
			[
				'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING,
				's'         => 'Research & Development',
				'fields'    => 'ids',
			]
		);
		$this->assertContains( $id, $query->posts );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_CPT::filter_meta
	 */
	public function test_filter_meta_only_on_edit() {
		global $pagenow;

		// Create some listings.
		$listing_id = $this->factory->post->create(
			[ 'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING ]
		);

		// Simulate viewing some other page.
		$pagenow = 'index.php';

		// Filter should do nothing.
		$_GET['job_listing_filled']   = '1';
		$_GET['job_listing_featured'] = '1';
		$query                        = new WP_Query(
			[
				'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING,
				'fields'    => 'ids',
			]
		);
		$this->assertContains( $listing_id, $query->posts );
	}

	/* Helper methods. */

	private function create_listing_with_meta( $meta ) {
		$id = $this->factory->post->create(
			[ 'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING ]
		);

		foreach ( $meta as $meta_key => $meta_value ) {
			update_post_meta( $id, $meta_key, $meta_value );
		}

		return $id;
	}
}
