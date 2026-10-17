<?php

/**
 * @group wpml
 */
class WP_Test_WP_Job_Manager_WPML extends WPJM_BaseTest {
	private $current_language = 'en';

	public function setUp(): void {
		parent::setUp();
		delete_option( 'job_manager_wpml_rewrite_slugs' );
		remove_action( 'wp_loaded', 'wpml_wpjm_flush_rewrite_rules' );
		$this->current_language = 'en';
		add_filter( 'wpml_default_language', [ $this, 'default_language' ] );
		add_filter( 'wpml_current_language', [ $this, 'current_language' ] );
		// The test bootstrap defines DOING_AJAX.
		add_filter( 'wp_doing_ajax', '__return_false' );
	}

	public function tearDown(): void {
		remove_filter( 'wpml_default_language', [ $this, 'default_language' ] );
		remove_filter( 'wpml_current_language', [ $this, 'current_language' ] );
		remove_filter( 'wp_doing_ajax', '__return_false' );
		remove_action( 'wp_loaded', 'wpml_wpjm_flush_rewrite_rules' );
		set_current_screen( 'front' );
		parent::tearDown();
	}

	public function default_language() {
		return 'en';
	}

	public function current_language() {
		return $this->current_language;
	}

	/**
	 * @covers ::wpml_wpjm_force_static_slugs
	 */
	public function test_saves_slugs_from_default_language_request() {
		$permalinks = $this->get_permalinks( 'poste', 'categorie-poste', 'type-poste', 'job-listings' );

		$this->assertSame( $permalinks, wpml_wpjm_force_static_slugs( $permalinks ) );
		$this->assertSame(
			[
				'job_rewrite_slug'          => 'poste',
				'category_rewrite_slug'     => 'categorie-poste',
				'type_rewrite_slug'         => 'type-poste',
				'jobs_archive_rewrite_slug' => 'job-listings',
				'language'                  => 'en',
			],
			get_option( 'job_manager_wpml_rewrite_slugs' )
		);
		$this->assertNotFalse( has_action( 'wp_loaded', 'wpml_wpjm_flush_rewrite_rules' ) );
	}

	/**
	 * @covers ::wpml_wpjm_force_static_slugs
	 */
	public function test_does_not_save_slugs_from_secondary_language_request() {
		$this->current_language = 'es';
		$permalinks             = $this->get_permalinks( 'trabajo', 'categoria-trabajo', 'tipo-trabajo', 'job-listings' );

		$this->assertSame( $permalinks, wpml_wpjm_force_static_slugs( $permalinks ) );
		$this->assertFalse( get_option( 'job_manager_wpml_rewrite_slugs' ) );
		$this->assertFalse( has_action( 'wp_loaded', 'wpml_wpjm_flush_rewrite_rules' ) );
	}

	/**
	 * @covers ::wpml_wpjm_force_static_slugs
	 */
	public function test_does_not_save_slugs_before_language_is_known() {
		$this->current_language = null;

		wpml_wpjm_force_static_slugs( $this->get_permalinks( 'job', 'job-category', 'job-type', 'job-listings' ) );

		$this->assertFalse( get_option( 'job_manager_wpml_rewrite_slugs' ) );
	}

	/**
	 * @covers ::wpml_wpjm_force_static_slugs
	 */
	public function test_does_not_save_slugs_from_admin_request() {
		set_current_screen( 'dashboard' );
		$permalinks = $this->get_permalinks( 'job', 'job-category', 'job-type', 'job-listings' );

		wpml_wpjm_force_static_slugs( $permalinks );

		$this->assertFalse( get_option( 'job_manager_wpml_rewrite_slugs' ) );
	}

