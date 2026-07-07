<?php
/**
 * Regression tests: a front-end publish transition must not honor an attacker-supplied
 * `_job_expires` value.
 *
 * WP_Job_Manager_Post_Types::set_expiry() fires on every transition into `publish`,
 * including the front-end preview -> publish performed synchronously during a submitter's
 * own POST. Only a gated admin edit (save_meta_data nonce + edit capability) may set the
 * expiry date manually; a front-end submitter must have expiry derived server-side from
 * the configured submission duration, otherwise they could grant their own listing an
 * unlimited lifetime and bypass the paid duration model (CWE-639).
 *
 * @package wp-job-manager
 */
class Tests_Frontend_Expiry_Authorization extends WPJM_BaseTest {

	/**
	 * An implausibly distant expiry an attacker would try to smuggle in.
	 */
	private const ATTACKER_EXPIRY = '2999-12-31';

	public function tearDown(): void {
		$_POST    = [];
		$_REQUEST = [];
		parent::tearDown();
	}

	/**
	 * Creates a listing in a non-published status, ready to transition to publish.
	 *
	 * @param int    $author Author user ID.
	 * @param string $status Initial post status.
	 *
	 * @return int Job listing ID.
	 */
	private function create_listing( $author, $status ) {
		return wp_insert_post(
			[
				'post_type'   => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_status' => $status,
				'post_author' => $author,
				'post_title'  => 'Expiry authorization',
			]
		);
	}

	/**
	 * Runs the expiry logic the transition_post_status handler invokes on a publish
	 * transition. Called directly to isolate the authorization gate from the admin
	 * writepanels save path (which the loaded WP_Job_Manager_Writepanels hooks into).
	 *
	 * @param int $job_id Job listing ID.
	 */
	private function run_publish_expiry_handler( $job_id ) {
		\WP_Job_Manager_Post_Types::instance()->set_expiry( get_post( $job_id ) );
	}

	/**
	 * A front-end submitter (no admin nonce) must not be able to set an arbitrary expiry.
	 */
	public function test_frontend_publish_ignores_posted_job_expires() {
		$submitter = $this->factory->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $submitter );

		$job_id = $this->create_listing( $submitter, 'preview' );

		// Attacker appends the field to their own preview -> publish POST; no admin nonce.
		$_POST['_job_expires'] = self::ATTACKER_EXPIRY;

		$this->run_publish_expiry_handler( $job_id );

		$this->assertNotSame(
			self::ATTACKER_EXPIRY,
			get_post_meta( $job_id, '_job_expires', true ),
			'A front-end publish must not honor an attacker-supplied _job_expires value.'
		);
	}

	/**
	 * The gated admin edit path (valid save_meta_data nonce + edit capability) may still
	 * set the expiry date manually.
	 */
	public function test_admin_edit_honors_posted_job_expires() {
		// get_user_by_role() installs the job-listing capabilities on the administrator role
		// (WP_Job_Manager_Install::install()), which a bare factory admin would not have.
		$admin = $this->get_user_by_role( 'administrator' );
		$this->login_as( $admin );

		$job_id = $this->create_listing( $admin, 'pending' );

		$_POST['job_manager_nonce'] = wp_create_nonce( 'save_meta_data' );
		$_POST['_job_expires']      = self::ATTACKER_EXPIRY;

		$this->run_publish_expiry_handler( $job_id );

		$this->assertSame(
			self::ATTACKER_EXPIRY,
			get_post_meta( $job_id, '_job_expires', true ),
			'A gated admin edit must still be able to set the expiry date manually.'
		);
	}

	/**
	 * A valid edit capability without the admin nonce is not sufficient: the manual expiry
	 * value must be ignored, so a logged-in author cannot self-serve an unlimited expiry.
	 */
	public function test_edit_capability_without_nonce_ignores_posted_job_expires() {
		// A genuinely capable admin (job-listing caps installed), but no admin nonce.
		$admin = $this->get_user_by_role( 'administrator' );
		$this->login_as( $admin );

		$job_id = $this->create_listing( $admin, 'preview' );

		// Capability is present, but the save_meta_data nonce is not.
		$_POST['_job_expires'] = self::ATTACKER_EXPIRY;

		$this->run_publish_expiry_handler( $job_id );

		$this->assertNotSame(
			self::ATTACKER_EXPIRY,
			get_post_meta( $job_id, '_job_expires', true ),
			'Without the admin nonce, a posted _job_expires must be ignored even for a capable user.'
		);
	}

	/**
	 * A valid save_meta_data nonce without the edit capability is not sufficient: the manual
	 * expiry value must be ignored. Nonces are not capability- or post-bound, so any logged-in
	 * user can mint a valid save_meta_data nonce for themselves; the edit_post capability check
	 * exists precisely to stop that self-minted nonce from unlocking a manual expiry. This is
	 * the exact attack shape the capability check defends against.
	 */
	public function test_minted_nonce_without_capability_ignores_posted_job_expires() {
		// A low-privilege author has no job_listing capabilities, so current_user_can(
		// 'edit_post', $job_id ) is false for a job_listing even one they authored.
		$submitter = $this->factory->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $submitter );

		$job_id = $this->create_listing( $submitter, 'preview' );

		// Any logged-in user can mint this nonce; the capability check is the real gate.
		$_POST['job_manager_nonce'] = wp_create_nonce( 'save_meta_data' );
		$_POST['_job_expires']      = self::ATTACKER_EXPIRY;

		$this->run_publish_expiry_handler( $job_id );

		$this->assertNotSame(
			self::ATTACKER_EXPIRY,
			get_post_meta( $job_id, '_job_expires', true ),
			'A self-minted nonce without the edit capability must not honor a posted _job_expires value.'
		);
	}
}
