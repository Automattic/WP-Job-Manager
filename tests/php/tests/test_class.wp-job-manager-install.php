<?php
/**
 * Tests for WP_Job_Manager_Install.
 *
 * @package wp-job-manager
 */
class WP_Test_WP_Job_Manager_Install extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		delete_option( 'job_manager_workplace_type_migrated' );
		delete_option( 'job_manager_workplace_type_migration_cursor' );
		wp_unschedule_hook( 'job_manager_migrate_workplace_type' );
	}

	/**
	 * @return string|false The assigned workplace type term slug, or false if none.
	 */
	private function get_workplace_type_slug( $post_id ) {
		$terms = wp_get_object_terms( $post_id, \WP_Job_Manager_Post_Types::TAX_WORKPLACE_TYPE );
		return empty( $terms ) ? false : $terms[0]->slug;
	}

	/**
	 * Existing listings are backfilled from the legacy `_remote_position` checkbox meta:
	 * truthy meta becomes the Remote term, everything else becomes On-Site.
	 *
	 * @covers WP_Job_Manager_Install::run_workplace_type_migration_batch
	 */
	public function test_migration_backfills_from_legacy_meta() {
		$remote_id     = $this->factory->job_listing->create( [ 'meta_input' => [ '_remote_position' => 1 ] ] );
		$non_remote_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_remote_position' => 0 ] ] );
		$untagged_id   = $this->factory->job_listing->create();

		WP_Job_Manager_Install::run_workplace_type_migration_batch();

		$this->assertSame( 'remote', $this->get_workplace_type_slug( $remote_id ) );
		$this->assertSame( 'on-site', $this->get_workplace_type_slug( $non_remote_id ) );
		$this->assertSame( 'on-site', $this->get_workplace_type_slug( $untagged_id ) );
		$this->assertSame( 1, intval( get_option( 'job_manager_workplace_type_migrated' ) ), 'a batch smaller than the page size should mark the migration complete' );
	}

	/**
	 * Re-running the migration must not overwrite listings that already carry a workplace
	 * type term (e.g. Hybrid, which has no legacy meta signal to backfill from).
	 *
	 * @covers WP_Job_Manager_Install::run_workplace_type_migration_batch
	 */
	public function test_migration_is_idempotent_and_preserves_existing_terms() {
		$hybrid_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_remote_position' => 1 ] ] );
		wp_set_object_terms( $hybrid_id, 'hybrid', \WP_Job_Manager_Post_Types::TAX_WORKPLACE_TYPE );

		WP_Job_Manager_Install::run_workplace_type_migration_batch();

		$this->assertSame( 'hybrid', $this->get_workplace_type_slug( $hybrid_id ) );
	}

	/**
	 * The three default workplace type terms are seeded by the migration, even on sites where
	 * `default_terms()` already ran (and so skipped) before this taxonomy existed.
	 *
	 * @covers WP_Job_Manager_Install::run_workplace_type_migration_batch
	 */
	public function test_migration_seeds_default_terms() {
		foreach ( [ 'on-site', 'remote', 'hybrid' ] as $slug ) {
			$term = get_term_by( 'slug', $slug, \WP_Job_Manager_Post_Types::TAX_WORKPLACE_TYPE );
			if ( $term ) {
				wp_delete_term( $term->term_id, \WP_Job_Manager_Post_Types::TAX_WORKPLACE_TYPE );
			}
		}

		WP_Job_Manager_Install::run_workplace_type_migration_batch();

		foreach ( [ 'on-site', 'remote', 'hybrid' ] as $slug ) {
			$this->assertNotFalse(
				get_term_by( 'slug', $slug, \WP_Job_Manager_Post_Types::TAX_WORKPLACE_TYPE ),
				"the '{$slug}' workplace type term should exist"
			);
		}
	}

	/**
	 * A full page of results means there may be more to migrate: the batch must record its
	 * cursor and reschedule itself instead of marking the migration complete.
	 *
	 * @covers WP_Job_Manager_Install::run_workplace_type_migration_batch
	 */
	public function test_migration_reschedules_when_a_full_batch_is_processed() {
		add_filter(
			'job_manager_workplace_type_migration_batch_size',
			function () {
				return 1;
			}
		);

		$this->factory->job_listing->create_many( 2 );

		WP_Job_Manager_Install::run_workplace_type_migration_batch();

		$this->assertFalse( (bool) get_option( 'job_manager_workplace_type_migrated' ) );
		$this->assertSame( 2, intval( get_option( 'job_manager_workplace_type_migration_cursor' ) ) );
		$this->assertNotFalse( wp_next_scheduled( 'job_manager_migrate_workplace_type' ), 'the next batch should be scheduled' );
	}

	/**
	 * When the workplace type feature is disabled the taxonomy isn't registered, so the batch
	 * must retry later rather than run against a taxonomy that doesn't exist or mark completion.
	 *
	 * @covers WP_Job_Manager_Install::run_workplace_type_migration_batch
	 */
	public function test_migration_retries_when_taxonomy_unavailable() {
		update_option( 'job_manager_enable_remote_position', 0 );

		WP_Job_Manager_Install::run_workplace_type_migration_batch();

		$this->assertFalse( (bool) get_option( 'job_manager_workplace_type_migrated' ) );
		$this->assertNotFalse( wp_next_scheduled( 'job_manager_migrate_workplace_type' ), 'a retry should be scheduled' );

		update_option( 'job_manager_enable_remote_position', 1 );
	}
}
