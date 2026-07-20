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
 * again for something this class knows nothing about. So no attachment is ever
 * deleted while anything outside the two reference types handled here (listing
 * featured images and the `_company_logo` user default) still points at it —
 * see `find_protected_ids()`. Unrecognised reference means "keep", so the
 * failure mode is a duplicate left behind rather than a broken image.
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
	];

	/**
	 * Rows per batch when streaming post content looking for inline references.
	 */
	const CONTENT_SCAN_BATCH = 200;

	/**
	 * Memoised content signatures, keyed by attachment ID. Hashing is the
	 * expensive part of a run and the same attachment is examined more than once.
	 *
	 * @var array<int, string>
	 */
	private $signature_cache = [];

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
	 * ## EXAMPLES
	 *
	 *     # Preview what would be de-duplicated across the whole site.
	 *     wp jm dedupe-logos
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
			$user_id = absint( $assoc_args['owner'] );
			// Without this, a typo silently becomes 0 — i.e. the whole site.
			if ( ! $user_id || ! get_userdata( $user_id ) ) {
				\WP_CLI::error( sprintf( 'No such user: %s', $assoc_args['owner'] ) );
			}
		}

		if ( ! $dry_run ) {
			// Deletion is not reversible on sites without MEDIA_TRASH.
			\WP_CLI::confirm( 'This will delete redundant logo attachments. Run without --live first to preview. Continue?', $assoc_args );
		}

		$progress = null;
		$report   = ( new self() )->run(
			[
				'dry_run' => $dry_run,
				'user_id' => $user_id,
				'on_plan' => function ( $total ) use ( &$progress ) {
					if ( $total ) {
						$progress = \WP_CLI\Utils\make_progress_bar( 'Merging duplicates', $total );
					}
				},
				'on_tick' => function () use ( &$progress ) {
					if ( $progress ) {
						$progress->tick();
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
		// record of what was collapsed into what.
		foreach ( $report['plan'] as $group ) {
			\WP_CLI::log( sprintf( '  %d <- %s', $group['canonical'], implode( ', ', $group['duplicates'] ) ) );
		}

		if ( $dry_run ) {
			\WP_CLI::success( sprintf( 'Would re-point references and delete %d attachment(s). Re-run with --live to apply.', $report['duplicates'] ) );
			return;
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
	 * Finds groups of identical logo attachments and, unless this is a dry run,
	 * merges each group into its oldest attachment.
	 *
	 * @since $$next-version$$
	 *
	 * @param array $args Optional arguments: 'dry_run' (bool, default true) only
	 *                    reports what would change; 'user_id' (int, default 0)
	 *                    limits de-duplication to a single owner.
	 * @return array Report counts: groups, duplicates, references_repointed,
	 *               attachments_deleted.
	 */
	public function run( array $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'dry_run' => true,
				'user_id' => 0,
				'on_plan' => null,
				'on_tick' => null,
			]
		);

		$report = [
			'groups'               => 0,
			'duplicates'           => 0,
			'references_repointed' => 0,
			'attachments_deleted'  => 0,
			'skipped_referenced'   => 0,
			'delete_failures'      => [],
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

			if ( $args['dry_run'] ) {
				continue;
			}

			$this->backfill_hash( $canonical );

			foreach ( $deletable as $duplicate ) {
				$repoint_map[ $duplicate ] = $canonical;
			}
		}

		if ( $args['dry_run'] || ! $repoint_map ) {
			return $report;
		}

		if ( is_callable( $args['on_plan'] ) ) {
			call_user_func( $args['on_plan'], count( $repoint_map ) );
		}

		// Move every reference first, then delete: a run that dies partway leaves an
		// un-deleted duplicate rather than a listing pointing at a deleted post.
		$report['references_repointed'] = $this->repoint_all( $repoint_map );

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

		$referenced_ids = $this->collect_referenced_logo_ids();

		// Queries above ask for IDs only, which does not prime the post cache — so
		// without this every signature costs a get_post() round trip.
		_prime_post_caches( $referenced_ids, false, true );

		foreach ( $referenced_ids as $attachment_id ) {
			$signature = $this->attachment_signature( $attachment_id, $user_id );
			if ( $signature ) {
				$logo_signatures[ $signature ]                                      = true;
				$owner_ids[ (int) get_post_field( 'post_author', $attachment_id ) ] = true;
			}
		}

		if ( ! $logo_signatures ) {
			return [];
		}

		// Group every image attachment those owners hold by signature, keeping
		// only signatures that match a referenced logo (folds in orphaned copies).
		$by_signature = [];
		$owner_images = $this->collect_owner_image_ids( array_keys( $owner_ids ) );
		_prime_post_caches( $owner_images, false, true );

		foreach ( $owner_images as $attachment_id ) {
			$signature = $this->attachment_signature( $attachment_id, $user_id );
			if ( $signature && isset( $logo_signatures[ $signature ] ) ) {
				$by_signature[ $signature ][] = (int) $attachment_id;
			}
		}

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
	 * Every registered post status, so listings in statuses WP_Query's 'any'
	 * shorthand hides are still seen. 'any' excludes any status registered with
	 * exclude_from_search, which covers WPJM's own `expired` and `preview` — an
	 * expired listing is a normal end state and still references its logo.
	 *
	 * @return string[]
	 */
	private function all_post_statuses() {
		return array_values( get_post_stati() );
	}

	/**
	 * Collects attachment IDs currently used as a logo: listing featured images
	 * and the `_company_logo` user meta default.
	 *
	 * @return int[] Unique attachment IDs.
	 */
	private function collect_referenced_logo_ids() {
		global $wpdb;

		$ids = [];

		// Deliberately unfiltered by author: a listing authored by one user can carry
		// a logo owned by another, so ownership is applied to the attachment in
		// attachment_signature() rather than to whatever references it.
		//
		// Read as one join rather than a get_posts( fields => ids ) loop calling
		// get_post_meta() per listing, which costs a query per listing on a site with
		// tens of thousands of them.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Bulk read replacing one query per listing.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot CLI migration.
		$thumbnail_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.meta_value
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s AND p.post_type = %s",
				self::HANDLED_POST_META_KEY,
				\WP_Job_Manager_Post_Types::PT_LISTING
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $thumbnail_ids as $thumbnail_id ) {
			if ( (int) $thumbnail_id ) {
				$ids[] = (int) $thumbnail_id;
			}
		}

		$user_args = [
			'fields'   => 'ID',
			'meta_key' => self::HANDLED_USER_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		];
		foreach ( get_users( $user_args ) as $owner_id ) {
			$logo_id = (int) get_user_meta( $owner_id, self::HANDLED_USER_META_KEY, true );
			if ( $logo_id ) {
				$ids[] = $logo_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Collects every image attachment owned by the given users, so orphaned
	 * duplicate copies (referenced by nothing) are considered for merging.
	 *
	 * @param int[] $owner_ids Owner user IDs.
	 * @return int[] Attachment IDs.
	 */
	private function collect_owner_image_ids( $owner_ids ) {
		if ( ! $owner_ids ) {
			return [];
		}

		return array_map(
			'intval',
			get_posts(
				[
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'post_mime_type' => 'image',
					'author__in'     => array_map( 'intval', $owner_ids ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				]
			)
		);
	}

	/**
	 * Re-points every known logo reference according to a duplicate => canonical map.
	 *
	 * Batched: two reads for the whole run rather than two per duplicate. Writes are
	 * still per affected row, which is proportional to real references rather than to
	 * the size of the media library.
	 *
	 * @param array<int, int> $map Duplicate attachment ID => canonical attachment ID.
	 * @return int Number of references updated.
	 */
	private function repoint_all( array $map ) {
		if ( ! $map ) {
			return 0;
		}

		$count      = 0;
		$duplicates = array_keys( $map );

		$jobs = get_posts(
			[
				'post_type'      => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_status'    => $this->all_post_statuses(),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => self::HANDLED_POST_META_KEY,
						'value'   => $duplicates,
						'compare' => 'IN',
					],
				],
			]
		);
		foreach ( $jobs as $job_id ) {
			$current = (int) get_post_meta( $job_id, self::HANDLED_POST_META_KEY, true );
			if ( isset( $map[ $current ] ) ) {
				update_post_meta( $job_id, self::HANDLED_POST_META_KEY, $map[ $current ] );
				++$count;
			}
		}

		$users = get_users(
			[
				'fields'     => 'ID',
				'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => self::HANDLED_USER_META_KEY,
						'value'   => $duplicates,
						'compare' => 'IN',
					],
				],
			]
		);
		foreach ( $users as $owner_id ) {
			$current = (int) get_user_meta( $owner_id, self::HANDLED_USER_META_KEY, true );
			if ( isset( $map[ $current ] ) ) {
				update_user_meta( $owner_id, self::HANDLED_USER_META_KEY, $map[ $current ] );
				++$count;
			}
		}

		return $count;
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
		global $wpdb;

		$candidate_ids = array_values( array_unique( array_map( 'intval', $candidate_ids ) ) );
		if ( ! $candidate_ids ) {
			return [];
		}

		$protected = [];
		$id_list   = implode( ',', $candidate_ids );
		$key_sql   = $this->reference_key_sql();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reference sweep across core tables has no API equivalent.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot CLI migration; a cached answer would be worse than none.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $id_list is ints; $key_sql is built from a class constant.
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Raw SQL, not a WP_Query meta arg; the scan is the point of the command.

		// Post meta. `_thumbnail_id` on a listing is the one case repoint_references()
		// handles; the same key on any other post type is not.
		$rows = $wpdb->get_results(
			"SELECT pm.meta_value, pm.meta_key, p.post_type
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_value IN ( {$id_list} )
			   AND pm.post_id NOT IN ( {$id_list} )
			   AND ( {$key_sql} )"
		);
		foreach ( $rows as $row ) {
			$handled = self::HANDLED_POST_META_KEY === $row->meta_key
				&& \WP_Job_Manager_Post_Types::PT_LISTING === $row->post_type;
			if ( ! $handled ) {
				$protected[ (int) $row->meta_value ] = true;
			}
		}

		// User meta, other than the `_company_logo` default that is handled.
		$rows = $wpdb->get_results(
			"SELECT meta_value, meta_key FROM {$wpdb->usermeta}
			 WHERE meta_value IN ( {$id_list} ) AND ( {$key_sql} )"
		);
		foreach ( $rows as $row ) {
			if ( self::HANDLED_USER_META_KEY !== $row->meta_key ) {
				$protected[ (int) $row->meta_value ] = true;
			}
		}

		// Term meta.
		$term_values = $wpdb->get_col(
			"SELECT meta_value FROM {$wpdb->termmeta}
			 WHERE meta_value IN ( {$id_list} ) AND ( {$key_sql} )"
		);
		foreach ( $term_values as $value ) {
			$protected[ (int) $value ] = true;
		}

		// Options storing a bare attachment ID.
		$option_values = $wpdb->get_col(
			"SELECT option_value FROM {$wpdb->options}
			 WHERE option_name IN ( 'site_logo', 'site_icon' ) AND option_value IN ( {$id_list} )"
		);
		foreach ( $option_values as $value ) {
			$protected[ (int) $value ] = true;
		}

		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		return $protected + $this->find_inline_content_references( $candidate_ids );
	}

	/**
	 * Finds candidates referenced from inside post content: the block editor's
	 * {"id":N}, the classic editor's wp-image-N class, and plain URLs to the file.
	 *
	 * Content cannot be indexed for this, so it is one streamed pass over the posts
	 * that contain any image marker at all, matched in PHP — rather than one LIKE
	 * scan per candidate.
	 *
	 * @param int[] $candidate_ids Attachment IDs being considered for deletion.
	 * @return array<int, true> Protected IDs.
	 */
	private function find_inline_content_references( array $candidate_ids ) {
		global $wpdb;

		$protected = [];
		$by_id     = array_fill_keys( $candidate_ids, true );
		$basenames = $this->attachment_basenames( $candidate_ids );
		$offset    = 0;

		do {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Content scan has no API equivalent.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot CLI migration.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_content FROM {$wpdb->posts}
					 WHERE post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s
					 ORDER BY ID LIMIT %d OFFSET %d",
					'%' . $wpdb->esc_like( 'wp-image-' ) . '%',
					'%' . $wpdb->esc_like( '"id":' ) . '%',
					'%' . $wpdb->esc_like( '/uploads/' ) . '%',
					self::CONTENT_SCAN_BATCH,
					$offset
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

			foreach ( $rows as $row ) {
				$content = (string) $row->post_content;

				if ( preg_match_all( '/(?:wp-image-|"id":\s*)(\d+)/', $content, $matches ) ) {
					foreach ( $matches[1] as $found ) {
						$found = (int) $found;
						if ( isset( $by_id[ $found ] ) && (int) $row->ID !== $found ) {
							$protected[ $found ] = true;
						}
					}
				}

				foreach ( $basenames as $attachment_id => $basename ) {
					if ( (int) $row->ID !== $attachment_id && false !== strpos( $content, $basename ) ) {
						$protected[ $attachment_id ] = true;
					}
				}
			}

			$fetched = count( $rows );
			$offset += self::CONTENT_SCAN_BATCH;
		} while ( self::CONTENT_SCAN_BATCH === $fetched );

		return $protected;
	}

	/**
	 * Maps attachment IDs to their file basename in one query.
	 *
	 * @param int[] $attachment_ids Attachment IDs.
	 * @return array<int, string>
	 */
	private function attachment_basenames( array $attachment_ids ) {
		global $wpdb;

		if ( ! $attachment_ids ) {
			return [];
		}

		$id_list = implode( ',', array_map( 'intval', $attachment_ids ) );

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

		$basenames = [];
		foreach ( $rows as $row ) {
			if ( $row->meta_value ) {
				$basenames[ (int) $row->post_id ] = wp_basename( $row->meta_value );
			}
		}

		return $basenames;
	}

	/**
	 * SQL fragment matching meta keys that may hold an attachment reference.
	 *
	 * @return string
	 */
	private function reference_key_sql() {
		global $wpdb;

		$clauses = [];
		foreach ( self::REFERENCE_KEY_PATTERNS as $pattern ) {
			$clauses[] = $wpdb->prepare( 'meta_key LIKE %s', $pattern );
		}

		return implode( ' OR ', $clauses );
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
		$alt = get_post_meta( $duplicate, '_wp_attachment_image_alt', true );
		if ( $alt && ! get_post_meta( $canonical, '_wp_attachment_image_alt', true ) ) {
			update_post_meta( $canonical, '_wp_attachment_image_alt', $alt );
		}

		$duplicate_post = get_post( $duplicate );
		$canonical_post = get_post( $canonical );
		if ( ! $duplicate_post || ! $canonical_post ) {
			return;
		}

		$update = [];
		foreach ( [ 'post_excerpt', 'post_content' ] as $field ) {
			if ( '' !== trim( (string) $duplicate_post->$field ) && '' === trim( (string) $canonical_post->$field ) ) {
				$update[ $field ] = $duplicate_post->$field;
			}
		}
		if ( $update ) {
			$update['ID'] = $canonical;
			wp_update_post( $update );
		}
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

		$veto = '__return_empty_string';
		add_filter( 'wp_delete_file', $veto );
		$deleted = wp_delete_attachment( $attachment_id );
		remove_filter( 'wp_delete_file', $veto );

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
	 * @param int $canonical Canonical attachment ID.
	 */
	private function backfill_hash( $canonical ) {
		if ( get_post_meta( $canonical, self::HASH_META_KEY, true ) ) {
			return;
		}
		$file = get_attached_file( $canonical );
		if ( $file && is_file( $file ) ) {
			$hash = md5_file( $file );
			if ( $hash ) {
				update_post_meta( $canonical, self::HASH_META_KEY, $hash );
			}
		}
	}
}
