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

	const         CACHE_GROUP = 'wpjm_stats';

	const DEFAULT_LOG_STAT_ARGS = [
		'group'        => '',
		'post_id'      => 0,
		'increment_by' => 1,
	];

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
		add_action( 'wp_enqueue_scripts', [ $this, 'frontend_scripts' ] );
		add_action( 'wp_ajax_job_manager_log_stat', [ $this, 'maybe_log_stat_ajax' ] );
		add_action( 'wp_ajax_nopriv_job_manager_log_stat', [ $this, 'maybe_log_stat_ajax' ] );
		add_action( 'wpjm_stats_frontend_scripts', [ $this, 'job_listing_frontend_scripts' ], 10, 1 );
		add_action( 'wpjm_stats_frontend_scripts', [ $this, 'jobs_frontend_scripts' ], 10, 1 );
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
		if ( ! wp_doing_ajax() ) {
			return;
		}

		$post_data = stripslashes_deep( $_POST );

		if ( ! isset( $post_data['_ajax_nonce'] ) || ! wp_verify_nonce( $post_data['_ajax_nonce'], 'ajax-nonce' ) ) {
			return;
		}

		$stats_json = $post_data['stats'] ?? '[]';
		$stats      = json_decode( $stats_json, ARRAY_A );

		if ( empty( $stats ) ) {
			return;
		}

		$errors           = [];
		$registered_stats = $this->get_registered_stats();

		foreach ( $stats as $stat_data ) {
			$post_id = isset( $stat_data['post_id'] ) ? absint( $stat_data['post_id'] ) : 0;

			if ( empty( $post_id ) ) {
				$errors[] = [ 'missing post_id', $stat_data ];
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $this->can_record_stats_for_post( $post ) ) {
				$errors[] = [ 'cannot record', $stat_data, $post ];
				continue;
			}

			if ( ! isset( $stat_data['name'] ) ) {
				$errors[] = [ 'no name', $stat_data ];
				continue;
			}

			$stat_name = trim( strtolower( $stat_data['name'] ) );

			if ( ! in_array( $stat_name, $this->get_registered_stat_names(), true ) ) {
				$errors[] = [ 'not registered', $stat_data ];
				continue;
			}

			$log_callback = $registered_stats[ $stat_name ]['log_callback'] ?? [ $this, 'log_stat' ];
			call_user_func( $log_callback, trim( $stat_name ), [ 'post_id' => $post_id ] );
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
	 * Register any frontend JS scripts.
	 *
	 * @return void
	 */
	public function frontend_scripts() {
		$post_id = absint( get_queried_object_id() );
		$post    = get_post( $post_id );

		if ( 0 === $post_id ) {
			return;
		}

		/**
		 * Delegate registration to dedicated hooks per-screen.
		 */
		do_action( 'wpjm_stats_frontend_scripts', $post );
	}

	/**
	 * Register any frontend scripts for job listings.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return void
	 */
	public function job_listing_frontend_scripts( $post ) {
		$post_type = $post->post_type;
		if ( \WP_Job_Manager_Post_Types::PT_LISTING !== $post_type ) {
			return;
		}

		$this->register_frontend_scripts_for_screen( 'listing', $post->ID );
	}

	/**
	 * Register any frontend scripts for a page containing 'jobs' shortcode.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return void
	 */
	public function jobs_frontend_scripts( $post ) {
		if ( $this->page_has_jobs_shortcode( $post ) ) {
			$this->register_frontend_scripts_for_screen( 'jobs', $post->ID );
		}
	}

	/**
	 * Check that a certain post/page is eligible for getting recorded stats.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return bool
	 */
	private function can_record_stats_for_post( $post ) {
		$can_record = false;
		if ( $this->page_has_jobs_shortcode( $post ) ) {
			return $this->filter_can_record_stats_for_post( true, $post );
		} elseif ( \WP_Job_Manager_Post_Types::PT_LISTING === $post->post_type ) {
			return $this->filter_can_record_stats_for_post( true, $post );
		}

		return $this->filter_can_record_stats_for_post( false, $post );
	}

	/**
	 * Run filter.
	 *
	 * @param bool     $can_record Can record.
	 * @param \WP_Post $post       The post.
	 *
	 * @return bool
	 */
	private function filter_can_record_stats_for_post( $can_record, $post ) {
		return (bool) apply_filters( 'wpjm_stats_can_record_stats_for_post', $can_record, $post );
	}

	/**
	 * Register scripts for given screen.
	 *
	 * @param string $page    Which page.
	 * @param int    $post_id Which id.
	 * @return void
	 */
	private function register_frontend_scripts_for_screen( $page = 'listing', $post_id = 0 ) {
		\WP_Job_Manager::register_script(
			'wp-job-manager-stats',
			'js/wpjm-stats.js',
			[
				'wp-dom-ready',
				'wp-hooks',
			],
			true
		);

		$script_data = [
			'ajax_url'     => admin_url( 'admin-ajax.php' ),
			'ajax_nonce'   => wp_create_nonce( 'ajax-nonce' ),
			'post_id'      => $post_id,
			'stats_to_log' => $this->get_stats_for_ajax( $post_id, $page ),
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
				'job_listing_view'                 => [
					'log_callback' => [ $this, 'log_stat' ], // Example of overriding how we log this.
					'trigger'      => 'page-load',
					'page'         => 'listing',
				],
				'job_listing_view_unique'          => [
					'unique'          => true,
					'unique_callback' => [ $this, 'unique_by_post_id' ],
					'trigger'         => 'page-load',
					'page'            => 'listing',
				],
				'job_listing_apply_button_clicked' => [
					'trigger'         => 'apply-button-clicked',
					'element'         => 'input.application_button',
					'event'           => 'click',
					'unique'          => true,
					'unique_callback' => [ $this, 'unique_by_post_id' ],
					'page'            => 'listing',
				],
				'jobs_view'                        => [
					'trigger' => 'page-load',
					'page'    => 'jobs',
				],
				'jobs_view_unique'                 => [
					'trigger'         => 'page-load',
					'page'            => 'jobs',
					'unique'          => true,
					'unique_callback' => [ $this, 'unique_by_post_id' ],
				],
				// New style of declaration, a stat that relies on calling a custom js func.
				'job_listing_impressions'          => [
					'trigger'         => 'job-listing-impression',
					'unique'          => true,
					'js_callback'     => 'WPJMStats.initListingImpression',
					'unique_callback' => [ $this, 'unique_by_post_id' ],
					'page'            => 'jobs',
				],
			]
		);
	}

	/**
	 * Determine what stats should be added to the kind of page the user is viewing.
	 *
	 * @param int    $post_id Optional post id.
	 * @param string $page    The page in question.
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
				'name'        => $stat_name,
				'post_id'     => $post_id,
				'trigger'     => $stat_data['trigger'] ?? '',
				'element'     => $stat_data['element'] ?? '',
				'event'       => $stat_data['event'] ?? '',
				'js_callback' => $stat_data['js_callback'] ?? null,
			];

			if ( ! empty( $stat_data['unique'] ) ) {
				$unique_callback         = $stat_data['unique_callback'];
				$stat_ajax['unique_key'] = call_user_func( $unique_callback, $stat_name, $post_id );
			}

			$ajax_stats[] = $stat_ajax;
		}

		return $ajax_stats;
	}

	/**
	 * Derive unique key by post id.
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
		return is_page() && has_shortcode( $post->post_content, 'jobs' );
	}
}
