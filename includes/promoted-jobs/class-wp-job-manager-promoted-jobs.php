<?php
/**
 * File containing the class WP_Job_Manager_Promoted_Jobs.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles promoted jobs functionality.
 *
 * @since 1.42.0
 */
class WP_Job_Manager_Promoted_Jobs {

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.42.0
	 */
	private static $instance = null;

	/**
	 * Name for post meta that identify a `job_listing` post as promoted.
	 *
	 * @var string
	 */
	const PROMOTED_META_KEY = '_promoted';

	/**
	 * Option that caches the number of active promoted jobs.
	 *
	 * @var string
	 */
	const PROMOTED_JOB_TRACK_OPTION = 'jm_promoted_job_count';

	/**
	 * The status handler.
	 *
	 * @var WP_Job_Manager_Promoted_Jobs_Status_Handler
	 */
	private WP_Job_Manager_Promoted_Jobs_Status_Handler $status_handler;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.42.0
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'init' ] );
	}

	/**
	 * Initializes Promoted Jobs feature.
	 *
	 * @return void
	 */
	public function init() {
		$this->include_dependencies();
		$this->register_post_metas();
		$this->status_handler = new WP_Job_Manager_Promoted_Jobs_Status_Handler();
		$this->status_handler->init();

		( new WP_Job_Manager_Promoted_Jobs_API( $this->status_handler ) )->init();

		add_filter( 'pre_delete_post', [ $this, 'cancel_promoted_jobs_deletion' ], 10, 2 );
	}

	/**
	 * Includes promoted jobs dependencies.
	 *
	 * @return void
	 */
	private function include_dependencies() {
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/promoted-jobs/class-wp-job-manager-promoted-jobs-api.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/promoted-jobs/class-wp-job-manager-promoted-jobs-notifications.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/promoted-jobs/class-wp-job-manager-promoted-jobs-status-handler.php';
	}

	/**
	 * Register post metas.
	 *
	 * @return void
	 */
	private function register_post_metas() {
		register_post_meta(
			\WP_Job_Manager_Post_Types::PT_LISTING,
			self::PROMOTED_META_KEY,
			[
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			]
		);
	}

	/**
	 * Cancel promoted jobs deletion.
	 *
	 * @internal
	 *
	 * @param WP_Post|false|null $delete
	 * @param WP_Post            $post
	 *
	 * @return WP_Post|false|null
	 */
	public function cancel_promoted_jobs_deletion( $delete, $post ) {
		if ( ! self::is_promoted( $post->ID ) ) {
			return $delete;
		}

		return false;
	}

	/**
	 * Check if a job is promoted.
	 *
	 * @param int $post_id
	 *
	 * @return boolean
	 */
	public static function is_promoted( $post_id ) {
		if ( \WP_Job_Manager_Post_Types::PT_LISTING !== get_post_type( $post_id ) ) {
			return false;
		}

		return '1' === get_post_meta( $post_id, self::PROMOTED_META_KEY, true );
	}

	/**
	 * Update promotion.
	 *
	 * @param int  $post_id
	 * @param bool $promoted
	 *
	 * @return boolean
	 */
	public static function update_promotion( $post_id, $promoted ) {
		if ( ! self::pre_change_promotion( $post_id ) ) {
			return false;
		}

		return update_post_meta( $post_id, self::PROMOTED_META_KEY, $promoted ? '1' : '0' );
	}

	/**
	 * Deactivate promotion for a job.
	 *
	 * @param int $post_id
	 *
	 * @return boolean
	 */
	public static function deactivate_promotion( $post_id ) {
		if ( ! self::pre_change_promotion( $post_id ) ) {
			return false;
		}

		return delete_post_meta( $post_id, self::PROMOTED_META_KEY );
	}

	/**
	 * Run necessary things before promotion change.
	 * - Check post type.
	 * - Clear job counter option.
	 *
	 * @param int $post_id
	 *
	 * @return boolean Whether pre change passed correctly.
	 */
	private static function pre_change_promotion( $post_id ) {
		if ( \WP_Job_Manager_Post_Types::PT_LISTING !== get_post_type( $post_id ) ) {
			return false;
		}

		delete_option( self::PROMOTED_JOB_TRACK_OPTION );

		return true;
	}

	/**
	 * Get the number of active promoted jobs filtering with specific args.
	 *
	 * @internal
	 *
	 * @param array $args Extra args for the counter query.
	 *
	 * @return int
	 */
	public static function query_promoted_jobs_count( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'post_type'      => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Returns promoted jobs only which should be a small number.
					[
						'key'     => self::PROMOTED_META_KEY,
						'value'   => '1',
						'compare' => '=',
					],
				],
			]
		);

		$promoted_jobs = new WP_Query( $args );

		return $promoted_jobs->found_posts;
	}

	/**
	 * Get the number of active promoted jobs.
	 *
	 * @return int
	 */
	public static function get_promoted_jobs_count() {
		$promoted_jobs_count = get_option( self::PROMOTED_JOB_TRACK_OPTION );

		if ( false === $promoted_jobs_count ) {
			$promoted_jobs_count = self::query_promoted_jobs_count();

			update_option( self::PROMOTED_JOB_TRACK_OPTION, $promoted_jobs_count );
		}

		return (int) $promoted_jobs_count;
	}
}

WP_Job_Manager_Promoted_Jobs::instance();
