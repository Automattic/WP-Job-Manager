<?php

require JOB_MANAGER_PLUGIN_DIR . '/includes/admin/class-wp-job-manager-writepanels.php';

class WP_Test_WP_Job_Manager_Writepanels extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		$this->enable_manage_job_listings_cap();
	}

	public function data_provider_test_save_job_data_auto_expire() {
		$expired_date = wp_date( 'Y-m-d', strtotime( '-2 months', current_datetime()->getTimestamp() ) );
		$future_date  = wp_date( 'Y-m-d', strtotime( '+2 months', current_datetime()->getTimestamp() ) );
		$duration     = absint( get_option( 'job_manager_submission_duration' ) );
		$auto_date    = wp_date( 'Y-m-d', strtotime( "+{$duration} days", current_datetime()->getTimestamp() ) );

		return [
			/**
			 * Tests to make sure auto-expiring works.
			 */
			'autoexpire_publish_future_publish'      => [
				// On published post, set to future date and expect published.
				[
					'original' => 'publish',
					'new'      => null,
					'expected' => 'publish',
				],
				[
					'original' => $future_date,
					'new'      => $future_date,
					'expected' => $future_date,
				],
			],
			'autoexpire_publish_past_expired'        => [
				// On published post, set to past date and expect expired.
				[
					'original' => 'publish',
					'new'      => 'publish',
					'expected' => 'expired',
				],
				[
					'original' => $future_date,
					'new'      => $expired_date,
					'expected' => $expired_date,
				],
			],
			'autoexpire_draft_past_expired'          => [
				// On draft post, set to past date and expect expired.
				[
					'original' => 'draft',
					'new'      => 'publish',
					'expected' => 'expired',
				],
				[
					'original' => $future_date,
					'new'      => $expired_date,
					'expected' => $expired_date,
				],
			],
			'autoexpire_draft_future_publish'        => [
				// On draft post, set to future date and expect expired.
				[
					'original' => 'draft',
					'new'      => 'publish',
					'expected' => 'publish',
				],
				[
					'original' => $future_date,
					'new'      => $future_date,
					'expected' => $future_date,
				],
			],
			'autoexpire_expired_future_keep_expired' => [
				// On expired post, set to future date and expect expired to be preserved.
				[
					'original' => 'expired',
					'new'      => null,
					'expected' => 'expired',
				],
				[
					'original' => $expired_date,
					'new'      => $future_date,
					'expected' => $future_date,
				],
			],

			/**
			 * Tests to make sure changes to draft is preserved.
			*/
			'draft_publish_draft'                    => [
				// From publish to draft (not touching expiration date) we should get a draft.
				[
					'original' => 'publish',
					'new'      => 'draft',
					'expected' => 'draft',
				],
				null,
			],
			'draft_expired_draft'                    => [
				// From expired to draft (not touching expiration date) we should get a draft.
				[
					'original' => 'expired',
					'new'      => 'draft',
					'expected' => 'draft',
				],
				null,
			],
			'draft_publish_draft_set_expired_date'   => [
				// From publish to draft (setting an expired expiration date) we should get a draft.
				[
					'original' => 'publish',
					'new'      => 'draft',
					'expected' => 'draft',
				],
				[
					'original' => $future_date,
					'new'      => $expired_date,
					'expected' => $expired_date,
				],
			],
			'draft_publish_draft_keep_expired_date'  => [
				// From publish to draft (keeping an expired expiration date) we should get a draft.
				[
					'original' => 'publish',
					'new'      => 'draft',
					'expected' => 'draft',
				],
				[
					'original' => $expired_date,
					'new'      => $expired_date,
					'expected' => $expired_date,
				],
			],
			'draft_expired_draft_set_expired'        => [
				// From expired to draft (setting an expired expiration date) we should get a draft.
				[
					'original' => 'expired',
					'new'      => 'draft',
					'expected' => 'draft',
				],
				[
					'original' => $future_date,
					'new'      => $expired_date,
					'expected' => $expired_date,
				],
			],
			'draft_expired_draft_keep_expired'       => [
				// From expired to draft (keeping an expired expiration date) we should get a draft.
				[
					'original' => 'expired',
					'new'      => 'draft',
					'expected' => 'draft',
				],
				[
					'original' => $expired_date,
					'new'      => $expired_date,
					'expected' => $expired_date,
				],
			],
		];
	}

	/**
	 * @covers WP_Job_Manager_Writepanels::save_job_listing_data
	 * @dataProvider data_provider_test_save_job_data_auto_expire
	 */
	public function test_save_job_data_auto_expire( $status_data = null, $expires_data = null ) {
		$writepanels = WP_Job_Manager_Writepanels::instance();

		$this->login_as_admin();
		$original_job_data = [];
		if ( null !== $status_data && null !== $status_data['original'] ) {
			$original_job_data['post_status'] = $status_data['original'];
		}
		if ( null !== $expires_data && null !== $expires_data['original'] ) {
			$original_job_data['meta_input'] = [ '_job_expires' => $expires_data['original'] ];
		}
		if ( null !== $status_data ) {
			$new_job_data = [
				'original_post_status' => $status_data['original'],
				'post_status'          => $status_data['new'],
			];
		}
		if ( null !== $expires_data && null !== $expires_data['new'] ) {
			$new_job_data['_job_expires'] = $expires_data['new'];
		}
		$job = $this->mock_writepanel_save_request( $new_job_data, $original_job_data );
		if ( null !== $status_data && null !== $status_data['new'] ) {
			wp_update_post(
				[
					'ID'          => $job->ID,
					'post_status' => $status_data['new'],
				]
			);
		}

		$writepanels->save_job_listing_data( $job->ID, $job );
		if ( $status_data ) {
			$this->assertEquals( $status_data['expected'], get_post_status( $job->ID ), sprintf( 'Expected post status of %s after emulating a save where the original post status was %s and new post status was %s', $status_data['expected'], $status_data['original'], $status_data['new'] ) );
		}
		if ( $expires_data ) {
			$this->assertEquals( $expires_data['expected'], get_post_meta( $job->ID, '_job_expires', true ), sprintf( 'Expected job expiration of %s after emulating a save where the original expiration was %s and the new expiration is %s', $expires_data['expected'], $expires_data['original'], $expires_data['new'] ) );
		}
	}

	private function mock_writepanel_save_request( $new_job_data = [], $original_job_data = [] ) {
		global $post;
		$job_id = $this->factory->job_listing->create( $original_job_data );
		$job    = get_post( $job_id );
		$post   = $job;

		$_POST                     = [];
		$_POST['_job_expires']     = $job->_job_expires;
		$_POST['_job_location']    = $job->_job_location;
		$_POST['_job_author']      = $job->_job_author;
		$_POST['_application']     = $job->_application;
		$_POST['_company_name']    = $job->_company_name;
		$_POST['_company_website'] = $job->_company_website;
		$_POST['_company_tagline'] = $job->_company_tagline;
		$_POST['_company_twitter'] = $job->_company_twitter;
		$_POST['_company_video']   = $job->_company_video;
		$_POST['_filled']          = $job->_filled;
		$_POST['_featured']        = $job->_featured;

		$_POST['post_status']          = 'publish';
		$_POST['original_post_status'] = $job->post_status;

		$_POST = array_merge( $_POST, $new_job_data );

		return $job;
	}

	/**
	 * Sets up a bulk edit request payload on $_REQUEST.
	 *
	 * @param array $bulk Bulk edit field values keyed by meta key.
	 */
	private function mock_bulk_edit_request( $bulk = [] ) {
		$_REQUEST = [];
		if ( null !== $bulk ) {
			$_REQUEST['job_manager_bulk_edit'] = '1';
			$_REQUEST['job_manager_bulk_edit_nonce'] = wp_create_nonce( 'job_manager_bulk_edit' );
			$_REQUEST['job_manager_bulk'] = $bulk;
		}
	}

	/**
	 * @covers WP_Job_Manager_Writepanels::bulk_edit_save
	 */
	public function test_bulk_edit_updates_text_fields() {
		$this->login_as_admin();
		$writepanels = WP_Job_Manager_Writepanels::instance();

		$job_id = $this->factory->job_listing->create();
		$job    = get_post( $job_id );

		$this->mock_bulk_edit_request(
			[
				'_job_location' => 'London',
				'_company_name' => 'Acme Corp',
			]
		);

		$writepanels->bulk_edit_save( $job_id, $job );

		$this->assertEquals( 'London', get_post_meta( $job_id, '_job_location', true ) );
		$this->assertEquals( 'Acme Corp', get_post_meta( $job_id, '_company_name', true ) );
	}

	/**
	 * @covers WP_Job_Manager_Writepanels::bulk_edit_save
	 */
	public function test_bulk_edit_empty_means_no_change() {
		$this->login_as_admin();
		$writepanels = WP_Job_Manager_Writepanels::instance();

		$job_id = $this->factory->job_listing->create(
			[
				'meta_input' => [
					'_job_location' => 'Berlin',
					'_company_name' => 'Existing Co',
				],
			]
		);
		$job = get_post( $job_id );

		// Empty string sent for fields we leave untouched.
		$this->mock_bulk_edit_request(
			[
				'_job_location' => '',
				'_company_name' => '',
			]
		);

		$writepanels->bulk_edit_save( $job_id, $job );

		$this->assertEquals( 'Berlin', get_post_meta( $job_id, '_job_location', true ) );
		$this->assertEquals( 'Existing Co', get_post_meta( $job_id, '_company_name', true ) );
	}

	/**
	 * @covers WP_Job_Manager_Writepanels::bulk_edit_save
	 */
	public function test_bulk_edit_checkbox_tristate() {
		$this->login_as_admin();
		$writepanels = WP_Job_Manager_Writepanels::instance();

		// Set filled to 1.
		$job_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_filled' => 0 ] ] );
		$job    = get_post( $job_id );
		$this->mock_bulk_edit_request( [ '_filled' => '1' ] );
		$writepanels->bulk_edit_save( $job_id, $job );
		$this->assertEquals( 1, absint( get_post_meta( $job_id, '_filled', true ) ) );

		// Set filled back to 0.
		$this->mock_bulk_edit_request( [ '_filled' => '0' ] );
		$writepanels->bulk_edit_save( $job_id, $job );
		$this->assertEquals( 0, absint( get_post_meta( $job_id, '_filled', true ) ) );

		// Empty (No Change) leaves it at 0.
		$this->mock_bulk_edit_request( [ '_filled' => '' ] );
		$writepanels->bulk_edit_save( $job_id, $job );
		$this->assertEquals( 0, absint( get_post_meta( $job_id, '_filled', true ) ) );
	}

	/**
	 * @covers WP_Job_Manager_Writepanels::bulk_edit_save
	 */
	public function test_bulk_edit_clear_expiry() {
		$this->login_as_admin();
		$this->enable_manage_job_listings_cap();
		$writepanels = WP_Job_Manager_Writepanels::instance();

		$future_date = wp_date( 'Y-m-d', strtotime( '+2 months', current_datetime()->getTimestamp() ) );
		$job_id      = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => $future_date ] ] );
		$job         = get_post( $job_id );

		$this->mock_bulk_edit_request( [ '_job_expires_clear' => '1' ] );
		$writepanels->bulk_edit_save( $job_id, $job );

		$this->assertSame( '', get_post_meta( $job_id, '_job_expires', true ) );
	}

	/**
	 * @covers WP_Job_Manager_Writepanels::bulk_edit_save
	 */
	public function test_bulk_edit_set_expiry_date() {
		$this->login_as_admin();
		$this->enable_manage_job_listings_cap();
		$writepanels = WP_Job_Manager_Writepanels::instance();

		$future_date = wp_date( 'Y-m-d', strtotime( '+3 months', current_datetime()->getTimestamp() ) );
		$job_id      = $this->factory->job_listing->create();
		$job         = get_post( $job_id );

		$this->mock_bulk_edit_request( [ '_job_expires' => $future_date ] );
		$writepanels->bulk_edit_save( $job_id, $job );

		$this->assertEquals( $future_date, get_post_meta( $job_id, '_job_expires', true ) );
	}

	/**
	 * @covers WP_Job_Manager_Writepanels::bulk_edit_save
	 */
	public function test_bulk_edit_past_expiry_sets_expired_status() {
		$this->login_as_admin();
		$this->enable_manage_job_listings_cap();
		$writepanels = WP_Job_Manager_Writepanels::instance();

		$past_date = wp_date( 'Y-m-d', strtotime( '-2 months', current_datetime()->getTimestamp() ) );
		$job_id    = $this->factory->job_listing->create( [ 'post_status' => 'publish' ] );
		$job       = get_post( $job_id );

		$this->mock_bulk_edit_request( [ '_job_expires' => $past_date ] );
		$writepanels->bulk_edit_save( $job_id, $job );

		$this->assertEquals( 'expired', get_post_status( $job_id ) );
	}

	/**
	 * @covers WP_Job_Manager_Writepanels::bulk_edit_save
	 */
	public function test_bulk_edit_featured_requires_manage_cap() {
		// The base test setUp grants manage_job_listings to everyone; turn it off so we can
		// verify a plain editor can update normal fields but not _featured (needs manage).
		$this->disable_manage_job_listings_cap();

		$editor_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		$editor    = get_user_by( 'ID', $editor_id );
		$editor->add_cap( \WP_Job_Manager_Post_Types::CAP_EDIT_LISTINGS );
		$editor->add_cap( \WP_Job_Manager_Post_Types::CAP_EDIT_PUBLISHED_LISTINGS );
		$editor->add_cap( \WP_Job_Manager_Post_Types::CAP_EDIT_OTHERS_LISTINGS );

		$this->login_as( $editor_id );
		$writepanels = WP_Job_Manager_Writepanels::instance();

		$job_id = $this->factory->job_listing->create(
			[
				'post_status' => 'publish',
				'meta_input'  => [ '_featured' => 0, '_company_name' => 'Original' ],
			]
		);
		$job = get_post( $job_id );

		$this->mock_bulk_edit_request(
			[
				'_featured'     => '1',
				'_company_name' => 'Updated',
			]
		);
		$writepanels->bulk_edit_save( $job_id, $job );

		$this->assertEquals( 0, absint( get_post_meta( $job_id, '_featured', true ) ) );
		$this->assertEquals( 'Updated', get_post_meta( $job_id, '_company_name', true ) );
	}

	/**
	 * @covers WP_Job_Manager_Writepanels::bulk_edit_save
	 */
	public function test_bulk_edit_rejects_bad_nonce() {
		$this->login_as_admin();
		$writepanels = WP_Job_Manager_Writepanels::instance();

		$job_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_company_name' => 'Original' ] ] );
		$job    = get_post( $job_id );

		$_REQUEST = [
			'job_manager_bulk_edit'        => '1',
			'job_manager_bulk_edit_nonce'  => 'garbage',
			'job_manager_bulk'             => [ '_company_name' => 'Changed' ],
		];

		$writepanels->bulk_edit_save( $job_id, $job );

		$this->assertEquals( 'Original', get_post_meta( $job_id, '_company_name', true ) );
	}

	/**
	 * @covers WP_Job_Manager_Writepanels::bulk_edit_save
	 */
	public function test_bulk_edit_without_flag_is_noop() {
		$this->login_as_admin();
		$writepanels = WP_Job_Manager_Writepanels::instance();

		$job_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_company_name' => 'Original' ] ] );
		$job    = get_post( $job_id );

		$_REQUEST = [];

		$writepanels->bulk_edit_save( $job_id, $job );

		$this->assertEquals( 'Original', get_post_meta( $job_id, '_company_name', true ) );
	}

	/**
	 * @covers WP_Job_Manager_Writepanels::bulk_edit_fields
	 */
	public function test_bulk_edit_fields_render_only_for_job_position() {
		$this->login_as_admin();
		$writepanels = WP_Job_Manager_Writepanels::instance();

		ob_start();
		$writepanels->bulk_edit_fields( 'job_position', 'job_listing' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Job Data', $output );
		$this->assertStringContainsString( 'job_manager_bulk_edit_nonce', $output );

		// Other columns and post types render nothing.
		ob_start();
		$writepanels->bulk_edit_fields( 'job_location', 'job_listing' );
		$other_column = ob_get_clean();
		$this->assertSame( '', $other_column );

		ob_start();
		$writepanels->bulk_edit_fields( 'job_position', 'post' );
		$other_type = ob_get_clean();
		$this->assertSame( '', $other_type );
	}
}
