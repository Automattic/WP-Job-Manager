<?php
/**
 * Tests that job dashboard actions run on the configured dashboard page and
 * that declined action requests surface an error instead of failing silently.
 *
 * @package wp-job-manager
 */

use WP_Job_Manager\Job_Dashboard_Shortcode;

/**
 * Thrown by the wp_redirect filter to escape Redirect_Message::redirect()
 * before its exit call.
 */
class WPJM_Test_Redirect_Interrupt extends Exception {}

class WP_Test_Job_Dashboard_Page_Gate extends WPJM_BaseTest {

	/**
	 * URL captured from the interrupted redirect, or null when none happened.
	 *
	 * @var string|null
	 */
	private $redirect_url;

	public function setUp(): void {
		parent::setUp();

		$this->redirect_url = null;
		add_filter( 'wp_redirect', [ $this, 'interrupt_redirect' ] );

		$_GET     = [];
		$_POST    = [];
		$_REQUEST = [];
		delete_option( 'job_manager_job_dashboard_page_id' );
	}

	public function tearDown(): void {
		remove_filter( 'wp_redirect', [ $this, 'interrupt_redirect' ] );
		delete_option( 'job_manager_job_dashboard_page_id' );
		$_GET     = [];
		$_POST    = [];
		$_REQUEST = [];
		parent::tearDown();
	}

	/**
	 * Captures the redirect URL and bails out before the exit call.
	 *
	 * @param string $url Redirect destination.
	 * @throws WPJM_Test_Redirect_Interrupt Always.
	 */
	public function interrupt_redirect( $url ) {
		$this->redirect_url = $url;
		throw new WPJM_Test_Redirect_Interrupt( $url );
	}

	/**
	 * Creates a published job owned by the current user.
	 *
	 * @return int Job ID.
	 */
	private function create_own_job() {
		return $this->factory->job_listing->create(
			[
				'post_author' => get_current_user_id(),
				'post_status' => 'publish',
			]
		);
	}

	/**
	 * Creates a page and navigates the test request to it.
	 *
	 * @param string $content Page content.
	 * @return int Page ID.
	 */
	private function go_to_page( $content ) {
		$page_id = $this->factory->post->create(
			[
				'post_type'    => 'page',
				'post_content' => $content,
			]
		);

		$this->go_to( get_permalink( $page_id ) );

		return $page_id;
	}

	/**
	 * Sets a nonce-verified mark_filled request for a job.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $action Dashboard action.
	 */
	private function request_action( $job_id, $action = 'mark_filled' ) {
		$_REQUEST['action']   = $action;
		$_REQUEST['job_id']   = (string) $job_id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'job_manager_my_job_actions' );
	}

	/**
	 * Runs the action handler, expecting it to finish with a redirect.
	 */
	private function handle_actions_expecting_redirect() {
		try {
			Job_Dashboard_Shortcode::instance()->handle_actions();
			$this->fail( 'Expected handle_actions() to redirect.' );
		} catch ( WPJM_Test_Redirect_Interrupt $e ) {
			$this->assertNotNull( $this->redirect_url );
		}
	}

	/**
	 * Returns the stored redirect message for the captured redirect URL.
	 *
	 * @return string|null
	 */
	private function get_redirect_message() {
		$query = wp_parse_url( $this->redirect_url, PHP_URL_QUERY );
		parse_str( (string) $query, $args );

		if ( empty( $args['updated'] ) ) {
			return null;
		}

		$message = get_transient( 'wpjm_message_' . $args['updated'] );

		return is_string( $message ) ? $message : null;
	}

	/**
	 * Actions run on the configured dashboard page even when its stored content
	 * does not contain the shortcode (synced pattern, template, page builder).
	 */
	public function test_action_runs_on_configured_page_without_shortcode() {
		$this->login_as_employer();
		$job_id = $this->create_own_job();

		$page_id = $this->go_to_page( '<!-- wp:block {"ref":7} /-->' );
		update_option( 'job_manager_job_dashboard_page_id', $page_id );

		$this->request_action( $job_id );
		$this->handle_actions_expecting_redirect();

		$this->assertSame( '1', get_post_meta( $job_id, '_filled', true ) );
		$this->assertStringNotContainsString( 'action=', $this->redirect_url );
	}

	/**
	 * Actions still run on a page holding the shortcode in its own content,
	 * even when a different page is configured as the dashboard.
	 */
	public function test_action_runs_on_shortcode_page() {
		$this->login_as_employer();
		$job_id = $this->create_own_job();

		update_option( 'job_manager_job_dashboard_page_id', 99999999 );
		$this->go_to_page( '[job_dashboard]' );

		$this->request_action( $job_id );
		$this->handle_actions_expecting_redirect();

		$this->assertSame( '1', get_post_meta( $job_id, '_filled', true ) );
	}

	/**
	 * A declined dashboard action redirects with an error notice instead of
	 * silently reloading, and does not perform the action.
	 */
	public function test_declined_action_surfaces_error() {
		$this->login_as_employer();
		$job_id = $this->create_own_job();

		$this->go_to_page( 'No dashboard here.' );

		$this->request_action( $job_id );
		$this->handle_actions_expecting_redirect();

		$this->assertSame( '0', get_post_meta( $job_id, '_filled', true ) );

		$message = $this->get_redirect_message();
		$this->assertNotNull( $message );
		$this->assertStringContainsString( 'not set as the job dashboard', $message );
	}

	/**
	 * Requests that do not carry a dashboard action are ignored on
	 * non-dashboard pages, with no redirect.
	 */
	public function test_unrelated_action_is_ignored() {
		$this->login_as_employer();
		$job_id = $this->create_own_job();

		$this->go_to_page( 'No dashboard here.' );

		$this->request_action( $job_id, 'someplugin_custom_action' );
		Job_Dashboard_Shortcode::instance()->handle_actions();

		$this->assertNull( $this->redirect_url );
	}

	/**
	 * Requests without a nonce are ignored even on the configured page.
	 */
	public function test_request_without_nonce_is_ignored() {
		$this->login_as_employer();
		$job_id = $this->create_own_job();

		$page_id = $this->go_to_page( 'Dashboard via template.' );
		update_option( 'job_manager_job_dashboard_page_id', $page_id );

		$_REQUEST['action'] = 'mark_filled';
		$_REQUEST['job_id'] = (string) $job_id;

		Job_Dashboard_Shortcode::instance()->handle_actions();

		$this->assertNull( $this->redirect_url );
		$this->assertSame( '0', get_post_meta( $job_id, '_filled', true ) );
	}
}
