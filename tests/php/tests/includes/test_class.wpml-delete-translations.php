<?php
/**
 * Tests that deleting a job listing from the frontend job dashboard also trashes
 * its WPML translations.
 *
 * WPML syncs trashing on `wp_trash_post`, but it only adds that hook on admin requests,
 * so a listing deleted from the dashboard (a normal frontend page load) leaves its
 * translations published. The dashboard fires `job_manager_my_job_do_action`, which
 * the WPML compatibility file uses to trash them.
 *
 * WPML is not installed in the test environment, so its filters are stubbed here to
 * behave the way the plugin documents: `wpml_element_type` prefixes the post type,
 * `wpml_setting` returns the "delete translations" setting, and the trid/translations
 * filters read from the fixture map.
 *
 * @package wp-job-manager
 */
class WP_Test_WPML_Delete_Translations extends WPJM_BaseTest {

	/**
	 * Translating the post type is WPML's job; emulate its normalisation.
	 *
	 * @var array<string, string>
	 */
	private $element_types = [
		'job_listing' => 'post_job_listing',
	];

	/**
	 * Trid per job ID, standing in for WPML's translations table.
	 *
	 * @var array<int, int>
	 */
	private $trids = [];

	/**
	 * Translations per trid, standing in for WPML's translations table.
	 *
	 * @var array<int, array<int, string>>
	 */
	private $translations = [];

	/**
	 * WPML's "delete translations" (`sync_delete`) setting.
	 *
	 * @var bool
	 */
	private $sync_delete = true;

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/3rd-party/wpml.php';

		// An employer has no delete capabilities, the same as on the frontend dashboard.
		$this->login_as_employer();

