<?php
/**
 * Retroactive de-duplication of company-logo attachments.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collapses identical company-logo attachments a single owner accumulated (for
 * example by re-uploading the same logo on every submission) down to one,
 * re-pointing every reference before deleting the redundant copies.
 *
 * Two attachments are candidates for merging when they have identical file
 * contents and the same owner. Identical content is *not* on its own proof that
 * an attachment is a logo, though: an owner may have uploaded the same image
 * again for something this class knows nothing about. So an attachment is not
 * deleted while anything outside the two reference types handled here (listing
 * featured images and the `_company_logo` user default) still points at it —
 * see `find_protected_ids()`. Unrecognised reference means "keep", so the
 * failure mode is a duplicate left behind rather than a broken image.
 *
 * What that sweep can see has limits worth knowing before running it live:
 *
 * - It reads meta rows whose *key* looks like it could hold an attachment
 *   reference, so a reference stored under a name with no media-ish word in it
 *   is missed. `job_manager_dedupe_reference_meta_key_patterns` and
 *   `job_manager_dedupe_protected_attachment_ids` exist for that.
 * - It runs once, before any deletion. A reference created while the run is in
 *   progress is not protected by it, other than the two handled keys, which are
 *   re-checked immediately before deleting. Run `--live` in maintenance mode.
 * - Nothing here is multisite-aware; run it per site with `wp --url=`.
 *
 * Attachments belonging to different users are never merged, mirroring the
 * runtime dedup boundary. Guest uploads (`post_author` 0) are skipped outright:
 * 0 is not an owner, so unrelated submitters would otherwise collapse together.
 *
 * @since $$next-version$$
 *
 * @internal
 */
class Attachment_Deduplicator {

	/**
	 * Meta key storing an attachment's content hash, shared with the runtime
	 * dedup so canonical attachments keep de-duplicating future uploads.
	 */
	const HASH_META_KEY = '_wpjm_attachment_hash';

	/**
	 * Meta keys this class knows how to re-point. A reference through any other
	 * key blocks deletion.
	 */
	const HANDLED_POST_META_KEY = '_thumbnail_id';
	const HANDLED_USER_META_KEY = '_company_logo';

	/**
	 * Meta-key patterns treated as possibly holding an attachment reference.
	 *
	 * The veto cannot simply match any meta row whose value equals the attachment
	 * ID: plenty of unrelated meta stores small integers (`wp_user_level` is 10 for
	 * an administrator), so every attachment whose ID collided with one would be
	 * protected forever and the command would quietly stop de-duplicating. Matching
	 * on key shape keeps the check meaningful. Add-ons follow these conventions too
	 * (`_candidate_photo`, `_company_logo`).
	 *
	 * Filterable, because a site storing references under a key with no media-ish
	 * word in its name (`company_brand`) would otherwise have to patch the plugin to
	 * protect itself.
	 */
	const REFERENCE_KEY_PATTERNS = [
		'%thumb%',
		'%logo%',
		'%image%',
		'%photo%',
		'%picture%',
		'%avatar%',
		'%attach%',
		'%media%',
		'%gallery%',
		'%icon%',
		'%banner%',
		'%cover%',
		'%file%',
		'%img%',
		'%pic%',
		'%upload%',
		'%featured%',
	];

	/**
	 * Core's own attachment plumbing, excluded from the reference sweep.
	 *
	 * These match the key patterns above but describe the attachment itself rather
	 * than a reference to it — and their values are file paths, so leaving them in
	 * would make every attachment sharing a filename stem protect its own siblings.
	 */
	const SELF_DESCRIBING_META_KEYS = [
		'_wp_attached_file',
		'_wp_attachment_metadata',
		'_wp_attachment_backup_sizes',
		'_wp_attachment_image_alt',
		'_wp_attachment_is_custom_background',
		'_wp_attachment_is_custom_header',
		'_wp_attachment_context',
	];

	/**
	 * How deep to walk a serialized value looking for attachment references.
	 */
	const MAX_VALUE_DEPTH = 8;

	/**
	 * How many plan lines to print before deferring to `--report`. Twenty thousand
	 * lines of scrollback is not a record of anything.
	 */
	const MAX_PLAN_LINES = 50;

	/**
	 * Rows handled per batch: how many attachments are hashed, looked up, or
	 * deleted — and how many posts are scanned for inline references — before the
	 * object cache is released.
	 *
	 * This command exists for libraries with tens of thousands of attachments, so
	 * nothing may hold all of them in memory at once — priming 20,000 posts and
	 * their meta alone runs to hundreds of megabytes. It also bounds the size of
	 * the `IN ( ... )` lists sent to MySQL.
	 */
	const DEFAULT_CHUNK_SIZE = 500;

	/**
	 * Attachments handled per batch.
	 *
	 * @var int
	 */
	private $chunk_size;

	/**
	 * Memoised content signatures, keyed by attachment ID. Hashing is the
	 * expensive part of a run and the same attachment is examined more than once.
	 *
	 * @var array<int, string>
	 */
	private $signature_cache = [];

	/**
	 * Constructor.
	 *
	 * @since $$next-version$$
	 *
	 * @param int $chunk_size Attachments handled per batch. Lower values trade
	 *                        queries for peak memory; mainly a test seam.
	 */
	public function __construct( $chunk_size = self::DEFAULT_CHUNK_SIZE ) {
		$this->chunk_size = max( 1, (int) $chunk_size );
	}