	/**
	 * @covers ::wpml_wpjm_force_static_slugs
	 */
	public function test_uses_saved_slugs_in_secondary_language() {
		update_option(
			'job_manager_wpml_rewrite_slugs',
			[
				'job_rewrite_slug'          => 'job',
				'category_rewrite_slug'     => 'job-category',
				'type_rewrite_slug'         => 'job-type',
				'jobs_archive_rewrite_slug' => 'job-listings',
				'language'                  => 'en',
			]
		);
		$this->current_language = 'es';

		$result = wpml_wpjm_force_static_slugs( $this->get_permalinks( 'trabajo', 'categoria-trabajo', 'tipo-trabajo', 'job-listings' ) );

		$this->assertSame( 'job', $result['job_rewrite_slug'] );
		$this->assertSame( 'job-category', $result['category_rewrite_slug'] );
		$this->assertSame( 'job-type', $result['type_rewrite_slug'] );
		$this->assertSame( 'job-listings', $result['jobs_archive_rewrite_slug'] );
		$this->assertFalse( has_action( 'wp_loaded', 'wpml_wpjm_flush_rewrite_rules' ) );
	}

	/**
	 * @covers ::wpml_wpjm_force_static_slugs
	 */
	public function test_keeps_custom_bases() {
		update_option(
			'job_manager_wpml_rewrite_slugs',
			[
				'job_rewrite_slug'          => 'job',
				'category_rewrite_slug'     => 'job-category',
				'type_rewrite_slug'         => 'job-type',
				'jobs_archive_rewrite_slug' => 'job-listings',
				'language'                  => 'en',
			]
		);
		$permalinks                          = $this->get_permalinks( 'careers', 'job-category', 'job-type', 'job-listings' );
		$permalinks['job_base']              = 'careers';
		$permalinks['category_base']         = 'career-area';
		$permalinks['category_rewrite_slug'] = 'career-area';

		$result = wpml_wpjm_force_static_slugs( $permalinks );

		$this->assertSame( 'careers', $result['job_rewrite_slug'] );
		$this->assertSame( 'career-area', $result['category_rewrite_slug'] );
		$this->assertSame( 'job-type', $result['type_rewrite_slug'] );
	}

	/**
	 * @covers ::wpml_wpjm_force_static_slugs
	 */
	public function test_picks_slugs_again_when_default_language_changes() {
		update_option(
			'job_manager_wpml_rewrite_slugs',
			[
				'job_rewrite_slug' => 'trabajo',
				'language'         => 'es',
			]
		);

		$result = wpml_wpjm_force_static_slugs( $this->get_permalinks( 'job', 'job-category', 'job-type', 'job-listings' ) );

		$this->assertSame( 'job', $result['job_rewrite_slug'] );
		$this->assertSame( 'en', get_option( 'job_manager_wpml_rewrite_slugs' )['language'] );
	}

	/**
	 * @covers ::wpml_wpjm_reset_static_slugs
	 */
	public function test_permalink_settings_change_clears_saved_slugs() {
		add_action( 'update_option_' . WP_Job_Manager_Post_Types::PERMALINK_OPTION_NAME, 'wpml_wpjm_reset_static_slugs' );
		update_option( 'job_manager_wpml_rewrite_slugs', [ 'job_rewrite_slug' => 'careers' ] );

		update_option( WP_Job_Manager_Post_Types::PERMALINK_OPTION_NAME, wp_json_encode( [ 'job_base' => 'vacancy' ] ) );
		remove_action( 'update_option_' . WP_Job_Manager_Post_Types::PERMALINK_OPTION_NAME, 'wpml_wpjm_reset_static_slugs' );

		$this->assertFalse( get_option( 'job_manager_wpml_rewrite_slugs' ) );
	}

	private function get_permalinks( $job, $category, $type, $archive ) {
		return [
			'job_base'                  => '',
			'category_base'             => '',
			'type_base'                 => '',
			'jobs_archive'              => '',
			'job_rewrite_slug'          => $job,
			'category_rewrite_slug'     => $category,
			'type_rewrite_slug'         => $type,
			'jobs_archive_rewrite_slug' => $archive,
		];
	}
}
