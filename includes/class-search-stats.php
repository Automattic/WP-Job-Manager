<?php
/**
 * File containing the class WP_Job_Manager\Search_Stats
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Log the search filters used on the `[jobs]` shortcode.
 *
 * @since 2.4.0
 */
class Search_Stats {
	use Singleton;

	/**
	 * Filter name for the search keyword.
	 */
	const FILTER_KEYWORD = 'keyword';

	/**
	 * Filter name for the search location.
	 */
	const FILTER_LOCATION = 'location';

	/**
	 * Filter name for the search category.
	 */
	const FILTER_CATEGORY = 'category';

	/**
	 * Filter name for the search job type.
	 */
	const FILTER_JOB_TYPE = 'job_type';

	private const TABLE = 'wpjm_search_stats';

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_wpdb_alias();
	}

	/**
	 * Initialize the alias for the search stats table on the wpdb object.
	 *
	 * @return void
	 */
	private function init_wpdb_alias() {
		global $wpdb;
		if ( isset( $wpdb->wpjm_search_stats ) ) {
			return;
		}
		$wpdb->wpjm_search_stats = $wpdb->prefix . self::TABLE;
		$wpdb->tables[]          = self::TABLE;
	}

	/**
	 * Migrate the search stats table to the latest version.
	 *
	 * @return void
	 */
	public function migrate_db() {
		global $wpdb;
		$collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		\dbDelta(
			[
				"CREATE TABLE {$wpdb->wpjm_search_stats} (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`date` date NOT NULL,
				`filter` varchar(20) NOT NULL,
				`value` varchar(191) NOT NULL,
				`value_hash` char(32) NOT NULL,
				`count` bigint(20) unsigned not null default 1,
				PRIMARY KEY (`id`),
				UNIQUE INDEX `idx_wpjm_search_stats_hash_date` (`value_hash`, `date`)
			) {$collate}",
			]
		);
	}

	/**
	 * Log the filter values used for a `[jobs]` search.
	 *
	 * A filter value that is an array (eg. `category`, `job_type`) is logged as one
	 * row per selected value rather than a joined string, since a comma-joined list
	 * of every combination would be far less useful for aggregation.
	 *
	 * @since 2.4.0
	 *
	 * @param array $filters {
	 * Filter name to value (or array of values) used in the search.
	 *
	 * @type string       $keyword  Search keyword.
	 * @type string       $location Search location.
	 * @type array|string $category Selected categories.
	 * @type array|string $job_type Selected job types.
	 * }
	 *
	 * @return bool
	 */
	public function log_search( array $filters ) {

		if ( ! Stats::is_enabled() ) {
			return false;
		}

		if ( isset( $filters[ self::FILTER_KEYWORD ] ) ) {
			$filters[ self::FILTER_KEYWORD ] = $this->normalize_keyword( (string) $filters[ self::FILTER_KEYWORD ] );
		}

		$rows = [];
		$seen = [];
		foreach ( $filters as $filter => $values ) {
			foreach ( (array) $values as $value ) {
				$value = trim( (string) $value );
				if ( '' === $value ) {
					continue;
				}
				$dedup_key = $filter . '|' . $value;
				if ( isset( $seen[ $dedup_key ] ) ) {
					continue;
				}
				$seen[ $dedup_key ] = true;
				$rows[]             = [
					'filter' => $filter,
					'value'  => $value,
				];
			}
		}

		if ( empty( $rows ) ) {
			return false;
		}

		return $this->batch_log( $rows );
	}

	/**
	 * Write a batch of search stat rows, incrementing the count on duplicates.
	 *
	 * @param array[] $rows {
	 * Array of rows to log.
	 *
	 * @type string $filter The filter name.
	 * @type string $value  The filter value.
	 * }
	 *
	 * @return bool
	 */
	private function batch_log( array $rows ) {
		global $wpdb;

		$today  = gmdate( 'Y-m-d' );
		$values = [];

		foreach ( $rows as $row ) {
			if ( strlen( $row['filter'] ) > 20 || strlen( $row['value'] ) > 191 ) {
				continue;
			}
			$value_hash = md5( $row['filter'] . '|' . $row['value'] );
			$values[]   = $wpdb->prepare( '(%s, %s, %s, %s, %d)', $today, $row['filter'], $row['value'], $value_hash, 1 );
		}

		if ( empty( $values ) ) {
			return false;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,
		$result = $wpdb->query(
			"INSERT INTO {$wpdb->wpjm_search_stats} " .
			'(`date`, `filter`, `value`, `value_hash`, `count` )' .
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'VALUES ' . implode( ', ', $values ) .
			'ON DUPLICATE KEY UPDATE `count` = `count` + VALUES(`count`)',
		);
		// phpcs:enable

		return false !== $result;
	}

	/**
	 * Normalize a search keyword before logging: lowercase, trim, and collapse
	 * repeated whitespace so equivalent searches aggregate together.
	 *
	 * @param string $keyword The raw search keyword.
	 *
	 * @return string
	 */
	private function normalize_keyword( string $keyword ) {
		$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $keyword, 'UTF-8' ) : strtolower( $keyword );
		return trim( preg_replace( '/\s+/', ' ', $lower ) );
	}

	/**
	 * Get logged search stats.
	 *
	 * @param string $filter Optional filter name to limit results to.
	 * @param string $date   Optional date (YYYY-MM-DD) to limit results to.
	 * @param int    $limit  Maximum number of rows to return.
	 *
	 * @return array
	 */
	public function get_stats( $filter = '', $date = null, $limit = 1000 ) {
		global $wpdb;

		$query  = "SELECT * FROM {$wpdb->wpjm_search_stats} WHERE 1=1 ";
		$params = [];

		if ( ! empty( $filter ) ) {
			$query   .= ' AND filter = %s';
			$params[] = $filter;
		}

		if ( ! empty( $date ) ) {
			$query   .= ' AND date = %s';
			$params[] = $date;
		}

		$query   .= ' ORDER BY id DESC LIMIT %d';
		$params[] = absint( $limit );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic query.
		$query = $wpdb->prepare( $query, $params );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared above; admin-only aggregate read, matches Stats::get_stats().
		return $wpdb->get_results( $query );
	}
}
