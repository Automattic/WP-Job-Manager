<?php
/**
 * Tests the retroactive company-logo attachment de-duplicator: it collapses
 * identical logo attachments a single owner accumulated (e.g. from re-uploading
 * the same logo on every submission) down to one, re-pointing every reference
 * before deleting the redundant copies.
 *
 * @package wp-job-manager
 */

use WP_Job_Manager\Attachment_Deduplicator;

class WP_Test_Attachment_Deduplicator extends WPJM_BaseTest {

	/**
	 * Files written into the uploads dir, removed on teardown.
	 *
	 * @var string[]
	 */
	private $created_files = [];

	public function tearDown(): void {
		foreach ( $this->created_files as $path ) {
			if ( file_exists( $path ) ) {
				unlink( $path );
			}
		}
		$this->created_files = [];
		parent::tearDown();
	}

	/**
	 * A minimal but valid 1x1 PNG.
	 *
	 * @return string Raw PNG bytes.
	 */
	private function png_bytes() {
		return base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' );
	}

	/**
	 * Creates an image attachment owned by a user with a real file on disk (so its
	 * content hash can be computed) and no dedup hash meta, mirroring a legacy
	 * duplicate created before the runtime dedup fix.
	 *
	 * @param int    $author   Owner user ID.
	 * @param string $bytes    Raw file bytes.
	 * @param string $filename File name.
	 * @return int Attachment ID.
	 */
	private function create_logo_attachment( $author, $bytes, $filename ) {
		$upload_dir = wp_upload_dir();
		$path       = trailingslashit( $upload_dir['path'] ) . $filename;
		file_put_contents( $path, $bytes );
		$this->created_files[] = $path;

		return (int) wp_insert_attachment(
			[
				'post_mime_type' => 'image/png',
				'post_title'     => $filename,
				'post_status'    => 'inherit',
				'post_author'    => $author,
			],
			$path
		);
	}

	/**
	 * Creates a job listing owned by a user with the given featured image.
	 *
	 * @param int $author        Owner user ID.
	 * @param int $attachment_id Featured image attachment.
	 * @return int Listing ID.
	 */
	private function create_listing_with_logo( $author, $attachment_id ) {
		$job_id = $this->factory->post->create(
			[
				'post_type'   => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_author' => $author,
			]
		);
		set_post_thumbnail( $job_id, $attachment_id );

		return $job_id;
	}

	/**
	 * A live run collapses a user's identical logo attachments to the oldest one,
	 * re-points listings' featured images to it, and deletes the redundant copies.
	 */
	public function test_live_run_merges_identical_logos_and_repoints_thumbnails() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$canonical = $this->create_logo_attachment( $author, $bytes, 'logo.png' );   // Older (lower ID).
		$duplicate = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' ); // Newer identical copy.

		$job_canonical = $this->create_listing_with_logo( $author, $canonical );
		$job_duplicate = $this->create_listing_with_logo( $author, $duplicate );

		$report = ( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertNull( get_post( $duplicate ), 'The redundant duplicate attachment must be deleted.' );
		$this->assertInstanceOf( WP_Post::class, get_post( $canonical ), 'The canonical attachment must survive.' );
		$this->assertEquals( $canonical, get_post_thumbnail_id( $job_duplicate ), 'The listing on the duplicate must be re-pointed to the canonical.' );
		$this->assertEquals( $canonical, get_post_thumbnail_id( $job_canonical ), 'The listing already on the canonical is unchanged.' );
		$this->assertSame( 1, $report['attachments_deleted'], 'Exactly one duplicate attachment should be deleted.' );
	}

