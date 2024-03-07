<?php
/**
 * File containing the class WP_Job_Manager\Stats
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collect and retrieve job statistics.
 *
 * @since $$next-version$$
 */
class Stats {
	use Singleton;

	/**
	 * Cache group for stat queries.
	 */
	const CACHE_GROUP = 'wpjm_stats';

	/**
	 * Setting key for enabling stats.
	 */
	const OPTION_ENABLE_STATS = 'job_manager_stats_enable';

	private const TABLE = 'wpjm_stats';

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

		$this->init_wpdb_alias();

		if ( ! self::is_enabled() ) {
			return;
		}

		Stats_Dashboard::instance();

		$this->init_hooks();
	}

	/**
	 * Initialize the alias for the stats table on the wpdb object.
	 *
	 * @return void
	 */
	private function init_wpdb_alias() {
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
	public function migrate_db() {
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
		return (bool) get_option( self::OPTION_ENABLE_STATS, false );
	}

	/**
	 * Log a stat into the db.
	 *
	 * @param string $name The stat name.
	 * @param array  $args {
	 * Optional args for this stat.
	 *
	 * @type string  $group The group this stat belongs to.
	 * @type int     $post_id The post_id this stat belongs to.
	 * @type int     $count The amount to increment the stat by.
	 * @type string  $date Date in YYYY-MM-DD format.
	 * }
	 *
	 * @return bool
	 */
	public function log_stat( string $name, array $args = [] ) {

		if ( ! self::is_enabled() ) {
			return false;
		}

		$args = wp_parse_args(
			$args,
			[
				'group'   => '',
				'post_id' => 0,
				'count'   => 1,
				'date'    => gmdate( 'Y-m-d' ),
			]
		);

		$group   = $args['group'];
		$post_id = absint( $args['post_id'] );
		$count   = $args['count'];

		if (
			strlen( $name ) > 255 ||
			strlen( $group ) > 255 ||
			! $post_id ||
			! is_integer( $count ) ) {
			return false;
		}

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->wpjm_stats} " .
				'(`name`, `date`, `group`, `post_id`, `count` ) ' .
				'VALUES (%s, %s, %s, %d, %d) ' .
				'ON DUPLICATE KEY UPDATE `count` = `count` + %d',
				$name,
				$args['date'],
				$group,
				$post_id,
				$count,
				$count
			)
		);

		if ( false === $result ) {
			return false;
		}

		return true;
	}

	/**
	 * Delete all stats for a given job.
	 *
	 * @param int $post_id
	 */
	public function delete_stats( $post_id ) {
		global $wpdb;
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->wpjm_stats} WHERE post_id = %d", $post_id ) );
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
	private function init_hooks() {
		add_action( 'wp_ajax_job_manager_log_stat', [ $this, 'ajax_log_stat' ] );
		add_action( 'wp_ajax_nopriv_job_manager_log_stat', [ $this, 'ajax_log_stat' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_stats_scripts' ] );
	}

	/**
	 * Log multiple stats in one go. Triggered in an ajax call.
	 *
	 * @return bool
	 */
	public function ajax_log_stat() {
		if ( ! wp_doing_ajax() ) {
			return false;
		}

		$post_data = stripslashes_deep( $_POST );

		if ( ! isset( $post_data['_ajax_nonce'] ) || ! wp_verify_nonce( $post_data['_ajax_nonce'], 'ajax-nonce' ) ) {
			return false;
		}

		$stats_json = $post_data['stats'] ?? '[]';
		$stats      = json_decode( $stats_json, true );

		if ( empty( $stats ) ) {
			return false;
		}

		$errors           = [];
		$registered_stats = $this->get_registered_stats();

		foreach ( $stats as $stat_data ) {
			$post_id = isset( $stat_data['post_id'] ) ? absint( $stat_data['post_id'] ) : 0;

			if ( empty( $post_id ) ) {
				$errors[] = [ 'missing post_id', $stat_data ];
				continue;
			}

			if ( ! \WP_Job_Manager_Post_Types::PT_LISTING === get_post_type( $post_id ) ) {
				$errors[] = [ 'cannot record', $stat_data, $post_id ];
				continue;
			}

			if ( ! isset( $stat_data['name'] ) ) {
				$errors[] = [ 'no name', $stat_data ];
				continue;
			}

			$stat_name = trim( strtolower( $stat_data['name'] ) );

			if ( empty( $registered_stats[ $stat_name ] ) ) {
				$errors[] = [ 'not registered', $stat_data ];
				continue;
			}

			$log_callback = $registered_stats[ $stat_name ]['log_callback'] ?? [ $this, 'log_stat' ];
			call_user_func( $log_callback, trim( $stat_name ), [ 'post_id' => $post_id ] );
		}

		if ( ! empty( $errors ) ) {
			return false;
		}

		return true;
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
	 * Register any frontend scripts for job listings.
	 *
	 * @access private
	 */
	public function maybe_enqueue_stats_scripts() {

		\WP_Job_Manager::register_script(
			'wp-job-manager-stats',
			'js/wpjm-stats.js',
			[
				'wp-dom-ready',
				'wp-hooks',
			],
			true
		);

		global $post;

		if ( is_wpjm_job_listing() ) {
			$this->enqueue_stats_script( 'listing', $post->ID );
		}

		if ( $this->page_has_jobs_shortcode( $post ) ) {
			$this->enqueue_stats_script( 'jobs', $post->ID );
		}

	}

	/**
	 * Register scripts for given screen.
	 *
	 * @param string $page Which page.
	 * @param int    $post_id Which id.
	 *
	 * @return void
	 */
	private function enqueue_stats_script( $page = 'listing', $post_id = 0 ) {

		$script_data = [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'ajaxNonce' => wp_create_nonce( 'ajax-nonce' ),
			'postId'    => $post_id,
			'stats'     => $this->get_stats_for_ajax( $post_id, $page ),
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
				Job_Listing_Stats::VIEW              => [
					'type'   => 'action',
					'action' => 'page-load',
					'page'   => 'listing',
				],
				Job_Listing_Stats::VIEW_UNIQUE       => [
					'type'   => 'action',
					'action' => 'page-load',
					'unique' => true,
					'page'   => 'listing',
				],
				Job_Listing_Stats::APPLY_CLICK       => [
					'type'   => 'domEvent',
					'args'   => [
						'element' => 'input.application_button',
						'event'   => 'click',
					],
					'unique' => true,
					'page'   => 'listing',
				],
				'search_view'                        => [
					'type'   => 'action',
					'action' => 'page-load',
					'page'   => 'jobs',
				],
				'search_view_unique'                 => [
					'type'   => 'action',
					'action' => 'page-load',
					'page'   => 'jobs',
					'unique' => true,
				],
				Job_Listing_Stats::SEARCH_IMPRESSION => [
					'type' => 'impression',
					'args' => [
						'container' => 'ul.job_listings',
						'item'      => 'li.job_listing',
					],
					'page' => 'jobs',
				],
			]
		);
	}

	/**
	 * Determine what stats should be added to the kind of page the user is viewing.
	 *
	 * @param int    $post_id Optional post id.
	 * @param string $page The page in question.
	 *
	 * @return array
	 */
	private function get_stats_for_ajax( $post_id = 0, $page = 'listing' ) {
		$ajax_stats = [];
		foreach ( $this->get_registered_stats() as $stat_name => $stat_data ) {
			if ( $page !== $stat_data['page'] ) {
				continue;
			}

			$stat_ajax = [
				'name'    => $stat_name,
				'post_id' => $post_id,
				'type'    => $stat_data['type'] ?? '',
				'action'  => $stat_data['action'] ?? '',
				'args'    => $stat_data['args'] ?? '',
			];

			if ( ! empty( $stat_data['unique'] ) ) {
				$unique_callback         = $stat_data['unique_callback'] ?? [ $this, 'unique_by_post_id' ];
				$stat_ajax['unique_key'] = call_user_func( $unique_callback, $stat_name, $post_id );
			}

			$ajax_stats[] = $stat_ajax;
		}

		return $ajax_stats;
	}

	/**
	 * Derive unique key by post id.
	 *
	 * @access private
	 *
	 * @param string $stat_name Name.
	 * @param int    $post_id Post id.
	 *
	 * @return string
	 */
	public function unique_by_post_id( $stat_name, $post_id ) {
		return $stat_name . '_' . $post_id;
	}

	/**
	 * Any page containing a job shortcode is eligible.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return bool
	 */
	public function page_has_jobs_shortcode( $post ) {
		return $post && has_shortcode( $post->post_content, 'jobs' );
	}
}
