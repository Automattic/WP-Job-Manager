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
		$wpdb->wpjm_stats = $wpdb->prefix . 'wpjm_stats';
		$wpdb->tables[]   = 'wpjm_stats';
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
		add_action( 'wp_enqueue_scripts', [ $this, 'frontend_scripts' ] );
		add_action( 'wp_ajax_job_manager_log_stat', [ $this, 'maybe_log_stat_ajax' ] );
		add_action( 'wp_ajax_nopriv_job_manager_log_stat', [ $this, 'maybe_log_stat_ajax' ] );
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

	/**
	 * Log multiple stats in one go. Triggered in an ajax call.
	 *
	 * @return void
	 */
	public function maybe_log_stat_ajax() {
		if ( ! ( defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {
			return;
		}

		$post_data = stripslashes_deep( $_POST );

		if ( ! isset( $post_data['_ajax_nonce'] ) || ! wp_verify_nonce( $post_data['_ajax_nonce'], 'ajax-nonce' ) ) {
			return;
		}

		$post_id = isset( $post_data['post_id'] ) ? absint( $post_data['post_id'] ) : 0;

		if ( empty( $post_id ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );

		if ( \WP_Job_Manager_Post_Types::PT_LISTING !== $post_type ) {
			return;
		}

		$stats = isset( $post_data['stats'] ) ? explode( ',', sanitize_text_field( $post_data['stats'] ) ) : [];

		// TODO: Maybe optimize this into a single insert?
		foreach ( $stats as $stat_name ) {
			$stat_name = trim( strtolower( $stat_name ) );
			if ( ! in_array( $stat_name, $this->get_registered_stat_names(), true ) ) {
				continue;
			}
			$this->log_stat( trim( $stat_name ), [ 'post_id' => $post_id ] );
		}
	}

	/**
	 * Get stat names.
	 *
	 * @return int[]|string[]
	 */
	private function get_registered_stat_names() {
		return array_keys( $this->get_registered_stats() );
	}

	/**
	 * Register any frontend JS scripts.
	 *
	 * @return void
	 */
	public function frontend_scripts() {
		$post_id   = absint( get_queried_object_id() );
		$post_type = get_post_type( $post_id );
		if ( \WP_Job_Manager_Post_Types::PT_LISTING !== $post_type ) {
			return;
		}

		\WP_Job_Manager::register_script(
			'wp-job-manager-stats',
			'js/wpjm-stats.js',
			[ 'jquery' ],
			true
		);

		$script_data = [
			'ajax_url'     => admin_url( 'admin-ajax.php' ),
			'ajax_nonce'   => wp_create_nonce( 'ajax-nonce' ),
			'post_id'      => $post_id,
			'stats_to_log' => $this->get_stats_for_ajax( $post_id ),
		];

		wp_enqueue_script( 'wp-job-manager-stats' );
		wp_localize_script(
			'wp-job-manager-stats',
			'job_manager_stats',
			$script_data
		);
	}

	/**
	 * Get all the registered stats.
	 *
	 * @return array
	 */
	private function get_registered_stats() {
		return (array) apply_filters(
			'wpjm_get_registered_stats',
			[
				'job_listing_view'        => [],
				'job_listing_view_unique' => [
					'unique' => true,
				],
			]
		);
	}

	/**
	 * Prepare stats for wp_localize.
	 *
	 * @param int $post_id Optional post id.
	 *
	 * @return array
	 */
	private function get_stats_for_ajax( $post_id = 0 ) {
		$ajax_stats = [];
		foreach ( $this->get_registered_stats() as $stat_name => $stat_data ) {
			$stat_ajax = [
				'name' => $stat_name,
			];
			if ( ! empty( $stat_data['unique'] ) ) {
				$stat_ajax['unique_key'] = $stat_name . '_' . $post_id;
			}
			$ajax_stats[] = $stat_ajax;
		}

		return $ajax_stats;
	}
}