	/**
	 * Identical logos owned by different users are never merged: cross-user reuse
	 * is exactly the ownership boundary the runtime dedup preserves.
	 */
	public function test_identical_logos_of_different_users_are_not_merged() {
		$user_a = $this->factory->user->create( [ 'role' => 'employer' ] );
		$user_b = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$logo_a = $this->create_logo_attachment( $user_a, $bytes, 'a.png' );
		$logo_b = $this->create_logo_attachment( $user_b, $bytes, 'b.png' );
		$this->create_listing_with_logo( $user_a, $logo_a );
		$this->create_listing_with_logo( $user_b, $logo_b );

		$report = ( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertInstanceOf( WP_Post::class, get_post( $logo_a ), "User A's logo must survive." );
		$this->assertInstanceOf( WP_Post::class, get_post( $logo_b ), "User B's identical logo must survive — not merged across users." );
		$this->assertSame( 0, $report['attachments_deleted'] );
	}

	/**
	 * A dry run (the default) reports the duplicates it would collapse but mutates
	 * nothing — no attachment deleted, no reference re-pointed.
	 */
	public function test_dry_run_is_the_default_and_mutates_nothing() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$duplicate = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		$job       = $this->create_listing_with_logo( $author, $duplicate );

		$report = ( new Attachment_Deduplicator() )->run();

		$this->assertInstanceOf( WP_Post::class, get_post( $duplicate ), 'Dry run must not delete the duplicate.' );
		$this->assertEquals( $duplicate, get_post_thumbnail_id( $job ), 'Dry run must not re-point references.' );
		$this->assertSame( 1, $report['duplicates'], 'Dry run still reports the duplicate it would collapse.' );
		$this->assertSame( 0, $report['attachments_deleted'] );
	}

	/**
	 * The user_id argument limits de-duplication to a single owner, leaving other
	 * owners' duplicates untouched.
	 */
	public function test_user_id_limits_dedupe_to_one_owner() {
		$user_a = $this->factory->user->create( [ 'role' => 'employer' ] );
		$user_b = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$a1 = $this->create_logo_attachment( $user_a, $bytes, 'a1.png' );
		$a2 = $this->create_logo_attachment( $user_a, $bytes, 'a2.png' );
		$b1 = $this->create_logo_attachment( $user_b, $bytes, 'b1.png' );
		$b2 = $this->create_logo_attachment( $user_b, $bytes, 'b2.png' );
		$this->create_listing_with_logo( $user_a, $a1 );
		$this->create_listing_with_logo( $user_a, $a2 );
		$this->create_listing_with_logo( $user_b, $b1 );
		$this->create_listing_with_logo( $user_b, $b2 );

		$report = ( new Attachment_Deduplicator() )->run( [ 'dry_run' => false, 'user_id' => $user_a ] );

		$this->assertSame( 1, $report['attachments_deleted'], "Only user A's duplicate is deleted." );
		$this->assertInstanceOf( WP_Post::class, get_post( $b1 ), "User B's duplicates are untouched." );
		$this->assertInstanceOf( WP_Post::class, get_post( $b2 ), "User B's duplicates are untouched." );
	}

	/**
	 * The reporter's real shape: many identical copies, only some still referenced
	 * and the rest orphaned. A live run collapses them all to the canonical and
	 * re-points the referenced listing onto it — even when the canonical is one of
	 * the orphans.
	 */
	public function test_live_run_collapses_orphaned_duplicate_copies() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$canonical  = $this->create_logo_attachment( $author, $bytes, 'logo.png' );   // Oldest — orphan, becomes canonical.
		$orphan     = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' ); // Orphan copy.
		$referenced = $this->create_logo_attachment( $author, $bytes, 'logo-2.png' ); // In-use copy.
		$job        = $this->create_listing_with_logo( $author, $referenced );

		$report = ( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertInstanceOf( WP_Post::class, get_post( $canonical ), 'The canonical survives.' );
		$this->assertNull( get_post( $orphan ), 'The orphaned copy is deleted.' );
		$this->assertNull( get_post( $referenced ), 'The redundant in-use copy is deleted after re-pointing.' );
		$this->assertEquals( $canonical, get_post_thumbnail_id( $job ), 'The listing is re-pointed onto the canonical.' );
		$this->assertSame( 2, $report['attachments_deleted'], 'Both redundant copies are deleted.' );
	}
}