	/**
	 * Releases the object cache built up while processing a batch.
	 *
	 * Without this the cache grows monotonically for the whole run, which is the
	 * usual way a long WP-CLI job runs out of memory.
	 */
	private function free_memory() {
		global $wpdb, $wp_object_cache;

		$wpdb->queries = [];

		if ( is_object( $wp_object_cache ) ) {
			if ( property_exists( $wp_object_cache, 'cache' ) ) {
				$wp_object_cache->cache = [];
			}
			if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
				$wp_object_cache->__remoteset();
			}
		}
	}

	/**
	 * Registers the WP-CLI command.
	 *
	 * @since $$next-version$$
	 */
	public static function init() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'jm dedupe-logos', [ self::class, 'cli' ] );
		}
	}

	/**
	 * De-duplicates company-logo attachments a single owner uploaded repeatedly.
	 *
	 * Collapses identical logo attachments (listing featured images and the
	 * `_company_logo` user default), including orphaned copies, down to the oldest
	 * one, re-pointing every reference before deleting the redundant copies.
	 * Dry-run by default; pass --live to apply.
	 *
	 * ## RUN THIS WITH THE SITE IN MAINTENANCE MODE
	 *
	 * Which attachments are safe to delete is worked out once, up front, and on the
	 * libraries this exists for that scan takes a while — every image the logo owners
	 * hold is hashed and every post's content is read. A reference created after that
	 * scan and before the matching delete is not protected by it.
	 *
	 * Re-points are verified immediately before deleting, so a listing or user default
	 * that changed in the meantime is left alone. Anything else — a new gallery entry,
	 * a page edited to embed the image, a logo set in the Customizer — is not covered,
	 * and deletion is not reversible unless the site defines MEDIA_TRASH.
	 *
	 * So: take a backup, put the site in maintenance mode, run it. On a live site the
	 * dry run is safe at any time; --live is not.
	 *
	 * ## OPTIONS
	 *
	 * [--live]
	 * : Apply the changes. Without this flag the command only reports (dry run).
	 *
	 * [--yes]
	 * : Skip the confirmation prompt for --live.
	 *
	 * [--owner=<id>]
	 * : Limit de-duplication to the attachments owned by a single user ID.
	 *   Named --owner rather than --user because --user is a reserved WP-CLI
	 *   global parameter: it sets the acting user and never reaches this command.
	 *
	 * [--report=<path>]
	 * : Write the canonical <- duplicates mapping to a CSV file before anything is
	 *   deleted. On a library with tens of thousands of attachments the terminal is
	 *   not a usable record of an irreversible operation.
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview what would be de-duplicated across the whole site.
	 *     wp jm dedupe-logos
	 *
	 *     # Preview it, keeping the plan for review.
	 *     wp jm dedupe-logos --report=dedupe-plan.csv
	 *
	 *     # Apply it.
	 *     wp jm dedupe-logos --live
	 *
	 *     # Just one owner.
	 *     wp jm dedupe-logos --owner=42 --live
	 *
	 * @when after_wp_load
	 *
	 * @since $$next-version$$
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 */
	public static function cli( $args, $assoc_args ) {
		$dry_run = ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'live', false );
		$user_id = 0;

		if ( isset( $assoc_args['owner'] ) ) {
			// A bare --owner arrives as true, and absint( true ) is 1: a destructive
			// command must not quietly pick a user out of a malformed flag.
			if ( ! is_numeric( $assoc_args['owner'] ) ) {
				\WP_CLI::error( 'The --owner flag needs a user ID, e.g. --owner=42.' );
			}
			$user_id = absint( $assoc_args['owner'] );
			// Without this, a typo silently becomes 0 — i.e. the whole site.
			if ( ! $user_id || ! get_userdata( $user_id ) ) {
				\WP_CLI::error( sprintf( 'No such user: %s', $assoc_args['owner'] ) );
			}
		}

		$report_path = \WP_CLI\Utils\get_flag_value( $assoc_args, 'report', '' );

		if ( ! $dry_run ) {
			// Deletion is not reversible on sites without MEDIA_TRASH, and references
			// created while the run is in progress are not protected by its scan.
			\WP_CLI::confirm( 'This will delete redundant logo attachments, and is not reversible unless MEDIA_TRASH is on. Run without --live first to preview, and run this one with the site in maintenance mode. Continue?', $assoc_args );
		}

		// Discovery hashes every image the logo owners hold and scans post content, so
		// on the libraries this exists for it is minutes of silence otherwise.
		\WP_CLI::log( 'Scanning for duplicate logos…' );

		$progress = null;
		$report   = ( new self() )->run(
			[
				'dry_run'  => $dry_run,
				'user_id'  => $user_id,
				'on_plan'  => function ( $total ) use ( &$progress ) {
					if ( $total ) {
						$progress = \WP_CLI\Utils\make_progress_bar( 'Merging duplicates', $total );
					}
				},
				'on_tick'  => function () use ( &$progress ) {
					if ( $progress ) {
						$progress->tick();
					}
				},
				'on_ready' => function ( $plan ) use ( $report_path ) {
					if ( $report_path ) {
						self::write_plan_csv( $report_path, $plan );
						\WP_CLI::log( sprintf( 'Wrote plan for %d group(s) to %s', count( $plan ), $report_path ) );
					}
				},
			]
		);

		if ( $progress ) {
			$progress->finish();
		}

		\WP_CLI::log( $dry_run ? 'DRY RUN — no changes made.' : 'LIVE' );
		\WP_CLI::log( sprintf( 'Duplicate groups found:  %d', $report['groups'] ) );
		\WP_CLI::log( sprintf( 'Redundant attachments:   %d', $report['duplicates'] ) );

		if ( $report['skipped_referenced'] ) {
			\WP_CLI::log( sprintf( 'Kept (referenced elsewhere): %d', $report['skipped_referenced'] ) );
		}

		// Print the mapping so a dry run can be audited, and a live run leaves a
		// record of what was collapsed into what. Past a few screenfuls the CSV is
		// the usable record, so don't bury the summary under thousands of lines.
		if ( count( $report['plan'] ) <= self::MAX_PLAN_LINES ) {
			foreach ( $report['plan'] as $group ) {
				\WP_CLI::log( sprintf( '  %d <- %s', $group['canonical'], implode( ', ', $group['duplicates'] ) ) );
			}
		} elseif ( ! $report_path ) {
			\WP_CLI::log( sprintf( '  (%d groups — re-run with --report=<path> for the full mapping)', count( $report['plan'] ) ) );
		}

		if ( $dry_run ) {
			\WP_CLI::success( sprintf( 'Would re-point references and delete %d attachment(s). Re-run with --live to apply.', $report['duplicates'] ) );
			return;
		}

		if ( $report['repoint_failures'] ) {
			\WP_CLI::warning(
				sprintf(
					'%d attachment(s) were kept because their references could not be moved: %s. Nothing was deleted for those — re-run to retry.',
					count( $report['repoint_failures'] ),
					implode( ', ', $report['repoint_failures'] )
				)
			);
		}

		if ( $report['delete_failures'] ) {
			\WP_CLI::error(
				sprintf(
					'Re-pointed %d reference(s); deleted %d attachment(s), but %d deletion(s) failed: %s. References were moved first, so nothing is left pointing at a deleted attachment — re-run to retry.',
					$report['references_repointed'],
					$report['attachments_deleted'],
					count( $report['delete_failures'] ),
					implode( ', ', $report['delete_failures'] )
				)
			);
		}

		\WP_CLI::success( sprintf( 'Re-pointed %d reference(s); deleted %d attachment(s).', $report['references_repointed'], $report['attachments_deleted'] ) );
	}

	/**
	 * Writes the plan to a CSV file, so an irreversible run leaves a record that
	 * outlives the terminal buffer.
	 *
	 * @param string  $path Destination path.
	 * @param array[] $plan Plan entries.
	 */
	private static function write_plan_csv( $path, array $plan ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen -- Writing an operator-specified CLI report file; WP_Filesystem is not loaded under WP-CLI.
		$handle = fopen( $path, 'w' );
		if ( ! $handle ) {
			\WP_CLI::error( sprintf( 'Could not write the report to %s', $path ) );
		}

		fputcsv( $handle, [ 'canonical', 'duplicate' ] );
		foreach ( $plan as $group ) {
			foreach ( $group['duplicates'] as $duplicate ) {
				fputcsv( $handle, [ $group['canonical'], $duplicate ] );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose -- Matches the fopen above.
		fclose( $handle );
	}

	/**
	 * Finds groups of identical logo attachments and, unless this is a dry run,
	 * merges each group into its oldest attachment.
	 *
	 * @since $$next-version$$
	 *
	 * @param array $args Optional arguments:
	 *                    'dry_run' (bool, default true) only reports what would change;
	 *                    'user_id' (int, default 0) limits to one attachment owner;
	 *                    'on_ready' (callable|null) called once with the finished plan,
	 *                    before anything is mutated;
	 *                    'on_plan' (callable|null) called once with the number of
	 *                    attachments about to be merged, before any mutation;
	 *                    'on_tick' (callable|null) called after each attachment.
	 * @return array Report: 'groups' and 'duplicates' (counts of what will be or was
	 *               merged), 'skipped_referenced' (candidates kept because something
	 *               unrecognised still references them), 'plan' (the canonical =>
	 *               duplicates mapping), and, for a live run, 'references_repointed',
	 *               'attachments_deleted', 'repoint_failures' (IDs kept because their
	 *               references could not be moved) and 'delete_failures' (IDs that
	 *               could not be deleted).
	 */
	public function run( array $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'dry_run'  => true,
				'user_id'  => 0,
				'on_ready' => null,
				'on_plan'  => null,
				'on_tick'  => null,
			]
		);

		$report = [
			'groups'               => 0,
			'duplicates'           => 0,
			'references_repointed' => 0,
			'attachments_deleted'  => 0,
			'skipped_referenced'   => 0,
			'delete_failures'      => [],
			'repoint_failures'     => [],
			'plan'                 => [],
		];

		$groups = $this->find_duplicate_logo_groups( (int) $args['user_id'] );

		// Resolve which candidates are off-limits in one batched pass over the whole
		// run, rather than re-querying the same tables for every duplicate.
		$candidates = [];
		foreach ( $groups as $group ) {
			foreach ( $group['duplicates'] as $duplicate ) {
				$candidates[] = $duplicate;
			}
		}
		$protected = $this->find_protected_ids( $candidates );

		$repoint_map = [];

		foreach ( $groups as $group ) {
			$canonical = $group['canonical'];

			// Attachments referenced through anything this class cannot re-point are
			// dropped from the group before it is reported or applied, so a dry run
			// describes exactly what --live would do.
			$deletable = [];
			foreach ( $group['duplicates'] as $duplicate ) {
				if ( isset( $protected[ $duplicate ] ) ) {
					++$report['skipped_referenced'];
					continue;
				}
				$deletable[] = $duplicate;
			}

			if ( ! $deletable ) {
				continue;
			}

			++$report['groups'];
			$report['duplicates'] += count( $deletable );
			$report['plan'][]      = [
				'canonical'  => $canonical,
				'duplicates' => $deletable,
			];

			foreach ( $deletable as $duplicate ) {
				$repoint_map[ $duplicate ] = $canonical;
			}
		}

		// The plan is complete and nothing has been touched yet, which is the only
		// point at which it can be recorded as what the run is about to do.
		if ( is_callable( $args['on_ready'] ) ) {
			call_user_func( $args['on_ready'], $report['plan'] );
		}

		if ( $args['dry_run'] || ! $repoint_map ) {
			return $report;
		}

		if ( is_callable( $args['on_plan'] ) ) {
			call_user_func( $args['on_plan'], count( $repoint_map ) );
		}

		foreach ( $report['plan'] as $group ) {
			$this->backfill_hash( $group['canonical'] );
		}

		// Move every reference first, then delete: a run that dies partway leaves an
		// un-deleted duplicate rather than a listing pointing at a deleted post.
		$report['references_repointed'] = $this->repoint_all( $repoint_map );

		// Then prove it landed. `update_post_meta()` reports failure by return value,
		// and core's wp_delete_attachment() deletes every `_thumbnail_id` row pointing
		// at the attachment — so a re-point that silently failed would turn into a
		// listing that lost its logo, counted as a success. Anything still referenced
		// is dropped from this run rather than deleted; re-running retries it.
		foreach ( $this->surviving_reference_ids( array_keys( $repoint_map ) ) as $unmoved ) {
			unset( $repoint_map[ $unmoved ] );
			$report['repoint_failures'][] = $unmoved;
		}

		$processed = 0;
		foreach ( $repoint_map as $duplicate => $canonical ) {
			$this->carry_over_metadata( $duplicate, $canonical );

			if ( $this->delete_attachment( $duplicate ) ) {
				++$report['attachments_deleted'];
			} else {
				// Its references already point at the canonical, so the site is intact
				// — this is a duplicate left behind, not a broken reference.
				$report['delete_failures'][] = $duplicate;
			}

			if ( is_callable( $args['on_tick'] ) ) {
				call_user_func( $args['on_tick'] );
			}

			// wp_delete_attachment() populates the cache for every post it touches.
			++$processed;
			if ( 0 === $processed % $this->chunk_size ) {
				$this->free_memory();
			}
		}

		return $report;
	}

	/**
	 * Groups logo attachments by owner and content, returning one entry per set
	 * of duplicates with the oldest (lowest ID) chosen as the canonical.
	 *
	 * A "logo" content signature is derived from attachments currently referenced
	 * as a logo; every image attachment owned by those owners that shares a
	 * signature is folded into the group, so orphaned duplicate copies (which no
	 * longer reference anything) are collapsed alongside the in-use ones.
	 *
	 * @param int $user_id Limit to one owner (0 = all).
	 * @return array[] Each: [ 'canonical' => int, 'duplicates' => int[] ].
	 */
	private function find_duplicate_logo_groups( $user_id = 0 ) {
		// Content signatures ("author:hash") of currently-referenced logos.
		$logo_signatures = [];
		$owner_ids       = [];

		// Both passes stream a page at a time. Materialising every attachment ID first
		// is what puts a large library over the memory limit, so the full set is never
		// held: each page is primed, reduced to signatures, then released.
		$this->each_referenced_logo_page(
			function ( array $page ) use ( &$logo_signatures, &$owner_ids, $user_id ) {
				foreach ( $page as $attachment_id ) {
					$signature = $this->attachment_signature( $attachment_id, $user_id );
					if ( $signature ) {
						$logo_signatures[ $signature ]                                      = true;
						$owner_ids[ (int) get_post_field( 'post_author', $attachment_id ) ] = true;
					}
				}
			}
		);

		if ( ! $logo_signatures ) {
			return [];
		}

		// Group every image attachment those owners hold by signature, keeping only
		// signatures that match a referenced logo (folds in orphaned copies). Only
		// matching IDs are retained, so this holds the duplicates rather than the
		// whole media library.
		$by_signature = [];
		$this->each_owner_image_page(
			array_keys( $owner_ids ),
			function ( array $page ) use ( &$by_signature, $logo_signatures, $user_id ) {
				foreach ( $page as $attachment_id ) {
					$signature = $this->attachment_signature( $attachment_id, $user_id );
					if ( $signature && isset( $logo_signatures[ $signature ] ) ) {
						$by_signature[ $signature ][] = (int) $attachment_id;
					}
				}
			}
		);

		$groups = [];
		foreach ( $by_signature as $ids ) {
			$ids = array_values( array_unique( $ids ) );
			if ( count( $ids ) < 2 ) {
				continue;
			}
			sort( $ids );
			$groups[] = [
				'canonical'  => $ids[0],
				'duplicates' => array_slice( $ids, 1 ),
			];
		}

		return $groups;
	}

	/**
	 * Streams attachment IDs currently used as a logo, a page at a time: listing
	 * featured images and the `_company_logo` user meta default.
	 *
	 * Each page is primed and then released, so the caller never holds more than
	 * one page of posts in the object cache.
	 *
	 * @param callable $handle Receives each page as an array of attachment IDs.
	 */
	private function each_referenced_logo_page( callable $handle ) {
		global $wpdb;

		$sources = [
			// Deliberately unfiltered by author: a listing authored by one user can
			// carry a logo owned by another, so ownership is applied to the attachment
			// in attachment_signature() rather than to whatever references it.
			$wpdb->prepare(
				"SELECT pm.meta_id AS cursor_id, pm.meta_value AS value
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s AND p.post_type = %s AND pm.meta_id > ",
				self::HANDLED_POST_META_KEY,
				\WP_Job_Manager_Post_Types::PT_LISTING
			),
			$wpdb->prepare(
				"SELECT umeta_id AS cursor_id, meta_value AS value FROM {$wpdb->usermeta} WHERE meta_key = %s AND umeta_id > ",
				self::HANDLED_USER_META_KEY
			),
		];

		foreach ( $sources as $sql ) {
			$last = 0;

			do {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Paged bulk read; no API equivalent that avoids loading everything.
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot CLI migration.
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $sql is already prepared; the cursor and limit are bound here.
				// Keyset rather than OFFSET, which re-reads every earlier row per page.
				$rows = $wpdb->get_results(
					$wpdb->prepare( $sql . '%d ORDER BY cursor_id LIMIT %d', $last, $this->chunk_size )
				);
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

				$page = [];
				foreach ( $rows as $row ) {
					$last = (int) $row->cursor_id;
					if ( (int) $row->value ) {
						$page[] = (int) $row->value;
					}
				}

				if ( $page ) {
					_prime_post_caches( $page, false, true );
					$handle( $page );
					$this->free_memory();
				}

				$fetched = count( $rows );
			} while ( $this->chunk_size === $fetched );
		}
	}

	/**
	 * Streams every image attachment owned by the given users, a page at a time, so
	 * orphaned duplicate copies (referenced by nothing) are considered for merging.
	 *
	 * @param int[]    $owner_ids Owner user IDs.
	 * @param callable $handle    Receives each page as an array of attachment IDs.
	 */
	private function each_owner_image_page( array $owner_ids, callable $handle ) {
		global $wpdb;

		if ( ! $owner_ids ) {
			return;
		}

		// Chunked: this command exists for sites with thousands of employers, and one
		// owner per logo means the whole set would otherwise become a single
		// IN ( ... ) list re-sent on every page.
		foreach ( array_chunk( array_map( 'intval', $owner_ids ), $this->chunk_size ) as $owner_chunk ) {
			$author_list = implode( ',', $owner_chunk );
			$last        = 0;

			do {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Paged bulk read; get_posts() would load the whole library.
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot CLI migration.
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $author_list is ints.
				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts}
						 WHERE post_type = 'attachment'
						   AND post_status = 'inherit'
						   AND post_mime_type LIKE %s
						   AND post_author IN ( {$author_list} )
						   AND ID > %d
						 ORDER BY ID LIMIT %d",
						$wpdb->esc_like( 'image/' ) . '%',
						$last,
						$this->chunk_size
					)
				);
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

				$page = array_map( 'intval', $ids );

				if ( $page ) {
					$last = end( $page );
					_prime_post_caches( $page, false, true );
					$handle( $page );
					$this->free_memory();
				}

				$fetched = count( $ids );
			} while ( $this->chunk_size === $fetched );
		}
	}

	/**
	 * Returns an attachment's "author:content-hash" signature, or empty string
	 * when it is not a usable local attachment (or not owned by $user_id).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $user_id       When set, require this owner.
	 * @return string Signature, or '' to skip.
	 */
	private function attachment_signature( $attachment_id, $user_id = 0 ) {
		$attachment_id = (int) $attachment_id;

		if ( ! isset( $this->signature_cache[ $attachment_id ] ) ) {
			$this->signature_cache[ $attachment_id ] = $this->compute_signature( $attachment_id );
		}

		$signature = $this->signature_cache[ $attachment_id ];

		// Scoping is by the owner of the attachment, not of whatever references it:
		// since #3060 a listing can carry a logo owned by a different user.
		if ( $signature && $user_id && (int) get_post_field( 'post_author', $attachment_id ) !== $user_id ) {
			return '';
		}

		return $signature;
	}

	/**
	 * Computes an attachment's "author:content-hash" signature.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Signature, or '' to skip.
	 */
	private function compute_signature( $attachment_id ) {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return '';
		}

		// Guest uploads all carry author 0, which is not an owner. Merging on it would
		// collapse unrelated anonymous submitters together, so guests are never merged
		// — the runtime dedup refuses them for the same reason.
		if ( 0 === (int) $attachment->post_author ) {
			return '';
		}

		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! is_file( $file ) ) {
			return '';
		}
		$hash = md5_file( $file );
		if ( ! $hash ) {
			return '';
		}

		return $attachment->post_author . ':' . $hash;
	}

	/**
	 * Re-points every known logo reference according to a duplicate => canonical map.
	 *
	 * Two reads for the whole run, then one write per reference actually affected.
	 * Reads go straight to the meta tables: a get_posts( fields => ids ) plus a
	 * get_post_meta() per row would cost a query for every affected listing, and
	 * reading the rows directly also sidesteps WP_Query's `post_status` shorthand,
	 * which hides statuses registered with exclude_from_search — WPJM's own
	 * `expired` and `preview` among them. An expired listing is a normal end state
	 * and still references its logo, so it must be re-pointed like any other.
	 *
	 * @param array<int, int> $map Duplicate attachment ID => canonical attachment ID.
	 * @return int Number of references updated.
	 */
	private function repoint_all( array $map ) {
		global $wpdb;

		if ( ! $map ) {
			return 0;
		}

		$count = 0;

		foreach ( array_chunk( array_map( 'intval', array_keys( $map ) ), $this->chunk_size ) as $chunk ) {
			$count += $this->repoint_chunk( $map, $chunk );
			$this->free_memory();
		}

		return $count;
	}

	/**
	 * Re-points one batch of duplicates.
	 *
	 * @param array<int, int> $map   Duplicate attachment ID => canonical attachment ID.
	 * @param int[]           $chunk Duplicate IDs in this batch.
	 * @return int Number of references updated.
	 */
	private function repoint_chunk( array $map, array $chunk ) {
		global $wpdb;

		$count   = 0;
		$id_list = implode( ',', $chunk );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Bulk read replacing one query per affected row.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot CLI migration.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $id_list is ints.
		$jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s AND p.post_type = %s AND pm.meta_value IN ( {$id_list} )",
				self::HANDLED_POST_META_KEY,
				\WP_Job_Manager_Post_Types::PT_LISTING
			)
		);

		$owners = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, meta_value FROM {$wpdb->usermeta}
				 WHERE meta_key = %s AND meta_value IN ( {$id_list} )",
				self::HANDLED_USER_META_KEY
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $jobs as $job ) {
			$current = (int) $job->meta_value;
			if ( isset( $map[ $current ] ) ) {
				update_post_meta( (int) $job->post_id, self::HANDLED_POST_META_KEY, $map[ $current ] );
				++$count;
			}
		}

		foreach ( $owners as $owner ) {
			$current = (int) $owner->meta_value;
			if ( isset( $map[ $current ] ) ) {
				update_user_meta( (int) $owner->user_id, self::HANDLED_USER_META_KEY, $map[ $current ] );
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Of the given attachments, those something still references through one of the
	 * two keys this class re-points.
	 *
	 * Run after re-pointing, as the precondition for deleting: an ID coming back here
	 * means its re-point did not land, and deleting it would strip the reference
	 * rather than move it. Two queries per batch, so the check does not scale with
	 * the number of duplicates.
	 *
	 * @param int[] $ids Attachment IDs about to be deleted.
	 * @return int[] IDs that are still referenced.
	 */
	private function surviving_reference_ids( array $ids ) {
		global $wpdb;

		$surviving = [];

		foreach ( array_chunk( array_map( 'intval', $ids ), $this->chunk_size ) as $chunk ) {
			$id_list = implode( ',', $chunk );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Bulk verification replacing one query per attachment.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot CLI migration; a cached answer would defeat the check.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $id_list is ints.
			$still_used = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT pm.meta_value
					 FROM {$wpdb->postmeta} pm
					 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					 WHERE pm.meta_key = %s AND p.post_type = %s AND pm.meta_value IN ( {$id_list} )",
					self::HANDLED_POST_META_KEY,
					\WP_Job_Manager_Post_Types::PT_LISTING
				)
			);

			$still_default = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT meta_value FROM {$wpdb->usermeta}
					 WHERE meta_key = %s AND meta_value IN ( {$id_list} )",
					self::HANDLED_USER_META_KEY
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

			foreach ( array_merge( $still_used, $still_default ) as $id ) {
				$surviving[ (int) $id ] = true;
			}
		}

		return array_keys( $surviving );
	}

	/**
	 * Returns the subset of the given attachments that something this class cannot
	 * re-point still references, as an [ id => true ] lookup.
	 *
	 * The candidate set is content-identical images, which is broader than "logos":
	 * the same file may also be a blog post's featured image, sit inline in someone's
	 * content, or be the site logo. Deleting those would strip an image with nothing
	 * left to repair it, so an unrecognised reference vetoes the deletion.
	 *
	 * Every check is batched across the whole candidate set — this command targets
	 * sites with tens of thousands of attachments, where a per-attachment sweep of
	 * these tables would not finish.
	 *
	 * @param int[] $candidate_ids Attachment IDs being considered for deletion.
	 * @return array<int, true> Protected IDs.
	 */
	private function find_protected_ids( array $candidate_ids ) {
		$candidate_ids = array_values( array_unique( array_map( 'intval', $candidate_ids ) ) );
		if ( ! $candidate_ids ) {
			return [];
		}

		$by_id = array_fill_keys( $candidate_ids, true );

		// Filenames are how a reference that stores a URL rather than an ID — legacy
		// `_company_logo` post meta, a hand-written <img>, a page builder printing a
		// custom field — is recognised at all.
		$by_stem = [];
		foreach ( $this->attachment_stems( $candidate_ids ) as $attachment_id => $stem ) {
			$by_stem[ $stem ][] = $attachment_id;
		}

		$protected = $this->find_protected_ids_in_meta( $by_id, $by_stem )
			+ $this->find_protected_ids_in_options( $by_id, $by_stem )
			+ $this->find_inline_content_references( $by_id, $by_stem );

		/**
		 * Filters the attachments the logo de-duplicator refuses to delete.
		 *
		 * The sweep recognises the storage shapes core and the common plugins use;
		 * a site storing references some other way can add its own here. Adding an
		 * ID keeps that attachment, it never causes a deletion.
		 *
		 * @since $$next-version$$
		 *
		 * @param array<int, true> $protected     Protected attachment IDs, as an [ id => true ] lookup.
		 * @param int[]            $candidate_ids Attachment IDs being considered for deletion.
		 */
		$protected = apply_filters( 'job_manager_dedupe_protected_attachment_ids', $protected, $candidate_ids );

		// A filter returning junk must not be able to widen the deletion set.
		$result = [];
		foreach ( array_keys( (array) $protected ) as $id ) {
			if ( isset( $by_id[ (int) $id ] ) ) {
				$result[ (int) $id ] = true;
			}
		}

		return $result;
	}

	/**
	 * The meta half of the reference sweep: one streamed pass over the meta rows whose
	 * key looks like it could hold an attachment reference, matched in PHP.
	 *
	 * Reading the values rather than comparing them to the candidate IDs in SQL is what
	 * makes serialized arrays (ACF galleries and repeaters), comma-separated lists
	 * (`_product_image_gallery`) and stored URLs (legacy `_company_logo`) visible at
	 * all: `meta_value IN ( 12,34 )` is an exact match against a LONGTEXT column, so
	 * MySQL coerces `a:1:{i:0;i:34;}` to 0 and `"12,34"` to 12 — matching nothing, or
	 * only the first ID, while looking like the key was covered.
	 *
	 * It is also one pass instead of one per batch of candidates. `meta_value` has no
	 * index and the key patterns have a leading wildcard, so each of these is a full
	 * table scan; doing it per batch is what stops the command finishing.
	 *
	 * @param array<int, true>     $by_id   Candidate IDs, as a lookup.
	 * @param array<string, int[]> $by_stem Candidate IDs keyed by file stem.
	 * @return array<int, true> Protected IDs.
	 */
	private function find_protected_ids_in_meta( array $by_id, array $by_stem ) {
		global $wpdb;

		$protected = [];

		$sources = [
			// `_thumbnail_id` on a listing is the one post-meta case repoint_all()
			// handles; the same key on any other post type is not. The post_id is
			// selected so an attachment's own meta cannot protect it.
			[
				'sql'      => "SELECT pm.meta_id AS cursor_id, pm.post_id AS owner_id, pm.meta_key, pm.meta_value, p.post_type
					 FROM {$wpdb->postmeta} pm
					 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id",
				'table'    => 'pm',
				'cursor'   => 'pm.meta_id',
				'key_col'  => 'pm.meta_key',
				'is_owner' => true,
			],
			[
				'sql'      => "SELECT umeta_id AS cursor_id, 0 AS owner_id, meta_key, meta_value, '' AS post_type FROM {$wpdb->usermeta}",
				'cursor'   => 'umeta_id',
				'key_col'  => 'meta_key',
				'is_owner' => false,
			],
			[
				'sql'      => "SELECT meta_id AS cursor_id, 0 AS owner_id, meta_key, meta_value, '' AS post_type FROM {$wpdb->termmeta}",
				'cursor'   => 'meta_id',
				'key_col'  => 'meta_key',
				'is_owner' => false,
			],
		];

		foreach ( $sources as $source ) {
			$last = 0;

			do {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reference sweep across core tables has no API equivalent.
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot CLI migration; a cached answer would be worse than none.
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Key clauses are built with prepare(); the cursor is bound below.
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $source is a literal from the array above; the key clauses are prepared.
				// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Raw SQL, not a WP_Query meta arg; the scan is the point of the command.
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						$source['sql']
						. ' WHERE ( ' . $this->reference_key_sql( $source['key_col'] ) . ' )'
						. ' AND ' . $this->excluded_key_sql( $source['key_col'] )
						// Keyset rather than OFFSET: paging an unindexed scan by offset
						// re-reads and discards every earlier row on every page.
						. ' AND ' . $source['cursor'] . ' > %d'
						. ' ORDER BY ' . $source['cursor'] . ' LIMIT %d',
						$last,
						$this->chunk_size
					)
				);
				// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

				foreach ( $rows as $row ) {
					$last = (int) $row->cursor_id;

					if ( $this->is_handled_reference( $row, $source['is_owner'] ) ) {
						continue;
					}

					foreach ( $this->referenced_ids_in_value( $row->meta_value, $by_id, $by_stem ) as $id ) {
						// An attachment's own meta describes it rather than referencing
						// it; another candidate's meta is a reference like any other.
						if ( $source['is_owner'] && (int) $row->owner_id === $id ) {
							continue;
						}
						$protected[ $id ] = true;
					}
				}

				$fetched = count( $rows );
			} while ( $this->chunk_size === $fetched );

			$this->free_memory();
		}

		return $protected;
	}

	/**
	 * Whether a meta row is one of the two reference types this class re-points, and
	 * so does not block deletion.
	 *
	 * @param object $row      Row with meta_key and post_type.
	 * @param bool   $is_owner Whether the row came from post meta.
	 * @return bool
	 */
	private function is_handled_reference( $row, $is_owner ) {
		if ( $is_owner ) {
			return self::HANDLED_POST_META_KEY === $row->meta_key
				&& \WP_Job_Manager_Post_Types::PT_LISTING === $row->post_type;
		}

		// User meta only: the same key on a *post* is the pre-1.24.0 listing logo,
		// which holds a URL and is re-pointed by nothing.
		return self::HANDLED_USER_META_KEY === $row->meta_key;
	}

	/**
	 * The options half of the sweep.
	 *
	 * Deliberately a short, named list rather than the meta key patterns: option names
	 * like `medium_size_w` would match `%media%`-ish patterns and hold a bare number
	 * (300, 150, 1024), so every attachment whose ID collided with an image dimension
	 * would be protected forever.
	 *
	 * @param array<int, true>     $by_id   Candidate IDs, as a lookup.
	 * @param array<string, int[]> $by_stem Candidate IDs keyed by file stem.
	 * @return array<int, true> Protected IDs.
	 */
	private function find_protected_ids_in_options( array $by_id, array $by_stem ) {
		global $wpdb;

		$protected = [];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- No API for scanning options by name pattern.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot CLI migration.
		$rows = $wpdb->get_results(
			"SELECT option_name, option_value FROM {$wpdb->options}
			 WHERE option_name IN ( 'site_logo', 'site_icon' )
			    OR option_name LIKE 'theme_mods\_%'
			    OR option_name LIKE 'widget\_%'"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $rows as $row ) {
			foreach ( $this->referenced_ids_in_value( $row->option_value, $by_id, $by_stem ) as $id ) {
				$protected[ $id ] = true;
			}
		}

		return $protected;
	}

	/**
	 * Extracts the candidate attachment IDs a stored value refers to.
	 *
	 * Handles the shapes references are actually stored in: a bare ID, a
	 * comma-separated list of them, an ID nested anywhere inside a serialized array
	 * (`theme_mods_*`'s `custom_logo`, an ACF gallery), and a URL or path, matched by
	 * filename. Anything else is ignored — pulling every integer out of arbitrary text
	 * would protect an attachment because a post happened to mention its ID.
	 *
	 * @param mixed                $value   Stored value.
	 * @param array<int, true>     $by_id   Candidate IDs, as a lookup.
	 * @param array<string, int[]> $by_stem Candidate IDs keyed by file stem.
	 * @return int[] Referenced candidate IDs.
	 */
	private function referenced_ids_in_value( $value, array $by_id, array $by_stem ) {
		$found = [];
		$this->collect_referenced_ids( maybe_unserialize( $value ), $by_id, $by_stem, $found, 0 );

		return array_keys( $found );
	}

	/**
	 * Walks one value (recursing into serialized structures) collecting candidate IDs.
	 *
	 * @param mixed                $value   Value to inspect.
	 * @param array<int, true>     $by_id   Candidate IDs, as a lookup.
	 * @param array<string, int[]> $by_stem Candidate IDs keyed by file stem.
	 * @param array<int, true>     $found   Accumulator, by reference.
	 * @param int                  $depth   Current recursion depth.
	 */
	private function collect_referenced_ids( $value, array $by_id, array $by_stem, array &$found, $depth ) {
		if ( $depth > self::MAX_VALUE_DEPTH ) {
			return;
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			foreach ( (array) $value as $item ) {
				$this->collect_referenced_ids( $item, $by_id, $by_stem, $found, $depth + 1 );
			}
			return;
		}

		if ( is_bool( $value ) || null === $value ) {
			return;
		}

		$string = trim( (string) $value );
		if ( '' === $string ) {
			return;
		}

		// A bare ID, or a comma-separated list of them.
		if ( preg_match( '/^\d+(\s*,\s*\d+)*$/', $string ) ) {
			foreach ( explode( ',', $string ) as $part ) {
				$id = (int) trim( $part );
				if ( isset( $by_id[ $id ] ) ) {
					$found[ $id ] = true;
				}
			}
			return;
		}

		if ( ! $by_stem ) {
			return;
		}

		foreach ( $this->file_stems_in( $string ) as $stem ) {
			if ( ! isset( $by_stem[ $stem ] ) ) {
				continue;
			}
			foreach ( $by_stem[ $stem ] as $id ) {
				$found[ $id ] = true;
			}
		}
	}

	/**
	 * Finds candidates referenced from inside post content: the block editor's
	 * {"id":N}, the classic editor's wp-image-N class, and plain URLs to the file.
	 *
	 * Content cannot be indexed for this, so it is one streamed pass over the posts
	 * that contain any image marker at all, matched in PHP — rather than one LIKE
	 * scan per candidate.
	 *
	 * @param array<int, true>     $by_id   Candidate IDs, as a lookup.
	 * @param array<string, int[]> $by_stem Candidate IDs keyed by file stem.
	 * @return array<int, true> Protected IDs.
	 */
	private function find_inline_content_references( array $by_id, array $by_stem ) {
		global $wpdb;

		$protected = [];
		$last      = 0;

		do {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Content scan has no API equivalent.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot CLI migration.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_content FROM {$wpdb->posts}
					 WHERE ( post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s )
					   AND ID > %d
					 ORDER BY ID LIMIT %d",
					'%' . $wpdb->esc_like( 'wp-image-' ) . '%',
					// Not just `"id":` — a block attribute nested inside another block
					// is stored JSON-escaped, and kses rewrites the escaped quotes to
					// ", so the same marker has three shapes on disk.
					'%' . $wpdb->esc_like( '"id' ) . '%',
					'%' . $wpdb->esc_like( 'u0022id' ) . '%',
					'%' . $wpdb->esc_like( '/uploads/' ) . '%',
					$last,
					$this->chunk_size
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

			foreach ( $rows as $row ) {
				$last    = (int) $row->ID;
				$content = (string) $row->post_content;

				// A block attribute nested inside another block is stored JSON-escaped
				// (\"id\"), and kses rewrites those quotes again ("id"), so
				// the same marker has to be recognised in all three shapes.
				$quote = '(?:"|\\\\"|\\\\u0022)';
				if ( preg_match_all( '/(?:wp-image-|' . $quote . 'id' . $quote . '\s*:\s*' . $quote . '?)(\d+)/', $content, $matches ) ) {
					foreach ( $matches[1] as $found ) {
						$found = (int) $found;
						if ( isset( $by_id[ $found ] ) && $last !== $found ) {
							$protected[ $found ] = true;
						}
					}
				}

				if ( ! $by_stem ) {
					continue;
				}

				foreach ( $this->file_stems_in( $content ) as $stem ) {
					if ( ! isset( $by_stem[ $stem ] ) ) {
						continue;
					}
					foreach ( $by_stem[ $stem ] as $attachment_id ) {
						if ( $last !== $attachment_id ) {
							$protected[ $attachment_id ] = true;
						}
					}
				}
			}

			$fetched = count( $rows );
		} while ( $this->chunk_size === $fetched );

		return $protected;
	}

	/**
	 * Maps attachment IDs to the stem of their file, in one query per batch.
	 *
	 * @param int[] $attachment_ids Attachment IDs.
	 * @return array<int, string>
	 */
	private function attachment_stems( array $attachment_ids ) {
		global $wpdb;

		if ( ! $attachment_ids ) {
			return [];
		}

		$stems = [];

		foreach ( array_chunk( array_map( 'intval', $attachment_ids ), $this->chunk_size ) as $chunk ) {
			$id_list = implode( ',', $chunk );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Bulk read replacing one get_post_meta() per attachment.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot CLI migration.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $id_list is ints.
			$rows = $wpdb->get_results(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta}
				 WHERE meta_key = '_wp_attached_file' AND post_id IN ( {$id_list} )"
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

			foreach ( $rows as $row ) {
				if ( $row->meta_value ) {
					$stems[ (int) $row->post_id ] = $this->file_stem( $row->meta_value );
				}
			}
		}

		return $stems;
	}

	/**
	 * Every image filename mentioned in a string, reduced to its stem.
	 *
	 * @param string $string Text to scan.
	 * @return string[] Stems, in order of appearance.
	 */
	private function file_stems_in( $string ) {
		if ( ! preg_match_all( '/[^\/"\'\s>\\\\]+\.(?:png|jpe?g|gif|webp|avif|bmp|tiff?|svg)/i', $string, $matches ) ) {
			return [];
		}

		$stems = [];
		foreach ( $matches[0] as $filename ) {
			$stems[] = $this->file_stem( $filename );
		}

		return $stems;
	}

	/**
	 * Reduces a filename to the stem shared by every file core generates from one
	 * upload, so a reference to a thumbnail protects the attachment it belongs to.
	 *
	 * Content that prints an image from a custom field carries neither `wp-image-N`
	 * nor a block id — only a URL, and usually a resized one. Matching the original
	 * filename alone misses those, and deleting the attachment takes the resized files
	 * with it. The extension is kept: a resized variant shares it, while `logo.png` and
	 * `logo.jpg` are unrelated files that must not protect each other.
	 *
	 * @param string $filename File name, path or URL.
	 * @return string Lowercased stem.
	 */
	private function file_stem( $filename ) {
		$name = strtolower( wp_basename( (string) $filename ) );

		if ( ! preg_match( '/^(.+)(\.[a-z0-9]+)$/', $name, $matches ) ) {
			return $name;
		}

		// -150x150 (intermediate size), -scaled (big-image threshold), -e1699999999
		// (edited in the media editor).
		$base = preg_replace( '/-\d+x\d+$/', '', $matches[1] );
		$base = preg_replace( '/-scaled$/', '', $base );
		$base = preg_replace( '/-e\d{10,}$/', '', $base );

		return $base . $matches[2];
	}

	/**
	 * SQL fragment matching meta keys that may hold an attachment reference.
	 *
	 * @param string $column Column to match against.
	 * @return string
	 */
	private function reference_key_sql( $column ) {
		global $wpdb;

		/**
		 * Filters the meta-key patterns treated as possibly holding a reference to an
		 * attachment, and so blocking its deletion.
		 *
		 * Patterns are SQL LIKE expressions matched against the meta key. Adding one
		 * only ever protects more attachments; it cannot cause a deletion.
		 *
		 * @since $$next-version$$
		 *
		 * @param string[] $patterns LIKE patterns.
		 */
		$patterns = apply_filters( 'job_manager_dedupe_reference_meta_key_patterns', self::REFERENCE_KEY_PATTERNS );

		$clauses = [];
		foreach ( (array) $patterns as $pattern ) {
			if ( is_string( $pattern ) && '' !== $pattern ) {
				// The column name is a class-controlled literal and stays outside
				// prepare(); only the pattern, which is filterable, is bound.
				$clauses[] = $column . ' LIKE ' . $wpdb->prepare( '%s', $pattern );
			}
		}

		if ( ! $clauses ) {
			return '1=0';
		}

		return implode( ' OR ', $clauses );
	}

	/**
	 * SQL fragment excluding core's own attachment plumbing from the sweep.
	 *
	 * @param string $column Column to match against.
	 * @return string
	 */
	private function excluded_key_sql( $column ) {
		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( self::SELF_DESCRIBING_META_KEYS ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $column is a class-controlled column name and $placeholders is generated from a class constant; the key values are bound.
		return $wpdb->prepare( $column . " NOT IN ( {$placeholders} )", self::SELF_DESCRIBING_META_KEYS );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	}

	/**
	 * Copies curated fields onto the canonical where it has none of its own.
	 *
	 * The canonical is the oldest copy, which is typically the auto-generated
	 * submission upload; alt text and captions an editor wrote tend to live on a
	 * later copy that is about to be deleted.
	 *
	 * @param int $duplicate Attachment about to be deleted.
	 * @param int $canonical Attachment being kept.
	 */
	private function carry_over_metadata( $duplicate, $canonical ) {
		global $wpdb;

		$alt = get_post_meta( $duplicate, '_wp_attachment_image_alt', true );
		if ( $alt && ! get_post_meta( $canonical, '_wp_attachment_image_alt', true ) ) {
			update_post_meta( $canonical, '_wp_attachment_image_alt', $alt );
		}

		$duplicate_post = get_post( $duplicate );
		$canonical_post = get_post( $canonical );
		if ( ! $duplicate_post || ! $canonical_post ) {
			return;
		}

		// Spelled out per field rather than looped over a list of field names, so the
		// array keys stay literal.
		$update = [];

		if ( '' !== trim( (string) $duplicate_post->post_excerpt ) && '' === trim( (string) $canonical_post->post_excerpt ) ) {
			$update['post_excerpt'] = (string) $duplicate_post->post_excerpt;
		}

		if ( '' !== trim( (string) $duplicate_post->post_content ) && '' === trim( (string) $canonical_post->post_content ) ) {
			$update['post_content'] = (string) $duplicate_post->post_content;
		}

		if ( ! $update ) {
			return;
		}

		// Written directly rather than through wp_update_post(): under CLI there is no
		// current user, so every save would run the caption and description through
		// wp_filter_post_kses and strip markup out of text this command is only moving
		// from one row to another.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Copying two columns verbatim; a filtered save would alter them. The cache is cleared below.
		$wpdb->update( $wpdb->posts, $update, [ 'ID' => $canonical ] );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		clean_post_cache( $canonical );
	}

	/**
	 * Deletes a redundant attachment without taking any file the canonical (or any
	 * other attachment) still needs with it.
	 *
	 * Two attachment rows can share one `_wp_attached_file`, and core's file cleanup
	 * does no reference counting — so unlinking on behalf of the duplicate would
	 * leave the survivor as a row whose file is gone. `force_delete` is left off so
	 * sites with MEDIA_TRASH keep an undo.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool Whether the attachment was deleted.
	 */
	private function delete_attachment( $attachment_id ) {
		if ( ! $this->file_is_shared( $attachment_id ) ) {
			return (bool) wp_delete_attachment( $attachment_id );
		}

		// A closure rather than a shared callback so removing it cannot take another
		// consumer's identical filter with it, and try/finally so a fatal inside the
		// delete cannot leave file deletion vetoed for the rest of the process.
		$veto = static function () {
			return '';
		};

		add_filter( 'wp_delete_file', $veto );
		try {
			$deleted = wp_delete_attachment( $attachment_id );
		} finally {
			remove_filter( 'wp_delete_file', $veto );
		}

		return (bool) $deleted;
	}

	/**
	 * Whether another attachment row points at the same file on disk.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private function file_is_shared( $attachment_id ) {
		global $wpdb;

		$relative_path = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( ! $relative_path ) {
			return false;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- No API for finding attachments by file path.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot CLI migration.
		$shared = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s AND post_id <> %d",
				$relative_path,
				(int) $attachment_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		return $shared > 0;
	}

	/**
	 * Stores the canonical attachment's content hash so the runtime dedup reuses
	 * it for future identical uploads.
	 *
	 * Hashes the file as uploaded, not whatever `get_attached_file()` currently
	 * returns: for images over `big_image_size_threshold` core replaces the attached
	 * file with a `-scaled` copy, while the runtime dedup hashes the upload before
	 * that happens. Hashing the scaled file would store a value its lookup can never
	 * match — permanently, since this backfill skips attachments that already have
	 * a hash.
	 *
	 * @param int $canonical Canonical attachment ID.
	 */
	private function backfill_hash( $canonical ) {
		if ( get_post_meta( $canonical, self::HASH_META_KEY, true ) ) {
			return;
		}
		$file = wp_get_original_image_path( $canonical );
		if ( ! $file ) {
			$file = get_attached_file( $canonical );
		}
		if ( $file && is_file( $file ) ) {
			$hash = md5_file( $file );
			if ( $hash ) {
				update_post_meta( $canonical, self::HASH_META_KEY, $hash );
			}
		}
	}
}
