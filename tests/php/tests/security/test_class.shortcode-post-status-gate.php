<?php
/**
 * Regression tests: the [jobs] shortcode must not honour a post_status attribute the
 * current viewer lacks the capability to query.
 *
 * The shortcode attribute is author-controlled content — a Contributor can preview their
 * own draft containing [jobs post_status="draft,pending,private"] — while the listings it
 * would expose belong to other users. The AJAX handler received this gate in 2.4.5; the
 * shortcode's direct (no-filters) query path must apply the same rule.
 *
 * @package wp-job-manager/tests
 */
class Tests_Shortcode_Post_Status_Gate extends WPJM_BaseTest {

	const SENTINEL = 'SENTINEL_DRAFT_LISTING_TITLE';

	private $draft_id;

	public function setUp(): void {
		parent::setUp();

		$employer       = $this->factory->user->create( [ 'role' => 'employer' ] );
		$this->draft_id = $this->factory->job_listing->create(
			[
				'post_status' => 'draft',
				'post_author' => $employer,
				'post_title'  => self::SENTINEL,
			]
		);
	}

	/**
	 * Renders the [jobs] shortcode's direct query path with a post_status attribute.
	 *
	 * @param string $post_status Requested statuses.
	 * @return string Rendered HTML.
	 */
	private function render_jobs_shortcode( $post_status ) {
		return (string) WP_Job_Manager_Shortcodes::instance()->output_jobs(
			[
				'show_filters' => 'false',
				'post_status'  => $post_status,
			]
		);
	}

	/**
	 * A viewer without the listing-editing capability cannot pull drafts through the
	 * shortcode attribute.
	 *
	 * @covers WP_Job_Manager_Shortcodes::output_jobs
	 */
	public function test_draft_status_ignored_for_low_privilege_viewer() {
		$contributor = $this->factory->user->create( [ 'role' => 'contributor' ] );
		wp_set_current_user( $contributor );

		$this->assertStringNotContainsString(
			self::SENTINEL,
			$this->render_jobs_shortcode( 'draft,pending,private' ),
			'A low-privilege viewer must not receive other users\' drafts via the post_status attribute.'
		);
	}

	/**
	 * Same for an anonymous viewer.
	 *
	 * @covers WP_Job_Manager_Shortcodes::output_jobs
	 */
	public function test_draft_status_ignored_for_anonymous_viewer() {
		$this->logout();

		$this->assertStringNotContainsString(
			self::SENTINEL,
			$this->render_jobs_shortcode( 'draft' ),
			'An anonymous viewer must not receive drafts via the post_status attribute.'
		);
	}

	/**
	 * Positive control: a viewer with the listing-editing capability still gets the
	 * requested statuses, so admin-facing uses of the attribute keep working.
	 *
	 * @covers WP_Job_Manager_Shortcodes::output_jobs
	 */
	public function test_draft_status_honoured_for_capable_viewer() {
		$this->login_as_admin();

		$this->assertStringContainsString(
			self::SENTINEL,
			$this->render_jobs_shortcode( 'draft' ),
			'A capable viewer must still be able to query drafts via the attribute.'
		);
	}

	/**
	 * Positive control: published listings render for everyone regardless of the gate.
	 *
	 * @covers WP_Job_Manager_Shortcodes::output_jobs
	 */
	public function test_publish_status_unaffected() {
		$published = $this->factory->job_listing->create(
			[ 'post_title' => 'PUBLISHED_SENTINEL_LISTING' ]
		);
		$this->logout();

		$this->assertStringContainsString(
			'PUBLISHED_SENTINEL_LISTING',
			$this->render_jobs_shortcode( 'publish' ),
			'Published listings must render for everyone.'
		);
	}
}
