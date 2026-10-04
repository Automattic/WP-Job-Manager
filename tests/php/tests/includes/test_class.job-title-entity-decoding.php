<?php
/**
 * Tests that a job listing title is HTML-entity decoded before it is placed
 * into a plain-text context (email subjects, the plain-text email body).
 *
 * The JSON-LD structured data had the same defect, fixed separately in #3027:
 * the emitted `<script type="application/ld+json">` block is now JSON-escaped
 * (`wp_json_encode()` with the `JSON_HEX_*` flags) instead of HTML-escaped, so
 * the payload round-trips; what the title itself contains is still governed by
 * the storage/filter pipeline covered here.
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

	/**
	 * Filters this test added, so tearDown() can remove them by reference —
	 * anonymous closures cannot be removed by name, and this suite leaves
	 * filters in place between cases (see tests/README.md).
	 *
	 * @var array<int, array{0:string,1:callable}>
	 */
	private $added_filters = [];

	/**
	 * Whether core's kses title filter was active before this test ran, so
	 * tearDown() can restore that exact state rather than force it off.
	 *
	 * @var bool
	 */
	private $kses_was_active;

	public function setUp(): void {
		parent::setUp();
		$this->kses_was_active = false !== has_filter( 'title_save_pre', 'wp_filter_kses' );
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-email.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-email-template.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/emails/class-wp-job-manager-email-admin-new-job.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/emails/class-wp-job-manager-email-admin-updated-job.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/emails/class-wp-job-manager-email-employer-expiring-job.php';
	}

	public function tearDown(): void {
		foreach ( $this->added_filters as $filter ) {
			remove_filter( $filter[0], $filter[1] );
		}
		$this->added_filters = [];

		if ( $this->kses_was_active ) {
			kses_init_filters();
		} else {
			kses_remove_filters();
		}

		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Adds a filter and records it so tearDown() can remove it by reference.
	 *
	 * @param string   $hook     Filter name.
	 * @param callable $callback Filter callback.
	 */
	private function add_temporary_filter( $hook, $callback ) {
		add_filter( $hook, $callback );
		$this->added_filters[] = [ $hook, $callback ];
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

	/**
	 * Renders the plain-text job details email segment and returns its output.
	 *
	 * @param WP_Post $job Job listing.
	 * @return string
	 */
	private function render_plain_text_job_details( $job ) {
		$email = new WP_Job_Manager_Email_Admin_New_Job( [ 'job' => $job ], [] );

		ob_start();
		WP_Job_Manager_Email_Notifications::output_job_details( $job, $email, true, true );
		return ob_get_clean();
	}

	public function test_plain_text_email_body_decodes_entities_in_title() {
		$job = $this->create_listing_as_unprivileged_user();

		$this->assertStringContainsString( self::RAW_TITLE, $this->render_plain_text_job_details( $job ) );
	}

	/**
	 * `esc_html()` in a plain-text template encodes a bare `&` itself, so this path is
	 * broken for administrators too — not just for users caught by the kses capability split.
	 */
	public function test_plain_text_email_body_does_not_encode_an_admin_raw_title() {
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

		$output = $this->render_plain_text_job_details( $job );
		$this->assertStringContainsString( self::RAW_TITLE, $output );
		$this->assertStringNotContainsString( '&amp;', $output );
	}

	/**
	 * Decoding must happen after tags are stripped, not before.
	 *
	 * A decoded `<` that is not followed by a space reads as an unterminated tag, so
	 * decode-then-strip hands `strip_tags()` a `Sales <10k` it will silently swallow
	 * along with the rest of the value.
	 */
	public function test_plain_text_email_body_does_not_truncate_a_value_at_a_bare_less_than() {
		$job = $this->create_listing_as_unprivileged_user();

		$this->add_temporary_filter(
			'job_manager_emails_job_detail_fields',
			function ( $fields ) {
				$fields['job_title']['value'] = 'Sales &lt;10k &amp; more';
				return $fields;
			}
		);

		$this->assertStringContainsString( 'Sales <10k & more', $this->render_plain_text_job_details( $job ) );
	}

	/**
	 * `esc_url()` encodes `&` to `&#038;`, which breaks a link in a plain-text body.
	 */
	public function test_plain_text_email_body_does_not_html_escape_the_url() {
		$job = $this->create_listing_as_unprivileged_user();

		$this->add_temporary_filter(
			'job_manager_emails_job_detail_fields',
			function ( $fields ) {
				$fields['job_title']['url'] = 'http://example.org/?job=1&preview=true';
				return $fields;
			}
		);

		$output = $this->render_plain_text_job_details( $job );

		$this->assertStringContainsString( 'http://example.org/?job=1&preview=true', $output );
		$this->assertStringNotContainsString( '&#038;', $output );
	}
}
