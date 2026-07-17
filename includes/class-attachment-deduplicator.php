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
 * Scoped to logos only (listing featured images and the `_company_logo` user
 * meta) and to a single owner per group, mirroring the runtime dedup boundary:
 * attachments belonging to different users are never merged.
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
	 * Registers the WP-CLI command.
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
	 * [--user=<id>]
	 * : Limit de-duplication to a single owner (user ID).
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
	 *     wp jm dedupe-logos --user=42 --live
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 */
	public static function cli( $args, $assoc_args ) {
		$dry_run = empty( $assoc_args['live'] );
		$user_id = isset( $assoc_args['user'] ) ? absint( $assoc_args['user'] ) : 0;

		$report = ( new self() )->run(
			[
				'dry_run' => $dry_run,
				'user_id' => $user_id,
			]
		);

		\WP_CLI::log( $dry_run ? 'DRY RUN — no changes made.' : 'LIVE' );
		\WP_CLI::log( sprintf( 'Duplicate groups found:  %d', $report['groups'] ) );
		\WP_CLI::log( sprintf( 'Redundant attachments:   %d', $report['duplicates'] ) );

		if ( $dry_run ) {
			\WP_CLI::success( sprintf( 'Would re-point references and delete %d attachment(s). Re-run with --live to apply.', $report['duplicates'] ) );
		} else {
			\WP_CLI::success( sprintf( 'Re-pointed %d reference(s); deleted %d attachment(s).', $report['references_repointed'], $report['attachments_deleted'] ) );
		}
	}

	/**
	 * Finds groups of identical logo attachments and, unless this is a dry run,
	 * merges each group into its oldest attachment.
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
			]
		);

		$report = [
			'groups'               => 0,
			'duplicates'           => 0,
			'references_repointed' => 0,
			'attachments_deleted'  => 0,
		];

		foreach ( $this->find_duplicate_logo_groups( (int) $args['user_id'] ) as $group ) {
			++$report['groups'];
			$report['duplicates'] += count( $group['duplicates'] );

			if ( $args['dry_run'] ) {
				continue;
			}

			$canonical = $group['canonical'];
			$this->backfill_hash( $canonical );

			foreach ( $group['duplicates'] as $duplicate ) {
				$report['references_repointed'] += $this->repoint_references( $duplicate, $canonical );
				wp_delete_attachment( $duplicate, true );
				++$report['attachments_deleted'];
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
		foreach ( $this->collect_referenced_logo_ids( $user_id ) as $attachment_id ) {
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
		foreach ( $this->collect_owner_image_ids( array_keys( $owner_ids ) ) as $attachment_id ) {
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
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return '';
		}
		if ( $user_id && (int) $attachment->post_author !== $user_id ) {
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
	 * Collects attachment IDs currently used as a logo: listing featured images
	 * and the `_company_logo` user meta default.
	 *
	 * @param int $user_id Limit to one owner (0 = all).
	 * @return int[] Unique attachment IDs.
	 */
	private function collect_referenced_logo_ids( $user_id = 0 ) {
		$ids = [];

		$job_args = [
			'post_type'      => \WP_Job_Manager_Post_Types::PT_LISTING,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		];
		if ( $user_id ) {
			$job_args['author'] = $user_id;
		}
		foreach ( get_posts( $job_args ) as $job_id ) {
			$thumbnail_id = (int) get_post_meta( $job_id, '_thumbnail_id', true );
			if ( $thumbnail_id ) {
				$ids[] = $thumbnail_id;
			}
		}

		$user_args = [
			'fields'   => 'ID',
			'meta_key' => '_company_logo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		];
		if ( $user_id ) {
			$user_args['include'] = [ $user_id ];
		}
		foreach ( get_users( $user_args ) as $owner_id ) {
			$logo_id = (int) get_user_meta( $owner_id, '_company_logo', true );
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

		return get_posts(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'author__in'     => array_map( 'intval', $owner_ids ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);
	}

	/**
	 * Re-points every known logo reference from a duplicate to the canonical.
	 *
	 * @param int $duplicate Duplicate attachment ID.
	 * @param int $canonical Canonical attachment ID to point at.
	 * @return int Number of references updated.
	 */
	private function repoint_references( $duplicate, $canonical ) {
		$count = 0;

		$jobs = get_posts(
			[
				'post_type'      => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $duplicate, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		foreach ( $jobs as $job_id ) {
			update_post_meta( $job_id, '_thumbnail_id', $canonical );
			++$count;
		}

		$users = get_users(
			[
				'fields'     => 'ID',
				'meta_key'   => '_company_logo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $duplicate, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		foreach ( $users as $owner_id ) {
			update_user_meta( $owner_id, '_company_logo', $canonical );
			++$count;
		}

		return $count;
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
