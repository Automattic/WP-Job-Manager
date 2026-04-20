<?php
/**
 * File containing the class WP_Job_Manager\Stats_Script
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add and handle frontend script to collect stats.
 *
 * @since 2.3.0
 */
class Stats_Script {
	use Singleton;

	/**
	 * Run any hooks related to stats.
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'wp_ajax_job_manager_log_stat', [ $this, 'ajax_log_stat' ] );
		add_action( 'wp_ajax_nopriv_job_manager_log_stat', [ $this, 'ajax_log_stat' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_stats_scripts' ] );
	}

	/**
	 * Max stats accepted in a single AJAX request.
	 */
	const AJAX_BATCH_LIMIT = 50;

	/**
	 * Requests allowed per client per minute.
	 */
	const AJAX_RATE_LIMIT = 60;

	/**
	 * Log multiple stats in one go. Triggered in an ajax call.
	 *
	 * @return void
	 */
	public function ajax_log_stat() {
		if ( ! wp_doing_ajax() ) {
			return;
		}

		$post_data = stripslashes_deep( $_POST );
		$post_id   = absint( $post_data['post_id'] ?? 0 );

		if (
			! isset( $post_data['_ajax_nonce'] )
			|| ! $post_id
			|| ! wp_verify_nonce( $post_data['_ajax_nonce'], 'wpjm_log_stat_' . $post_id )
		) {
			wp_send_json_error( 'Invalid request.', 403 );
			return;
		}

		if ( $this->is_rate_limited() ) {
			wp_send_json_error( 'Too many requests.', 429 );
			return;
		}

		$stats = json_decode( $post_data['stats'] ?? '[]', true );
		if ( empty( $stats ) || ! is_array( $stats ) ) {
			wp_send_json_error( 'No stats to log.', 400 );
			return;
		}

		$stats = array_slice( $stats, 0, self::AJAX_BATCH_LIMIT );

		$today = gmdate( 'Y-m-d' );
		$stats = array_map(
			function ( $stat ) use ( $today ) {
				if ( ! is_array( $stat ) ) {
					return null;
				}
				$stat['count'] = 1;
				$stat['date']  = $today;
				return $stat;
			},
			$stats
		);
		$stats = array_filter( $stats );

		$registered_stats = $this->get_registered_stat_names();
		$stats            = array_filter(
			$stats,
			function ( $stat ) use ( $registered_stats ) {
				return isset( $stat['name'] ) && in_array( $stat['name'], $registered_stats, true );
			}
		);

		$stats = $this->filter_by_post_validity( $stats );
		$stats = $this->filter_server_unique( $stats );

		if ( empty( $stats ) ) {
			wp_send_json_success();
			return;
		}

		Stats::instance()->batch_log_stats( $stats );
		wp_send_json_success();
	}

	/**
	 * Validate stat post_ids against the expected post type and status.
	 *
	 * Listing-scope stats (page=listing or type=impression) require a published job_listing.
	 * Other stats require any published post.
	 *
	 * @param array $stats Stat rows.
	 * @return array Filtered stat rows.
	 */
	private function filter_by_post_validity( $stats ) {
		$listing_stat_names = [];
		foreach ( $this->get_registered_stats() as $name => $def ) {
			$is_listing_page = ( $def['page'] ?? '' ) === 'listing';
			$is_impression   = ( $def['type'] ?? '' ) === 'impression';
			if ( $is_listing_page || $is_impression ) {
				$listing_stat_names[] = $name;
			}
		}

		$cache = [];
		return array_filter(
			$stats,
			function ( $stat ) use ( &$cache, $listing_stat_names ) {
				$post_id = absint( $stat['post_id'] ?? 0 );
				if ( ! $post_id ) {
					return false;
				}

				$requires_listing = in_array( $stat['name'] ?? '', $listing_stat_names, true );
				$cache_key        = $post_id . '|' . ( $requires_listing ? 'L' : 'P' );

				if ( ! isset( $cache[ $cache_key ] ) ) {
					$status = get_post_status( $post_id );
					$type   = get_post_type( $post_id );
					if ( 'publish' !== $status ) {
						$cache[ $cache_key ] = false;
					} elseif ( $requires_listing && \WP_Job_Manager_Post_Types::PT_LISTING !== $type ) {
						$cache[ $cache_key ] = false;
					} else {
						$cache[ $cache_key ] = true;
					}
				}

				return $cache[ $cache_key ];
			}
		);
	}

	/**
	 * Server-side per-client dedup for "_unique" stats.
	 *
	 * @param array $stats Stat rows.
	 * @return array Filtered stat rows.
	 */
	private function filter_server_unique( $stats ) {
		$client = $this->get_client_ip();
		if ( '' === $client ) {
			return $stats;
		}
		$today = gmdate( 'Y-m-d' );
		$ttl   = strtotime( 'tomorrow UTC' ) - time();
		$ttl   = $ttl > 0 ? $ttl : DAY_IN_SECONDS;

		return array_filter(
			$stats,
			function ( $stat ) use ( $client, $today, $ttl ) {
				$name = $stat['name'] ?? '';
				if ( strlen( $name ) < 7 || '_unique' !== substr( $name, -7 ) ) {
					return true;
				}
				$key = 'wpjm_u_' . md5( $client . '|' . $name . '|' . ( $stat['post_id'] ?? 0 ) . '|' . $today );
				if ( get_transient( $key ) ) {
					return false;
				}
				set_transient( $key, 1, $ttl );
				return true;
			}
		);
	}

	/**
	 * Soft per-client rate limit. Not an auth boundary — absorbs drive-by abuse.
	 *
	 * @return bool
	 */
	private function is_rate_limited() {
		$client = $this->get_client_ip();
		if ( '' === $client ) {
			return false;
		}
		$key   = 'wpjm_rl_' . md5( $client );
		$count = (int) get_transient( $key );
		if ( $count >= self::AJAX_RATE_LIMIT ) {
			return true;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return false;
	}

	/**
	 * Client IP from REMOTE_ADDR only (X-Forwarded-For is spoofable by design here).
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP ) : '';
		return is_string( $ip ) ? $ip : '';
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
			'ajaxNonce' => wp_create_nonce( 'wpjm_log_stat_' . $post_id ),
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
				$unique_callback         = $stat_data['unique_callback'] ?? [ $this, 'get_post_id_unique_key' ];
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
	public function get_post_id_unique_key( $stat_name, $post_id ) {
		return $stat_name . '_' . $post_id;
	}

	/**
	 * Any page containing a job shortcode is eligible.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return bool
	 */
	private function page_has_jobs_shortcode( $post ) {
		return $post && has_shortcode( $post->post_content, 'jobs' );
	}

}
