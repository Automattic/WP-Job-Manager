<?php
/**
 * Regression tests: the front-end edit-job form must not create a new listing for an
 * unauthorized request.
 *
 * A logged-out visitor — or a logged-in user who cannot edit the requested listing —
 * resolves to job_id 0 (see WP_Job_Manager_Form_Edit_Job::__construct via
 * job_manager_user_can_edit_job()). The edit handler must bail in that case rather than
 * falling through to save_job(), which inserts a brand-new listing and thereby bypasses
 * the account-required submission gate enforced by the submit-job form.
 *
 * @package wp-job-manager
 */
class Tests_Edit_Job_Unauthorized_Creation extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-edit-job.php';
	}

	public function tearDown(): void {
		$_POST    = [];
		$_REQUEST = [];
		parent::tearDown();
	}

	/**
	 * Counts all job listings regardless of status.
	 *
	 * @return int
	 */
	private function count_listings() {
		return count(
			get_posts(
				[
					'post_type'   => \WP_Job_Manager_Post_Types::PT_LISTING,
					'post_status' => 'any',
					'fields'      => 'ids',
					'numberposts' => -1,
				]
			)
		);
	}

	/**
	 * Drives WP_Job_Manager_Form_Edit_Job::submit_handler() with a posted edit, the way a
	 * front-end POST does (constructor resolves job_id from $_REQUEST, then handler runs).
	 *
	 * @param array $fields Extra/overriding POST fields.
	 */
	private function post_edit_job( $fields ) {
		$_POST = array_merge(
			[
				'job_manager_form' => 'edit-job',
				'submit_job'       => '1',
				'job_title'        => 'Unauthorized Edit Creation',
				'job_description'  => 'body',
				'application'      => 'attacker@example.test',
				'company_name'     => 'AttackerCo',
			],
			$fields
		);
		foreach ( $_POST as $key => $value ) {
			$_REQUEST[ $key ] = $value;
		}

		$form = new WP_Job_Manager_Form_Edit_Job();
		$form->submit_handler();
	}

	/**
	 * A logged-out edit-job POST (no job_id, no nonce) must not create a listing.
	 */
	public function test_logged_out_request_creates_no_listing() {
		$this->logout();
		$before = $this->count_listings();

		$this->post_edit_job( [] );

		$this->assertSame(
			$before,
			$this->count_listings(),
			'A logged-out edit-job POST must not create a job listing.'
		);
	}

	/**
	 * A logged-in user who does not own the requested listing must neither modify it nor
	 * have their request fall through to creating a new listing.
	 *
	 * The job_id resets to 0 (unauthorized), so a valid `submit-job-0` nonce is supplied —
	 * without the fix the handler passes the nonce check and inserts a new listing.
	 */
	public function test_non_owner_creates_nothing_and_modifies_nothing() {
		$this->login_as_employer();
		$victim = $this->factory->job_listing->create(
			[
				'post_author' => get_current_user_id(),
				'post_title'  => 'Victim Original Title',
			]
		);

		$this->login_as_employer_b();
		$before = $this->count_listings();

		$this->post_edit_job(
			[
				'job_id'      => $victim,
				'job_title'   => 'Hijacked Title',
				'_wpjm_nonce' => wp_create_nonce( 'submit-job-0' ),
			]
		);

		$this->assertSame(
			$before,
			$this->count_listings(),
			'A non-owner edit request must not create a new listing.'
		);
		$this->assertSame(
			'Victim Original Title',
			get_post( $victim )->post_title,
			'A non-owner must not modify the victim listing.'
		);
	}

	/**
	 * No-regression: the owner can still edit their own listing, and doing so updates the
	 * existing post rather than creating a new one.
	 */
	public function test_owner_can_edit_own_listing() {
		add_filter( 'job_manager_job_is_editable', '__return_true' );

		$this->login_as_employer();
		$job = $this->factory->job_listing->create(
			[
				'post_author' => get_current_user_id(),
				'post_title'  => 'Original Title',
			]
		);

		$before = $this->count_listings();

		$this->post_edit_job(
			[
				'job_id'      => $job,
				'_wpjm_nonce' => wp_create_nonce( 'submit-job-' . $job ),
				'job_title'   => 'Updated Title',
			]
		);

		remove_filter( 'job_manager_job_is_editable', '__return_true' );

		$this->assertSame(
			$before,
			$this->count_listings(),
			'Editing an existing listing must not create an extra listing.'
		);
		$this->assertSame(
			'Updated Title',
			get_post( $job )->post_title,
			'The owner edit must be saved to the existing listing.'
		);
	}
}
