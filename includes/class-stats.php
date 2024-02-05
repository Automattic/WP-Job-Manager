<?php
/**
 * File containing the class WP_Job_Manager_Stats
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is responsible for initializing all aspects of stats for wpjm.
 */
class Stats {
	use Singleton;

	const TABLE       = 'wpjm_stats';
	const CACHE_GROUP = 'wpjm_stats';

	const DEFAULT_LOG_STAT_ARGS = [
		'group'        => '',
		'post_id'      => 0,
		'increment_by' => 1,
	];

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Do initialization of all the things needed for stats.
	 */
	public function init() {

		include_once __DIR__ . '/class-job-listing-stats.php';
		include_once __DIR__ . '/class-stats-dashboard.php';

		Stats_Dashboard::instance();

		$this->initialize_wpdb();
		$this->hook();
	}

	/**
	 * Initialize the alias for the stats table on the wpdb object.
	 *
	 * @return void
	 */
	private function initialize_wpdb() {
		global $wpdb;
		if ( isset( $wpdb->wpjm_stats ) ) {
			return;
		}
		$wpdb->wpjm_stats = $wpdb->prefix . self::TABLE;
		$wpdb->tables[]   = self::TABLE;
	}

	/**
	 * Migrate the stats table to the latest version.
	 *
	 * @return void
	 */
	public function migrate() {
		global $wpdb;
		$collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		\dbDelta(
			[
				"CREATE TABLE {$wpdb->wpjm_stats} (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`date` date NOT NULL,
				`post_id` bigint(20) DEFAULT NULL,
				`name` varchar(255) NOT NULL,
				`group` varchar(255) DEFAULT '',
				`count` bigint(20) unsigned not null default 1,
				PRIMARY KEY (`id`),
				UNIQUE INDEX `idx_wpjm_stats_name_date_group_post_id`  (`name`, `date`, `group`, `post_id`)
			) {$collate}",
			]
		);
	}

	/**
	 * Check if collecting and showing statistics are enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return get_option( 'job_manager_stats_enable', false );
	}

	/**
	 * Log a stat into the db.
	 *
	 * @param string $name         The stat name.
	 * @param array  $args {
	 * Optional args for this stat.
	 *
	 * @type string $group        The group this stat belongs to.
	 * @type int    $post_id      The post_id this stat belongs to.
	 * @type int    $increment_by The amount to increment the stat by.
	 * }
	 *
	 * @return bool
	 */
	public function log_stat( string $name, array $args = [] ) {
		global $wpdb;

		$args         = array_merge( self::DEFAULT_LOG_STAT_ARGS, $args );
		$group        = $args['group'];
		$post_id      = $args['post_id'];
		$increment_by = $args['increment_by'];

		if (
			strlen( $name ) > 255 ||
			strlen( $group ) > 255 ||
			! is_numeric( $post_id ) ||
			! is_numeric( $increment_by ) ) {
			return false;
		}

		$date_today = gmdate( 'Y-m-d' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->wpjm_stats} " .
				'(`name`, `date`, `group`, `post_id`, `count` ) ' .
				'VALUES (%s, %s, %s, %d, %d) ' .
				'ON DUPLICATE KEY UPDATE `count` = `count` + %d',
				$name,
				$date_today,
				$group,
				$post_id,
				$increment_by,
				$increment_by
			)
		);

		if ( false === $result ) {
			return false;
		}

		$cache_key = $this->get_cache_key_for_stat( $name, $group, $post_id );

		wp_cache_delete( $cache_key, 'wpjm_stats' );

		return true;
	}

	/**
	 * Get a cache key for a stat.
	 *
	 * @param string $name    The name.
	 * @param string $group   The optional group.
	 * @param int    $post_id The optional post id.
	 *
	 * @return string
	 */
	private function get_cache_key_for_stat( string $name, string $group = '', int $post_id = 0 ) {
		$hash = md5( "{$name}_{$group}_{$post_id}" );
		return "wpjm_stat_{$hash}";
	}

	/**
	 * Perform plugin activation-related stats actions.
	 *
	 * @return void
	 */
	public function activate() {
	}

	/**
	 * Run any hooks related to stats.
	 *
	 * @return void
	 */
	private function hook() {
		add_action( 'wp', [ $this, 'maybe_log_listing_view' ] );
	}

	/**
	 * Log a (non-unique) listing page view.
	 *
	 * @return void
	 */
	public function maybe_log_listing_view() {
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		$post_id   = absint( get_queried_object_id() );
		$post_type = get_post_type( $post_id );
		if ( \WP_Job_Manager_Post_Types::PT_LISTING !== $post_type ) {
			return;
		}

		$this->log_stat( 'job_listing_view', [ 'post_id' => $post_id ] );
	}
}
