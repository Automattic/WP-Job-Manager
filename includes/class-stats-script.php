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
			wp_send_json_error( __( 'Invalid request.', 'wp-job-manager' ), 403 );
			return;
		}

		if ( $this->is_rate_limited() ) {
			wp_send_json_error( __( 'Too many requests.', 'wp-job-manager' ), 429 );
			return;
		}

		$stats_raw = $post_data['stats'] ?? '[]';
		if ( ! is_string( $stats_raw ) ) {
			wp_send_json_error( __( 'Invalid payload.', 'wp-job-manager' ), 400 );
			return;
		}
		$stats = json_decode( $stats_raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $stats ) ) {
			wp_send_json_error( __( 'Invalid payload.', 'wp-job-manager' ), 400 );
			return;
		}
		if ( empty( $stats ) ) {
			wp_send_json_error( __( 'No stats to log.', 'wp-job-manager' ), 400 );
			return;
		}

		$stats = array_slice( $stats, 0, self::AJAX_BATCH_LIMIT );

		$today = gmdate( 'Y-m-d' );
		$stats = array_map(
			function ( $stat ) use ( $today ) {
				if ( ! is_array( $stat ) ) {
					return null;
				}
				// Canonicalize types early so every downstream step (validity check,
				// dedup keying, DB write) operates on consistent scalars. Without this,
				// non-string name/group or non-integer post_id can trip strlen()/md5()
				// later and turn the endpoint into a trivial DoS.
				$stat['post_id'] = absint( $stat['post_id'] ?? 0 );
				$stat['name']    = is_string( $stat['name'] ?? null ) ? $stat['name'] : '';
				$stat['group']   = is_string( $stat['group'] ?? null ) ? $stat['group'] : '';
				$stat['count']   = 1;
				$stat['date']    = $today;
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

		$stats = $this->filter_by_post_validity( $stats, $post_id );
		$stats = $this->filter_server_unique( $stats );

		if ( empty( $stats ) ) {
			wp_send_json_success();
			return;
		}

		if ( ! Stats::instance()->batch_log_stats( $stats ) ) {
			wp_send_json_error( __( 'Unable to log stats.', 'wp-job-manager' ), 500 );
			return;
		}
		wp_send_json_success();
	}

	/**
	 * Validate stat post_ids against expected post type, status, and request scope.
	 *
	 * Two checks are applied per stat row:
	 *
	 * 1. Scope: for non-impression stats the stat's post_id must equal the request-level
	 *    post_id (the page the client rendered, bound to the nonce). This stops a client
	 *    with a valid nonce for one published post from logging `job_view`, `job_apply_click`,
	 *    etc. for a different post.
	 *
	 * 2. Validity: the target post must be published, and listing-scope stats
	 *    (page=listing or type=impression) must target a `job_listing`.
	 *
	 * Impression stats (type=impression) are exempt from the scope check because the
	 * `[jobs]` shortcode's JS client posts per-listing post_ids that legitimately differ
	 * from the page post_id. Residual risk: on a `[jobs]` page a client with a valid page
	 * nonce can still submit impressions for any published job_listing, not just ones
	 * visible on the page. Rate limiting + dedup constrain the volume; the dashboard
	 * footnote discloses that public-page stats are approximate. Eliminating this would
	 * require server-side impression recording, which is incompatible with full-page
	 * caching.
	 *
	 * @param array $stats            Stat rows.
	 * @param int   $request_post_id  The page-level post_id the nonce is bound to.
	 * @return array Filtered stat rows.
	 */
	private function filter_by_post_validity( $stats, $request_post_id ) {
		$listing_stat_names    = [];
		$impression_stat_names = [];
		foreach ( $this->get_registered_stats() as $name => $def ) {
			$is_listing_page = ( $def['page'] ?? '' ) === 'listing';
			$is_impression   = ( $def['type'] ?? '' ) === 'impression';
			if ( $is_listing_page || $is_impression ) {
				$listing_stat_names[] = $name;
			}
			if ( $is_impression ) {
				$impression_stat_names[] = $name;
			}
		}

		$cache = [];
		return array_filter(
			$stats,
			function ( $stat ) use ( &$cache, $listing_stat_names, $impression_stat_names, $request_post_id ) {
				$post_id = absint( $stat['post_id'] ?? 0 );
				if ( ! $post_id ) {
					return false;
				}

				$name             = $stat['name'] ?? '';
				$is_impression    = in_array( $name, $impression_stat_names, true );
				$requires_listing = in_array( $name, $listing_stat_names, true );

				// Scope: non-impression stats must target the post the nonce was issued for.
				if ( ! $is_impression && $post_id !== $request_post_id ) {
					return false;
				}

				$cache_key = $post_id . '|' . ( $requires_listing ? 'L' : 'P' );

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
	 * Server-side per-client dedup for stats flagged `unique` in their definition.
	 *
	 * Authoritative: reads the `unique` flag from registered-stat definitions rather
	 * than guessing from the stat name. Covers `*_unique` stats and any other stat
	 * (e.g. `job_apply_click`) registered with `unique => true`.
	 *
	 * Uses a 24-hour sliding window starting at the first observed click. A calendar-day
	 * (midnight UTC) boundary would let a visitor near the boundary re-count within
	 * minutes of their first click.
	 *
	 * @param array $stats Stat rows.
	 * @return array Filtered stat rows.
	 */
	private function filter_server_unique( $stats ) {
		$client = $this->get_client_ip();
		if ( '' === $client ) {
			return $stats;
		}

		$unique_stat_names = [];
		foreach ( $this->get_registered_stats() as $name => $def ) {
			if ( ! empty( $def['unique'] ) ) {
				$unique_stat_names[] = $name;
			}
		}
		if ( empty( $unique_stat_names ) ) {
			return $stats;
		}

		return array_filter(
			$stats,
			function ( $stat ) use ( $client, $unique_stat_names ) {
				$name = $stat['name'] ?? '';
				if ( ! in_array( $name, $unique_stat_names, true ) ) {
					return true;
				}
				$key = 'wpjm_u_' . md5( $client . '|' . $name . '|' . ( $stat['post_id'] ?? 0 ) );
				if ( get_transient( $key ) ) {
					return false;
				}
				set_transient( $key, 1, DAY_IN_SECONDS );
				return true;
			}
		);
	}

	/**
	 * Soft per-client rate limit. Not an auth boundary — absorbs drive-by abuse.
	 *
	 * Fixed-window implementation: the transient stores `{count, start}` and the
	 * window resets once `now - start >= MINUTE_IN_SECONDS`. Using just an integer
	 * counter with `set_transient(..., MINUTE_IN_SECONDS)` would refresh the TTL
	 * on every call, so a continuous trickle of requests would never let the
	 * window expire and would eventually falsely block legitimately active users.
	 *
	 * @return bool
	 */
	private function is_rate_limited() {
		$client = $this->get_client_ip();
		if ( '' === $client ) {
			return false;
		}
		$key    = 'wpjm_rl_' . md5( $client );
		$now    = time();
		$window = get_transient( $key );

		if (
			! is_array( $window )
			|| ! isset( $window['count'], $window['start'] )
			|| $now - (int) $window['start'] >= MINUTE_IN_SECONDS
		) {
			$window = [
				'count' => 0,
				'start' => $now,
			];
		}

		if ( (int) $window['count'] >= self::AJAX_RATE_LIMIT ) {
			return true;
		}

		$window['count']++;
		set_transient( $key, $window, MINUTE_IN_SECONDS );
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

		// If a page is both a single job_listing and hosts the [jobs] shortcode,
		// only the first call wins — double-localizing would overwrite the first
		// nonce and break listing-page stats.
		if ( wp_script_is( 'wp-job-manager-stats', 'enqueued' ) ) {
			return;
		}

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
