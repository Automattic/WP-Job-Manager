<?php

require JOB_MANAGER_PLUGIN_DIR . '/includes/admin/class-wp-job-manager-writepanels.php';

class WP_Test_WP_Job_Manager_Writepanels extends WPJM_BaseTest {

	public function data_provider_test_save_job_data_auto_expire() {
		$expired_date = date( 'Y-m-d', strtotime( '-2 months', current_time( 'timestamp' ) ) );
		$future_date  = date( 'Y-m-d', strtotime( '+2 months', current_time( 'timestamp' ) ) );
		$duration     = absint( get_option( 'job_manager_submission_duration' ) );
		$auto_date    = date( 'Y-m-d', strtotime( "+{$duration} days", current_time( 'timestamp' ) ) );

		return array(
			/**
			 * Tests to make sure auto-expiring works.
			 */
			'autoexpire_publish_future_publish'      => array(
				// On published post, set to future date and expect published.
				array(
					'original' => 'publish',
					'new'      => null,
					'expected' => 'publish',
				),
				array(
					'original' => $future_date,
					'new'      => $future_date,
					'expected' => $future_date,
				),
			),
			'autoexpire_publish_past_expired'        => array(
				// On published post, set to past date and expect expired.
				array(
					'original' => 'publish',
					'new'      => 'publish',
					'expected' => 'expired',
				),
				array(
					'original' => $future_date,
					'new'      => $expired_date,
					'expected' => $expired_date,
				),
			),
			'autoexpire_draft_past_expired'          => array(
				// On draft post, set to past date and expect expired.
				array(
					'original' => 'draft',
					'new'      => 'publish',
					'expected' => 'expired',
				),
				array(
					'original' => $future_date,
					'new'      => $expired_date,
					'expected' => $expired_date,
				),
			),
			'autoexpire_draft_future_publish'        => array(
				// On draft post, set to future date and expect expired.
				array(
					'original' => 'draft',
					'new'      => 'publish',
					'expected' => 'publish',
				),
				array(
					'original' => $future_date,
					'new'      => $future_date,
					'expected' => $future_date,
				),
			),
			'autoexpire_expired_future_keep_expired' => array(
				// On expired post, set to future date and expect expired to be preserved.
				array(
					'original' => 'expired',
					'new'      => null,
					'expected' => 'expired',
				),
				array(
					'original' => $expired_date,
					'new'      => $future_date,
					'expected' => $future_date,
				),
			),

			/**
			 * Tests to make sure changes to draft is preserved.
			*/
			'draft_publish_draft'                    => array(
				// From publish to draft (not touching expiration date) we should get a draft.
				array(
					'original' => 'publish',
					'new'      => 'draft',
					'expected' => 'draft',
				),
				null,
			),
			'draft_expired_draft'                    => array(
				// From expired to draft (not touching expiration date) we should get a draft.
				array(
					'original' => 'expired',
					'new'      => 'draft',
					'expected' => 'draft',
				),
				null,
			),
			'draft_publish_draft_set_expired_date'   => array(
				// From publish to draft (setting an expired expiration date) we should get a draft.
				array(
					'original' => 'publish',
					'new'      => 'draft',
					'expected' => 'draft',
				),
				array(
					'original' => $future_date,
					'new'      => $expired_date,
					'expected' => $expired_date,
				),
			),
			'draft_publish_draft_keep_expired_date'  => array(
				// From publish to draft (keeping an expired expiration date) we should get a draft.
				array(
					'original' => 'publish',
					'new'      => 'draft',
					'expected' => 'draft',
				),
				array(
					'original' => $expired_date,
					'new'      => $expired_date,
					'expected' => $expired_date,
				),
			),
			'draft_expired_draft_set_expired'        => array(
				// From expired to draft (setting an expired expiration date) we should get a draft.
				array(
					'original' => 'expired',
					'new'      => 'draft',
					'expected' => 'draft',
				),
				array(
					'original' => $future_date,
					'new'      => $expired_date,
					'expected' => $expired_date,
				),
			),
			'draft_expired_draft_keep_expired'       => array(
				// From expired to draft (keeping an expired expiration date) we should get a draft.
				array(
					'original' => 'expired',
					'new'      => 'draft',
					'expected' => 'draft',
				),
				array(
					'original' => $expired_date,
					'new'      => $expired_date,
					'expected' => $expired_date,
				),
			),
		);
	}

	/**
	 * @covers WP_Job_Manager_Writepanels::save_job_listing_data
	 * @dataProvider data_provider_test_save_job_data_auto_expire
	 */
	public function test_save_job_data_auto_expire( $status_data = null, $expires_data = null ) {
		$writepanels = WP_Job_Manager_Writepanels::instance();

		$this->login_as_admin();
		$original_job_data = array();
		if ( null !== $status_data && null !== $status_data['original'] ) {
			$original_job_data['post_status'] = $status_data['original'];
		}
		if ( null !== $expires_data && null !== $expires_data['original'] ) {
			$original_job_data['meta_input'] = array( '_job_expires' => $expires_data['original'] );
		}
		if ( null !== $status_data ) {
			$new_job_data = array(
				'original_post_status' => $status_data['original'],
				'post_status'          => $status_data['new'],
			);
		}
		if ( null !== $expires_data && null !== $expires_data['new'] ) {
			$new_job_data['_job_expires'] = $expires_data['new'];
		}
		$job = $this->mock_writepanel_save_request( $new_job_data, $original_job_data );
		if ( null !== $status_data && null !== $status_data['new'] ) {
			wp_update_post(
				array(
					'ID'          => $job->ID,
					'post_status' => $status_data['new'],
				)
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

	private function mock_writepanel_save_request( $new_job_data = array(), $original_job_data = array() ) {
		global $post;
		$job_id = $this->factory->job_listing->create( $original_job_data );
		$job    = get_post( $job_id );
		$post   = $job;

		$_POST                     = array();
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
}