		add_filter( 'wpml_setting', [ $this, 'stub_setting' ], 10, 2 );
		add_filter( 'wpml_element_type', [ $this, 'stub_element_type' ] );
		add_filter( 'wpml_element_trid', [ $this, 'stub_element_trid' ], 10, 3 );
		add_filter( 'wpml_get_element_translations', [ $this, 'stub_element_translations' ], 10, 5 );
	}

	public function tearDown(): void {
		remove_filter( 'wpml_setting', [ $this, 'stub_setting' ], 10 );
		remove_filter( 'wpml_element_type', [ $this, 'stub_element_type' ] );
		remove_filter( 'wpml_element_trid', [ $this, 'stub_element_trid' ], 10 );
		remove_filter( 'wpml_get_element_translations', [ $this, 'stub_element_translations' ], 10 );

		$this->trids        = [];
		$this->translations = [];
		$this->sync_delete  = true;

		parent::tearDown();
	}

	/**
	 * @param mixed  $value Value passed to the filter.
	 * @param string $key   Setting key.
	 * @return mixed
	 */
	public function stub_setting( $value, $key ) {
		return 'sync_delete' === $key ? $this->sync_delete : $value;
	}

	/**
	 * @param string $element_type Element type passed to the filter.
	 * @return string
	 */
	public function stub_element_type( $element_type ) {
		return $this->element_types[ $element_type ] ?? $element_type;
	}

	/**
	 * @param mixed  $value      Value passed to the filter.
	 * @param int    $element_id Post ID.
	 * @param string $element_type Element type.
	 * @return int|null
	 */
	public function stub_element_trid( $value, $element_id, $element_type ) {
		// Only answer for the normalised post type, the way WPML does.
		if ( 'post_job_listing' !== $element_type ) {
			return $value;
		}

		return $this->trids[ $element_id ] ?? null;
	}

	/**
	 * @param mixed  $value          Value passed to the filter.
	 * @param int    $trid           Translation group ID.
	 * @param string $element_type   Element type.
	 * @param bool   $skip_empty     Whether to skip empty translations.
	 * @param bool   $all_statuses   Whether to include non-public translations.
	 * @return array<int, object>|null
	 */
	public function stub_element_translations( $value, $trid, $element_type, $skip_empty = false, $all_statuses = false ) {
		if ( 'post_job_listing' !== $element_type ) {
			return $value;
		}

		$translations = [];

		foreach ( $this->translations[ $trid ] ?? [] as $element_id ) {
			$translations[] = is_object( $element_id ) ? $element_id : (object) [ 'element_id' => $element_id ];
		}

		return $translations;
	}

	/**
	 * Records a listing and its translations against one trid.
	 *
	 * @param int   $job_id       Original listing ID.
	 * @param int[] $translation_ids Translation IDs sharing the original's trid.
	 * @param int   $trid         Optional explicit trid.
	 */
	private function register_translations( $job_id, array $translation_ids, $trid = 1 ) {
		$this->trids[ $job_id ] = $trid;
		$this->translations[ $trid ] = array_merge( [ $job_id ], $translation_ids );

		foreach ( $translation_ids as $translation_id ) {
			$this->trids[ $translation_id ] = $trid;
		}
	}

	/**
	 * Creates a published post, by default a job listing owned by the current user.
	 *
	 * @param int|null $author_id Author ID. Defaults to the current user.
	 * @param string   $post_type Post type.
	 * @return int Post ID.
	 */
	private function create_job( $author_id = null, $post_type = \WP_Job_Manager_Post_Types::PT_LISTING ) {
		return wp_insert_post(
			[
				'post_type'   => $post_type,
				'post_title'  => 'Test job',
				'post_status' => 'publish',
				'post_author' => $author_id ?? get_current_user_id(),
			]
		);
	}

	/**
	 * The filter is registered on the dashboard action.
	 */
	public function test_hook_is_registered() {
		do_action( 'wpml_loaded' );

		$this->assertNotFalse(
			has_action( 'job_manager_my_job_do_action', 'wpml_wpjm_delete_translations' ),
			'The translation cleanup must run on the dashboard action.'
		);
	}

	/**
	 * Deleting a listing trashes its translations too.
	 *
	 * This is the reported bug: the translations used to stay published.
	 */
	public function test_delete_trashes_translations() {
		$job_id        = $this->create_job();
		$translated_id = $this->create_job();
		$this->register_translations( $job_id, [ $translated_id ] );

		wp_trash_post( $job_id );
		wpml_wpjm_delete_translations( 'delete', $job_id );

		$this->assertTrashed( $translated_id );
	}

	/**
	 * When WPML's "delete translations" setting is off, translations are left alone, the
	 * same as when the listing is trashed in the admin.
	 */
	public function test_sync_delete_off_leaves_translations_alone() {
		$this->sync_delete = false;

		$job_id        = $this->create_job();
		$translated_id = $this->create_job();
		$this->register_translations( $job_id, [ $translated_id ] );

		wp_trash_post( $job_id );
		wpml_wpjm_delete_translations( 'delete', $job_id );

		$this->assertNotTrashed( $translated_id );
	}

	/**
	 * A translation owned by another user is not trashed, while one the employer owns is.
	 */
	public function test_translation_by_another_author_is_not_trashed() {
		$job_id    = $this->create_job();
		$own_id    = $this->create_job();
		$others_id = $this->create_job( $this->get_user_by_role( 'employer', '_b' ) );
		$this->register_translations( $job_id, [ $own_id, $others_id ] );

		wp_trash_post( $job_id );
		wpml_wpjm_delete_translations( 'delete', $job_id );

		$this->assertTrashed( $own_id );
		$this->assertNotTrashed( $others_id );
	}

	/**
	 * Only job listings are trashed, even if WPML returns another post type in the group.
	 */
	public function test_translation_that_is_not_a_listing_is_not_trashed() {
		$job_id  = $this->create_job();
		$post_id = $this->create_job( null, 'post' );
		$this->register_translations( $job_id, [ $post_id ] );

		wp_trash_post( $job_id );
		wpml_wpjm_delete_translations( 'delete', $job_id );

		$this->assertNotTrashed( $post_id );
	}

	/**
	 * The cleanup runs through the dashboard action, which is how the handler reaches it.
	 */
	public function test_delete_action_trashes_translations() {
		do_action( 'wpml_loaded' );

		$job_id        = $this->create_job();
		$translated_id = $this->create_job();
		$this->register_translations( $job_id, [ $translated_id ] );

		// Order used by the dashboard handler: trash the listing, then fire the action.
		wp_trash_post( $job_id );
		do_action( 'job_manager_my_job_do_action', 'delete', $job_id );

		$this->assertTrashed( $translated_id );
	}

	/**
	 * Every translation is trashed, not just the first.
	 */
	public function test_delete_trashes_every_translation() {
		$job_id    = $this->create_job();
		$second_id = $this->create_job();
		$third_id  = $this->create_job();
		$this->register_translations( $job_id, [ $second_id, $third_id ] );

		wp_trash_post( $job_id );
		wpml_wpjm_delete_translations( 'delete', $job_id );

		$this->assertTrashed( $second_id );
		$this->assertTrashed( $third_id );
	}

	/**
	 * The deleted listing is skipped, so the other translations are still reached when
	 * the user deletes a translation rather than the original.
	 */
	public function test_deleting_a_translation_trashes_the_others() {
		$job_id        = $this->create_job();
		$translated_id = $this->create_job();
		$this->register_translations( $job_id, [ $translated_id ] );

		// The user deletes the translated copy, not the original.
		wp_trash_post( $translated_id );
		wpml_wpjm_delete_translations( 'delete', $translated_id );

		$this->assertTrashed( $job_id );
	}

	/**
	 * Other dashboard actions must not trash translations.
	 */
	public function test_other_actions_leave_translations_alone() {
		$job_id        = $this->create_job();
		$translated_id = $this->create_job();
		$this->register_translations( $job_id, [ $translated_id ] );

		foreach ( [ 'mark_filled', 'mark_not_filled', 'duplicate', 'relist', 'renew', 'continue' ] as $action ) {
			wpml_wpjm_delete_translations( $action, $job_id );

			$this->assertNotTrashed( $translated_id );
		}
	}

	/**
	 * A listing with no trid has nothing to trash.
	 */
	public function test_listing_without_trid_is_a_no_op() {
		$job_id = $this->create_job();

		wpml_wpjm_delete_translations( 'delete', $job_id );

		// No exception and no unexpected trashing.
		$this->assertNotTrashed( $job_id );
	}

	/**
	 * A listing with a trid but no translations is a no-op.
	 */
	public function test_listing_without_translations_is_a_no_op() {
		$job_id = $this->create_job();
		$this->trids[ $job_id ] = 5;

		wpml_wpjm_delete_translations( 'delete', $job_id );

		$this->assertNotTrashed( $job_id );
	}

	/**
	 * Malformed translation entries are skipped rather than trashing post 0.
	 */
	public function test_translation_without_element_id_is_skipped() {
		$job_id = $this->create_job();
		$this->trids[ $job_id ] = 7;
		$this->translations[7]  = [ (object) [ 'element_id' => '' ] ];

		$trashed = 0;
		$count   = function () use ( &$trashed ) {
			$trashed++;
		};
		add_action( 'wp_trash_post', $count );

		wpml_wpjm_delete_translations( 'delete', $job_id );

		remove_action( 'wp_trash_post', $count );

		$this->assertSame( 0, $trashed, 'A translation without an element_id must not trigger a trash.' );
	}

	/**
	 * The trid lookup must receive the normalised element type, and translations must
	 * be requested for all statuses so a frontend employer is not filtered out by
	 * WPML's capability check.
	 */
	public function test_lookups_use_normalised_type_and_all_statuses() {
		$job_id        = $this->create_job();
		$translated_id = $this->create_job();
		$this->register_translations( $job_id, [ $translated_id ] );

		$trid_args = [];
		$capture   = function () use ( &$trid_args ) {
			$trid_args = func_get_args();
			return func_get_arg( 0 );
		};

		$translation_args = [];
		$record           = function () use ( &$translation_args ) {
			$translation_args = func_get_args();
			return func_get_arg( 0 );
		};

		add_filter( 'wpml_element_trid', $capture, 20, 3 );
		add_filter( 'wpml_get_element_translations', $record, 20, 6 );

		wp_trash_post( $job_id );
		wpml_wpjm_delete_translations( 'delete', $job_id );

		remove_filter( 'wpml_element_trid', $capture, 20 );
		remove_filter( 'wpml_get_element_translations', $record, 20 );

		$this->assertSame(
			'post_job_listing',
			$trid_args[2] ?? null,
			'The trid lookup must receive the post_-prefixed element type.'
		);
		$this->assertSame(
			'post_job_listing',
			$translation_args[2] ?? null,
			'The translations lookup must receive the post_-prefixed element type.'
		);
		$this->assertTrue(
			$translation_args[4] ?? null,
			'Translations must be requested for all statuses, not just public ones.'
		);
	}

	/**
	 * A caller firing the dashboard action with only the action, as older integrations
	 * do, must not trigger an argument error.
	 */
	public function test_missing_job_id_is_tolerated() {
		do_action( 'wpml_loaded' );

		$job_id        = $this->create_job();
		$translated_id = $this->create_job();
		$this->register_translations( $job_id, [ $translated_id ] );

		do_action( 'job_manager_my_job_do_action', 'delete' );

		$this->assertNotTrashed( $translated_id );
	}
}
