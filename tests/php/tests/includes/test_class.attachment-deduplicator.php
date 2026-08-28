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

	/**
	 * An attachment that happens to share a logo's bytes but is referenced somewhere
	 * the de-duplicator does not know how to re-point (here: the featured image of an
	 * ordinary post) must never be deleted. Content-identical is not logo-identical,
	 * and deleting it would silently strip that post's featured image.
	 */
	public function test_attachment_referenced_outside_listings_is_never_deleted() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$logo       = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$blog_image = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		$this->create_listing_with_logo( $author, $logo );

		$blog_post = $this->factory->post->create( [ 'post_type' => 'post', 'post_author' => $author ] );
		set_post_thumbnail( $blog_post, $blog_image );

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertInstanceOf( WP_Post::class, get_post( $blog_image ), "An image used as another post type's featured image must survive." );
		$this->assertEquals( $blog_image, get_post_thumbnail_id( $blog_post ), "The blog post's featured image must be untouched." );
	}

	/**
	 * An attachment referenced inline in another post's content must not be deleted:
	 * nothing re-points post_content, so deleting it 404s the image in that post.
	 */
	public function test_attachment_referenced_in_post_content_is_never_deleted() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$logo   = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$inline = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		$this->create_listing_with_logo( $author, $logo );

		$this->factory->post->create(
			[
				'post_type'    => 'post',
				'post_content' => '<img src="' . wp_get_attachment_url( $inline ) . '" class="wp-image-' . $inline . '" />',
			]
		);

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertInstanceOf( WP_Post::class, get_post( $inline ), 'An image referenced inline in post content must survive.' );
	}

	/**
	 * Expired listings still reference their logo and must be re-pointed like any
	 * other. WP_Query's 'any' excludes statuses registered exclude_from_search, so a
	 * scan using it treats an expired listing's logo as an orphan, deletes it, and
	 * leaves the listing pointing at a deleted attachment. Expired is the normal end
	 * state of a listing, not an edge case.
	 */
	public function test_expired_listing_references_are_repointed_not_orphaned() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$canonical = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$duplicate = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );

		$this->create_listing_with_logo( $author, $canonical );

		$expired = $this->create_listing_with_logo( $author, $duplicate );
		wp_update_post( [ 'ID' => $expired, 'post_status' => 'expired' ] );

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertEquals( $canonical, get_post_thumbnail_id( $expired ), 'The expired listing must be re-pointed onto the canonical, not left dangling.' );
	}

	/**
	 * Guest submissions all have post_author = 0, which is not an owner. Merging on
	 * it collapses unrelated anonymous submitters' logos together — the inverse of
	 * the ownership boundary the runtime dedup enforces by refusing guests outright.
	 */
	public function test_guest_owned_attachments_are_never_merged() {
		$bytes = $this->png_bytes();

		$guest_a = $this->create_logo_attachment( 0, $bytes, 'guest-a.png' );
		$guest_b = $this->create_logo_attachment( 0, $bytes, 'guest-b.png' );
		$this->create_listing_with_logo( 0, $guest_a );
		$this->create_listing_with_logo( 0, $guest_b );

		$report = ( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertInstanceOf( WP_Post::class, get_post( $guest_a ), "One guest's logo must survive." );
		$this->assertInstanceOf( WP_Post::class, get_post( $guest_b ), "Another guest's identical logo must survive — guests are not a shared owner." );
		$this->assertSame( 0, $report['attachments_deleted'] );
		$this->assertSame( 2, $report['skipped_guest'], 'Both guest uploads are counted as unexamined, so the residue outside the run is measured, not silent.' );
		$this->assertSame( 0, $report['skipped_unmatched'], 'Guest uploads are counted once, as guest-owned — not again as unmatched.' );
	}

	/**
	 * The report counts the image attachments the run never examined — guest
	 * uploads, and images matching no currently-referenced logo — separately from
	 * the duplicates it found. Those counts are what tells an operator whether the
	 * library's remaining bloat is inside or outside this command's reach.
	 */
	public function test_report_counts_unexamined_images_by_reason() {
		$author   = $this->factory->user->create( [ 'role' => 'employer' ] );
		$stranger = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes    = $this->png_bytes();

		// A referenced logo with one duplicate: both match, one is redundant.
		$canonical = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$duplicate = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		$this->create_listing_with_logo( $author, $canonical );
		$this->create_listing_with_logo( $author, $duplicate );

		// Unanchored: different bytes from any referenced logo, so nothing marks
		// them as logo duplicates — one owned by the logo owner, one by a user
		// with no listings at all.
		$unrelated = $this->create_logo_attachment( $author, $bytes . "\0", 'photo.png' );
		$abandoned = $this->create_logo_attachment( $stranger, $bytes . "\0\0", 'old-logo.png' );

		// Guest-owned: counted under its own reason, not as unmatched.
		$guest = $this->create_logo_attachment( 0, $bytes, 'guest.png' );

		$report = ( new Attachment_Deduplicator() )->run();

		$this->assertSame( 1, $report['duplicates'], 'The matched duplicate is planned for collapse, not counted as skipped. Report: ' . wp_json_encode( $report ) );
		$this->assertSame( 1, $report['skipped_guest'] );
		$this->assertSame( 2, $report['skipped_unmatched'], 'Both unanchored images are counted, whether their owner has listings or not.' );
		$this->assertInstanceOf( WP_Post::class, get_post( $unrelated ), 'Counting an image as unexamined must not touch it.' );
		$this->assertInstanceOf( WP_Post::class, get_post( $abandoned ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $guest ) );
	}

	/**
	 * With user_id set, the unexamined counts describe that owner's library, not
	 * the whole site — a per-owner run reports a per-owner residue.
	 */
	public function test_unexamined_counts_are_scoped_to_the_requested_owner() {
		$user_a = $this->factory->user->create( [ 'role' => 'employer' ] );
		$user_b = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$a1 = $this->create_logo_attachment( $user_a, $bytes, 'a1.png' );
		$a2 = $this->create_logo_attachment( $user_a, $bytes, 'a2.png' );
		$this->create_listing_with_logo( $user_a, $a1 );
		$this->create_listing_with_logo( $user_a, $a2 );
		$this->create_logo_attachment( $user_a, $bytes . "\0", 'a-photo.png' );

		// Outside the requested scope entirely: another owner's unanchored image
		// and a guest upload.
		$this->create_logo_attachment( $user_b, $bytes . "\0\0", 'b-photo.png' );
		$this->create_logo_attachment( 0, $bytes, 'guest.png' );

		$report = ( new Attachment_Deduplicator() )->run( [ 'user_id' => $user_a ] );

		$this->assertSame( 1, $report['duplicates'] );
		$this->assertSame( 1, $report['skipped_unmatched'], "Only owner A's unanchored image is counted. Report: " . wp_json_encode( $report ) );
		$this->assertSame( 0, $report['skipped_guest'], 'Guest uploads are outside a per-owner scope, not part of its residue.' );
	}

	/**
	 * Two attachment rows can point at the same file on disk. Deleting one with
	 * force_delete unlinks the shared file, leaving the canonical as a row whose
	 * file no longer exists.
	 */
	public function test_deleting_a_duplicate_never_removes_a_file_the_canonical_still_uses() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$canonical = $this->create_logo_attachment( $author, $bytes, 'shared.png' );
		$path      = get_attached_file( $canonical );

		// A second row over the same file, as pre-dedup submission flows could produce.
		$duplicate = (int) wp_insert_attachment(
			[
				'post_mime_type' => 'image/png',
				'post_title'     => 'shared-copy',
				'post_status'    => 'inherit',
				'post_author'    => $author,
			],
			$path
		);

		$this->create_listing_with_logo( $author, $canonical );
		$this->create_listing_with_logo( $author, $duplicate );

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertInstanceOf( WP_Post::class, get_post( $canonical ), 'The canonical attachment survives.' );
		$this->assertFileExists( $path, "The canonical's file must not be unlinked by deleting a row that shared its path." );
	}

	/**
	 * The canonical is the oldest attachment, which is typically the auto-generated
	 * upload with no alt text. Alt text an editor curated on a later copy is about to
	 * be deleted, so carry it over rather than losing it.
	 */
	public function test_curated_alt_text_is_carried_over_to_the_canonical() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$canonical = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$curated   = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		update_post_meta( $curated, '_wp_attachment_image_alt', 'Acme Corporation logo' );

		$this->create_listing_with_logo( $author, $canonical );
		$this->create_listing_with_logo( $author, $curated );

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertSame( 'Acme Corporation logo', get_post_meta( $canonical, '_wp_attachment_image_alt', true ), 'Curated alt text must be carried onto the canonical before the copy is deleted.' );
	}

	/**
	 * The canonical is given the content hash the runtime dedup looks up, so future
	 * identical uploads are de-duplicated against it instead of starting a new pile.
	 */
	public function test_canonical_is_backfilled_with_the_runtime_dedup_hash() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$canonical = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		$this->create_listing_with_logo( $author, $canonical );

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertSame( md5( $bytes ), get_post_meta( $canonical, Attachment_Deduplicator::HASH_META_KEY, true ), 'The canonical must carry the runtime dedup hash.' );
	}

	/**
	 * Scoping to one owner means the owner of the *attachments*, not of the listings
	 * referencing them. Since #3060 a listing authored by one user can carry a logo
	 * owned by another, so scoping by listing author makes the owner's own duplicates
	 * invisible and reports a confidently-wrong "nothing to do".
	 */
	public function test_user_scope_follows_attachment_owner_not_listing_author() {
		$owner     = $this->factory->user->create( [ 'role' => 'employer' ] );
		$publisher = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes     = $this->png_bytes();

		$canonical = $this->create_logo_attachment( $owner, $bytes, 'logo.png' );
		$duplicate = $this->create_logo_attachment( $owner, $bytes, 'logo-1.png' );

		// Both listings are authored by someone other than the attachments' owner.
		$this->create_listing_with_logo( $publisher, $canonical );
		$this->create_listing_with_logo( $publisher, $duplicate );

		$report = ( new Attachment_Deduplicator() )->run( [ 'dry_run' => false, 'user_id' => $owner ] );

		$this->assertSame( 1, $report['attachments_deleted'], "The owner's duplicate must be found even though the listings belong to someone else." );
		$this->assertNull( get_post( $duplicate ) );
	}

	/**
	 * Work per duplicate must not scale as a fixed multiple of queries: this command
	 * targets sites with tens of thousands of attachments, where a handful of queries
	 * per duplicate is the difference between finishing and timing out. The budget is
	 * deliberately loose — it exists to catch a return to per-duplicate scanning, not
	 * to pin an exact number.
	 */
	public function test_query_count_does_not_scale_with_the_number_of_duplicates() {
		global $wpdb;

		$bytes = $this->png_bytes();

		// Several owners, each with their own duplicate pile and a `_company_logo`
		// default: the cost must not scale with owners either, which a single-owner
		// fixture would not catch.
		for ( $owner = 0; $owner < 3; $owner++ ) {
			$author = $this->factory->user->create( [ 'role' => 'employer' ] );
			$bytes  = $this->png_bytes() . str_repeat( "\0", $owner + 1 );

			$canonical = $this->create_logo_attachment( $author, $bytes, "o{$owner}-logo.png" );
			$this->create_listing_with_logo( $author, $canonical );
			update_user_meta( $author, '_company_logo', $canonical );

			for ( $i = 1; $i <= 10; $i++ ) {
				$duplicate = $this->create_logo_attachment( $author, $bytes, "o{$owner}-logo-{$i}.png" );
				$this->create_listing_with_logo( $author, $duplicate );
			}
		}

		$before = $wpdb->num_queries;
		$report = ( new Attachment_Deduplicator() )->run( [ 'dry_run' => true ] );
		$used   = $wpdb->num_queries - $before;

		$this->assertSame( 30, $report['duplicates'], 'All duplicates across all owners are found. Report: ' . wp_json_encode( $report ) );
		// 18 covers the batched scans plus fixed overhead (the two unexamined-image
		// COUNTs among it); per-duplicate scanning would use several times this.
		$this->assertLessThan( 18, $used, sprintf( 'A dry run over 30 duplicates across 3 owners used %d queries; scanning should be batched, not per-duplicate or per-owner.', $used ) );
	}

	/**
	 * A failed deletion is reported rather than counted as a success. Because
	 * references are moved before anything is deleted, a failure leaves a redundant
	 * attachment behind — never a listing pointing at a post that no longer exists.
	 */
	public function test_failed_deletion_is_reported_and_leaves_references_intact() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$canonical = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$duplicate = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );

		$this->create_listing_with_logo( $author, $canonical );
		$job = $this->create_listing_with_logo( $author, $duplicate );

		$fail = static function () {
			return false;
		};
		add_filter( 'pre_delete_attachment', $fail );
		$report = ( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );
		remove_filter( 'pre_delete_attachment', $fail );

		$this->assertSame( 0, $report['attachments_deleted'], 'A failed delete must not be counted as deleted.' );
		$this->assertSame( [ $duplicate ], $report['delete_failures'], 'The failed attachment must be reported.' );
		$this->assertEquals( $canonical, get_post_thumbnail_id( $job ), 'References were moved first, so the listing points at the canonical regardless.' );
	}

	/**
	 * Batching exists so a library with tens of thousands of attachments does not
	 * have to be held in memory at once. The result must not depend on where the
	 * batch boundaries happen to fall, including protections and re-points for
	 * candidates that land in different batches from each other.
	 */
	public function test_results_are_identical_across_batch_boundaries() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$canonical = $this->create_logo_attachment( $author, $bytes, 'batch.png' );
		$this->create_listing_with_logo( $author, $canonical );

		$duplicates = [];
		for ( $i = 1; $i <= 7; $i++ ) {
			$duplicates[] = $this->create_logo_attachment( $author, $bytes, "batch-{$i}.png" );
		}
		foreach ( $duplicates as $duplicate ) {
			$this->create_listing_with_logo( $author, $duplicate );
		}

		// One protected candidate, deliberately mid-run rather than first or last, and
		// protected via inline content — the same batch size drives the content scan,
		// so the protecting post must be found even when it lands on a later page.
		$protected = $duplicates[4];

		// Filler posts carrying an image marker so they are scanned, pushing the real
		// reference past the first content-scan page.
		for ( $i = 0; $i < 5; $i++ ) {
			$this->factory->post->create(
				[
					'post_type'    => 'post',
					'post_content' => '<img src="https://example.test/wp-content/uploads/filler-' . $i . '.png" />',
				]
			);
		}
		$this->factory->post->create(
			[
				'post_type'    => 'post',
				'post_content' => '<img src="' . wp_get_attachment_url( $protected ) . '" class="wp-image-' . $protected . '" />',
			]
		);

		// A batch size well below the number of candidates forces several batches.
		$report = ( new Attachment_Deduplicator( 2 ) )->run( [ 'dry_run' => false ] );

		$this->assertSame( 6, $report['attachments_deleted'], 'Every unprotected duplicate is deleted regardless of batching.' );
		$this->assertSame( 1, $report['skipped_referenced'], 'The protected candidate is kept even though it sits mid-batch.' );
		$this->assertInstanceOf( WP_Post::class, get_post( $protected ), 'The protected attachment survives, found by a content scan on a later page.' );

		foreach ( $duplicates as $duplicate ) {
			if ( $duplicate === $protected ) {
				continue;
			}
			$this->assertNull( get_post( $duplicate ), "Duplicate {$duplicate} is deleted." );
		}
	}

	/**
	 * Plenty of plugins store attachment IDs inside a serialized array rather than as
	 * a bare value — an ACF gallery or repeater field, for one. A sweep that compares
	 * `meta_value` to the candidate ID never matches those, so the reference is
	 * invisible and the image is deleted out from under it.
	 */
	public function test_attachment_referenced_from_a_serialized_meta_value_is_never_deleted() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$logo    = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$gallery = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		$this->create_listing_with_logo( $author, $logo );

		$page = $this->factory->post->create( [ 'post_type' => 'page' ] );
		update_post_meta( $page, '_gallery_images', [ 4242, $gallery ] );

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertInstanceOf( WP_Post::class, get_post( $gallery ), 'An attachment referenced from inside a serialized meta value must survive.' );
	}

	/**
	 * The other common shape is a comma-separated list of IDs (WooCommerce's
	 * `_product_image_gallery`). MySQL coerces `"4242,53"` to 4242, so every ID after
	 * the first looks unreferenced — protection that is silently partial is worse
	 * than none, because the key looks covered.
	 */
	public function test_attachment_referenced_from_a_comma_separated_meta_value_is_never_deleted() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$logo    = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$gallery = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		$this->create_listing_with_logo( $author, $logo );

		$page = $this->factory->post->create( [ 'post_type' => 'page' ] );
		update_post_meta( $page, '_product_image_gallery', '4242,' . $gallery );

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertInstanceOf( WP_Post::class, get_post( $gallery ), 'An attachment listed in a comma-separated meta value must survive.' );
	}

	/**
	 * Listings created before 1.24.0 store `_company_logo` on the post as a URL rather
	 * than an attachment ID, and still render from it. A URL never equals an ID, so
	 * the sweep has to recognise the file it points at.
	 */
	public function test_attachment_referenced_by_url_in_legacy_company_logo_meta_is_never_deleted() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$logo   = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$legacy = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		$this->create_listing_with_logo( $author, $logo );

		$listing = $this->factory->post->create( [ 'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING ] );
		update_post_meta( $listing, '_company_logo', wp_get_attachment_url( $legacy ) );

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertInstanceOf( WP_Post::class, get_post( $legacy ), 'A legacy listing renders its logo from a URL; that attachment must survive.' );
	}

	/**
	 * Core's Site Identity logo lives in `theme_mods_<theme>` as a serialized array,
	 * not in an option named after it. An admin who is also an employer can easily own
	 * both the site logo and a content-identical company logo.
	 */
	public function test_attachment_used_as_the_customizer_site_logo_is_never_deleted() {
		$author = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$bytes  = $this->png_bytes();

		$logo      = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$site_logo = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		$this->create_listing_with_logo( $author, $logo );

		set_theme_mod( 'custom_logo', $site_logo );

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		remove_theme_mod( 'custom_logo' );

		$this->assertInstanceOf( WP_Post::class, get_post( $site_logo ), "The Customizer's site logo must survive." );
	}

	/**
	 * Content that prints a bare `<img>` from a custom field carries no `wp-image-N`
	 * class and no block `{"id":N}` — only a URL, and often a resized one. Matching
	 * the original filename alone misses it, and deleting the attachment takes the
	 * resized file with it.
	 */
	public function test_attachment_referenced_by_a_resized_filename_in_content_is_never_deleted() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$logo   = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$inline = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		$this->create_listing_with_logo( $author, $logo );

		$thumbnail_url = str_replace( 'logo-1.png', 'logo-1-150x150.png', wp_get_attachment_url( $inline ) );
		$this->factory->post->create(
			[
				'post_type'    => 'post',
				'post_content' => '<img src="' . $thumbnail_url . '" alt="" />',
			]
		);

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertInstanceOf( WP_Post::class, get_post( $inline ), 'An attachment referenced by a resized filename must survive.' );
	}

	/**
	 * Block attributes nested inside another block are stored JSON-escaped, so the
	 * `"id":N` marker appears as `\"id\":N`.
	 */
	public function test_attachment_referenced_by_an_escaped_block_id_is_never_deleted() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$logo   = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$inline = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		$this->create_listing_with_logo( $author, $logo );

		// Doubled: wp_insert_post() unslashes, so a single backslash would not survive
		// into the stored content the way the block editor's own escaping does.
		$this->factory->post->create(
			[
				'post_type'    => 'post',
				'post_content' => '<!-- wp:group {"inner":"{\\\\"id\\\\":' . $inline . '}"} --><!-- /wp:group -->',
			]
		);

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertInstanceOf( WP_Post::class, get_post( $inline ), 'An attachment referenced by an escaped block id must survive.' );
	}

	/**
	 * Moving references before deleting only protects the site if the move actually
	 * landed. `update_post_meta()` returns false on a failed write, and core's
	 * `wp_delete_attachment()` then deletes every `_thumbnail_id` row pointing at the
	 * attachment — so an unchecked write turns a failed re-point into a listing that
	 * silently loses its logo, reported as a success.
	 */
	public function test_a_duplicate_is_not_deleted_when_its_reference_could_not_be_repointed() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$canonical = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$duplicate = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );

		$this->create_listing_with_logo( $author, $canonical );
		$job = $this->create_listing_with_logo( $author, $duplicate );

		// A write that reports failure without touching the row, as a DB error would.
		$block = static function ( $check, $object_id, $meta_key ) {
			return '_thumbnail_id' === $meta_key ? false : $check;
		};
		add_filter( 'update_post_metadata', $block, 10, 3 );
		$report = ( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );
		remove_filter( 'update_post_metadata', $block, 10 );

		$this->assertInstanceOf( WP_Post::class, get_post( $duplicate ), 'A duplicate whose reference could not be moved must not be deleted.' );
		$this->assertEquals( $duplicate, get_post_thumbnail_id( $job ), 'The listing must still point at an attachment that exists.' );
		$this->assertSame( 0, $report['attachments_deleted'] );
	}

	/**
	 * The runtime dedup hashes the file as uploaded, before core replaces it with a
	 * `-scaled` copy for images over the big-image threshold. Backfilling the hash of
	 * whatever `get_attached_file()` returns therefore stores a value the runtime
	 * lookup can never match — permanently, since the backfill skips attachments that
	 * already have a hash.
	 */
	public function test_backfilled_hash_is_of_the_original_upload_not_the_scaled_copy() {
		$author   = $this->factory->user->create( [ 'role' => 'employer' ] );
		$original = $this->png_bytes();
		$scaled   = $this->png_bytes() . "\0scaled";

		$canonical = $this->create_logo_attachment( $author, $scaled, 'big-scaled.png' );
		$this->create_logo_attachment( $author, $scaled, 'big-scaled-1.png' );
		$this->create_listing_with_logo( $author, $canonical );

		// The pre-scale upload core keeps alongside the scaled file it now serves.
		$upload_dir    = wp_upload_dir();
		$original_path = trailingslashit( $upload_dir['path'] ) . 'big.png';
		file_put_contents( $original_path, $original );
		$this->created_files[] = $original_path;
		wp_update_attachment_metadata( $canonical, [ 'original_image' => 'big.png' ] );

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertSame( md5( $original ), get_post_meta( $canonical, Attachment_Deduplicator::HASH_META_KEY, true ), 'The backfilled hash must match what the runtime dedup computes for the original upload.' );
	}

	/**
	 * One candidate's meta referencing another candidate is an unrecognised reference
	 * like any other. Suppressing it by excluding the current batch makes the outcome
	 * depend on which batch each ID landed in.
	 */
	public function test_a_candidate_referenced_from_another_candidates_meta_is_never_deleted() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$logo       = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$referrer   = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		$referenced = $this->create_logo_attachment( $author, $bytes, 'logo-2.png' );
		$this->create_listing_with_logo( $author, $logo );

		update_post_meta( $referrer, '_linked_image', $referenced );

		// A batch size that puts both candidates in the same batch; the outcome must
		// not differ from one that separates them.
		( new Attachment_Deduplicator( 50 ) )->run( [ 'dry_run' => false ] );

		$this->assertInstanceOf( WP_Post::class, get_post( $referenced ), "An attachment referenced from another attachment's meta must survive regardless of batching." );
	}

	/**
	 * A media-ish meta key on a post this class does not re-point is the mechanism the
	 * whole veto rests on, and the case add-ons actually produce (`_candidate_photo`).
	 */
	public function test_attachment_referenced_by_a_media_shaped_meta_key_is_never_deleted() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$logo  = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$photo = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		$this->create_listing_with_logo( $author, $logo );

		$resume = $this->factory->post->create( [ 'post_type' => 'post' ] );
		update_post_meta( $resume, '_candidate_photo', $photo );

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertInstanceOf( WP_Post::class, get_post( $photo ), 'An attachment referenced through a media-shaped meta key must survive.' );
	}

	/**
	 * Term meta is one of the surfaces the sweep claims to cover.
	 */
	public function test_attachment_referenced_from_term_meta_is_never_deleted() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$logo  = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$badge = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		$this->create_listing_with_logo( $author, $logo );

		$term = $this->factory->term->create( [ 'taxonomy' => 'category' ] );
		update_term_meta( $term, 'category_image', $badge );

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertInstanceOf( WP_Post::class, get_post( $badge ), 'An attachment referenced from term meta must survive.' );
	}

	/**
	 * `site_logo` holds a bare attachment ID and nothing re-points it.
	 */
	public function test_attachment_used_as_the_site_logo_is_never_deleted() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$logo      = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$site_logo = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		$this->create_listing_with_logo( $author, $logo );

		update_option( 'site_logo', $site_logo );

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		delete_option( 'site_logo' );

		$this->assertInstanceOf( WP_Post::class, get_post( $site_logo ), 'The site logo must survive.' );
	}

	/**
	 * The dry run's plan is the only record of what --live will collapse into what, so
	 * it has to be the actual mapping rather than a count.
	 */
	public function test_dry_run_reports_the_canonical_to_duplicates_mapping() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$canonical = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$first     = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		$second    = $this->create_logo_attachment( $author, $bytes, 'logo-2.png' );
		$this->create_listing_with_logo( $author, $canonical );

		$report = ( new Attachment_Deduplicator() )->run();

		$this->assertSame(
			[
				[
					'canonical'  => $canonical,
					'duplicates' => [ $first, $second ],
				],
			],
			$report['plan'],
			'The dry run must report which attachments collapse into which canonical.'
		);
	}

	/**
	 * A caption and description an editor wrote on a later copy are lost with it
	 * unless they are carried over, exactly like alt text.
	 */
	public function test_curated_caption_and_description_are_carried_over_to_the_canonical() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$canonical = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$curated   = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );
		wp_update_post(
			[
				'ID'           => $curated,
				'post_excerpt' => 'Acme Corporation, est. 1984',
				'post_content' => 'The full-colour logo used on job listings.',
			]
		);

		$this->create_listing_with_logo( $author, $canonical );
		$this->create_listing_with_logo( $author, $curated );

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$survivor = get_post( $canonical );
		$this->assertSame( 'Acme Corporation, est. 1984', $survivor->post_excerpt, 'A curated caption must be carried onto the canonical.' );
		$this->assertSame( 'The full-colour logo used on job listings.', $survivor->post_content, 'A curated description must be carried onto the canonical.' );
	}

	/**
	 * The `_company_logo` user default is a logo reference in its own right and must
	 * be re-pointed before its attachment is deleted.
	 */
	public function test_company_logo_user_meta_is_repointed() {
		$author = $this->factory->user->create( [ 'role' => 'employer' ] );
		$bytes  = $this->png_bytes();

		$canonical = $this->create_logo_attachment( $author, $bytes, 'logo.png' );
		$duplicate = $this->create_logo_attachment( $author, $bytes, 'logo-1.png' );

		$this->create_listing_with_logo( $author, $canonical );
		update_user_meta( $author, '_company_logo', $duplicate );

		( new Attachment_Deduplicator() )->run( [ 'dry_run' => false ] );

		$this->assertEquals( $canonical, (int) get_user_meta( $author, '_company_logo', true ), 'The user default must be re-pointed onto the canonical.' );
		$this->assertNull( get_post( $duplicate ), 'The duplicate is deleted once its reference has moved.' );
	}
}
