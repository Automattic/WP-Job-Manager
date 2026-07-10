<?php
/**
 * Tests that a job listing title is HTML-entity decoded before it is placed
 * into a plain-text context (email subjects, JSON-LD structured data).
 *
 * WordPress core hooks `wp_filter_kses()` onto `title_save_pre` for any user
 * without the `unfiltered_html` capability, so a listing submitted by a guest,
 * subscriber, or employer stores `Events &amp; Marketing` in `post_title`.
 * That is correct for HTML output — `the_title()` renders it as `&` — but a
 * raw interpolation into an email subject leaks the entity to the recipient.
 *
 * @package wp-job-manager
 */
class WP_Test_Job_Title_Entity_Decoding extends WPJM_BaseTest {

	const RAW_TITLE = 'Events & Marketing coordinator';

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-email.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-email-template.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/emails/class-wp-job-manager-email-admin-new-job.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/emails/class-wp-job-manager-email-admin-updated-job.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/emails/class-wp-job-manager-email-employer-expiring-job.php';
	}

	public function tearDown(): void {
		kses_remove_filters();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Creates a listing the way a non-privileged submitter does, so `post_title`
	 * is entity-encoded by core's `title_save_pre` filter rather than by us
	 * hand-writing the encoded string into the fixture.
	 *
	 * @return WP_Post
	 */
	private function create_listing_as_unprivileged_user() {
		$user_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$this->assertFalse(
			current_user_can( 'unfiltered_html' ),
			'Fixture precondition: the submitting user must lack unfiltered_html.'
		);

		kses_remove_filters();
		kses_init_filters();

		$job_id = wp_insert_post(
			[
				'post_type'   => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_title'  => self::RAW_TITLE,
				'post_status' => 'publish',
				'post_author' => $user_id,
			]
		);

		return get_post( $job_id );
	}

	public function test_admin_new_job_subject_decodes_entities_in_title() {
		$job   = $this->create_listing_as_unprivileged_user();
		$email = new WP_Job_Manager_Email_Admin_New_Job( [ 'job' => $job ], [] );

		$this->assertStringContainsString( self::RAW_TITLE, $email->get_subject() );
		$this->assertStringNotContainsString( '&amp;', $email->get_subject() );
	}

	public function test_admin_updated_job_subject_decodes_entities_in_title() {
		$job   = $this->create_listing_as_unprivileged_user();
		$email = new WP_Job_Manager_Email_Admin_Updated_Job( [ 'job' => $job ], [] );

		$this->assertStringContainsString( self::RAW_TITLE, $email->get_subject() );
		$this->assertStringNotContainsString( '&amp;', $email->get_subject() );
	}

	public function test_employer_expiring_job_subject_decodes_entities_in_title() {
		$job   = $this->create_listing_as_unprivileged_user();
		$email = new WP_Job_Manager_Email_Employer_Expiring_Job( [ 'job' => $job ], [] );

		$this->assertStringContainsString( self::RAW_TITLE, $email->get_subject() );
		$this->assertStringNotContainsString( '&amp;', $email->get_subject() );
	}

	public function test_structured_data_title_decodes_entities() {
		$job  = $this->create_listing_as_unprivileged_user();
		$data = wpjm_get_job_listing_structured_data( $job );

		$this->assertSame( self::RAW_TITLE, $data['title'] );
	}

	/**
	 * The decoded title must survive JSON encoding of the structured data payload.
	 *
	 * Note this asserts on the payload, not on the rendered `<script type="application/ld+json">`
	 * block: `wpjm_esc_json()` re-escapes that output with `$double_encode = true`, so the emitted
	 * markup still contains `&amp;`. That is a separate, pre-existing concern.
	 */
	public function test_structured_data_json_payload_contains_no_entity() {
		$job  = $this->create_listing_as_unprivileged_user();
		$json = wp_json_encode( wpjm_get_job_listing_structured_data( $job ) );

		$this->assertStringNotContainsString( '&amp;', $json );
		$this->assertStringNotContainsString( '&#038;', $json );
	}

	/**
	 * Decoding must be a no-op for a title stored raw, which is what an
	 * administrator's submission produces.
	 */
	public function test_unencoded_title_is_left_alone() {
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		kses_remove_filters();

		$job_id = wp_insert_post(
			[
				'post_type'   => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_title'  => self::RAW_TITLE,
				'post_status' => 'publish',
				'post_author' => $admin_id,
			]
		);
		$job = get_post( $job_id );

		$this->assertSame( self::RAW_TITLE, $job->post_title, 'Fixture precondition: admin titles store raw.' );

		$email = new WP_Job_Manager_Email_Admin_New_Job( [ 'job' => $job ], [] );
		$this->assertStringContainsString( self::RAW_TITLE, $email->get_subject() );

		$data = wpjm_get_job_listing_structured_data( $job );
		$this->assertSame( self::RAW_TITLE, $data['title'] );
	}

	/**
	 * Entities other than `&amp;` must decode too, or an apostrophe in a title
	 * would still leak `&#039;` into the subject line.
	 */
	public function test_quote_entities_are_decoded() {
		$user_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );
		kses_remove_filters();
		kses_init_filters();

		$title  = "Nurse's aide & \"night\" cover";
		$job_id = wp_insert_post(
			[
				'post_type'   => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_author' => $user_id,
			]
		);

		$email = new WP_Job_Manager_Email_Admin_New_Job( [ 'job' => get_post( $job_id ) ], [] );

		$this->assertStringContainsString( $title, $email->get_subject() );
	}
}
