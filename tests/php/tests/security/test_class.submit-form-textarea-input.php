<?php
/**
 * Regression tests for submitted textarea fields.
 *
 * Descriptions submitted through the frontend forms are stored the way the listing display
 * treats them: unless the submitter can edit job listings, the content is limited to the
 * listing's allowed tags and shortcodes and blocks are kept as the text that was written.
 *
 * @package wp-job-manager/tests
 */

class Tests_Submit_Form_Textarea_Input extends WPJM_BaseTest {

	const FIELD_KEY = 'job_description';

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';
	}

	public function tearDown(): void {
		unset( $_POST[ self::FIELD_KEY ] );
		remove_filter( 'job_manager_job_description_allow_shortcodes', '__return_true' );
		parent::tearDown();
	}

	/**
	 * @covers WP_Job_Manager_Form::get_posted_textarea_field
	 */
	public function test_shortcodes_in_subscriber_description_are_stored_as_text() {
		$this->login_as( $this->factory->user->create( [ 'role' => 'subscriber' ] ) );

		$stored = $this->post_description( 'Before [gallery ids="1"] middle [caption]inner text[/caption] after' );

		$this->assertStringNotContainsString( '[', $stored, 'No expandable shortcode may be stored.' );
		$this->assertStringContainsString( 'Before', $stored );
		$this->assertStringContainsString( 'middle', $stored );
		$this->assertStringContainsString( 'inner text', $stored, 'Text inside enclosing shortcodes must be kept.' );
		$this->assertStringContainsString( 'after', $stored );
	}

	/**
	 * @covers WP_Job_Manager_Form::get_posted_textarea_field
	 */
	public function test_media_markup_in_subscriber_description_is_removed() {
		$this->login_as( $this->factory->user->create( [ 'role' => 'subscriber' ] ) );

		$stored = $this->post_description( '<video src="https://example.com/a.mp4"></video><p>Keep</p>' );

		$this->assertStringNotContainsString( '<video', $stored );
		$this->assertStringContainsString( '<p>Keep</p>', $stored );
	}

	/**
	 * @covers WP_Job_Manager_Form::get_posted_textarea_field
	 */
	public function test_block_delimiters_in_subscriber_description_are_dropped() {
		$this->login_as( $this->factory->user->create( [ 'role' => 'subscriber' ] ) );

		$stored = $this->post_description( 'Intro <!-- wp:latest-posts {"postsToShow":3} /--> outro' );

		$this->assertStringNotContainsString( 'wp:latest-posts', $stored );
		$this->assertStringContainsString( 'Intro', $stored );
		$this->assertStringContainsString( 'outro', $stored );
	}

	/**
	 * Literal brackets stay visible as text.
	 *
	 * @covers WP_Job_Manager_Form::get_posted_textarea_field
	 */
	public function test_brackets_in_subscriber_description_are_encoded() {
		$this->login_as( $this->factory->user->create( [ 'role' => 'subscriber' ] ) );

		$stored = $this->post_description( 'Array [0] notation' );

		$this->assertStringNotContainsString( '[', $stored );
		$this->assertStringContainsString( '&#091;0&#093;', $stored, 'Bracket text must be kept, encoded.' );
	}

	/**
	 * @covers WP_Job_Manager_Form::get_posted_textarea_field
	 */
	public function test_guest_description_is_stored_as_text() {
		$this->logout();

		$stored = $this->post_description( 'Intro [gallery ids="1"]' );

		$this->assertStringNotContainsString( '[gallery', $stored );
	}

	/**
	 * Control: submitters who can edit job listings keep their markup.
	 *
	 * @covers WP_Job_Manager_Form::get_posted_textarea_field
	 */
	public function test_admin_description_is_stored_as_posted() {
		$this->login_as_admin();

		$content = 'Intro [gallery ids="1"] <!-- wp:paragraph --><p>Block</p><!-- /wp:paragraph -->';

		$this->assertSame( $content, $this->post_description( $content ) );
	}

	/**
	 * Trust follows the listing-editing capability, not a role.
	 *
	 * @covers WP_Job_Manager_Form::can_submit_unrestricted_html
	 */
	public function test_description_is_stored_as_posted_for_listing_editor() {
		$user = new WP_User( $this->factory->user->create( [ 'role' => 'subscriber' ] ) );
		$user->add_cap( \WP_Job_Manager_Post_Types::CAP_EDIT_LISTINGS );
		$this->login_as( $user->ID );

		$content = 'Intro [gallery ids="1"]';

		$this->assertSame( $content, $this->post_description( $content ) );
	}

	/**
	 * @covers WP_Job_Manager_Form::can_submit_unrestricted_html
	 */
	public function test_filter_can_keep_submissions_unrestricted() {
		$this->login_as( $this->factory->user->create( [ 'role' => 'subscriber' ] ) );
		add_filter( 'job_manager_job_description_allow_shortcodes', '__return_true' );

		$content = 'Intro [gallery ids="1"]';

		$this->assertSame( $content, $this->post_description( $content ) );
	}

	/**
	 * Submits a description through the form's textarea handling and returns the stored value.
	 *
	 * @param string $content Submitted description.
	 * @return string
	 */
	private function post_description( $content ) {
		$_POST[ self::FIELD_KEY ] = wp_slash( $content );

		$form   = WP_Job_Manager_Form_Submit_Job::instance();
		$method = new ReflectionMethod( $form, 'get_posted_textarea_field' );
		$method->setAccessible( true );

		return $method->invoke( $form, self::FIELD_KEY, [] );
	}
}
