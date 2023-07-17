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
 * @since $$next-version$$
 */
class WP_Job_Manager_Promoted_Jobs_Admin {
	/**
	 * The URL for the promote job form on WPJobManager.com.
	 */
	private const PROMOTE_JOB_FORM_PATH = '/promote-job/';

	/**
	 * The action in wp-admin where we'll redirect the user to the promote job form.
	 */
	private const PROMOTE_JOB_ACTION = 'wpjm-promote-job-listing';

	/**
	 * The action in wp-admin where we'll deactivate a promotion to a job.
	 */
	private const DEACTIVATE_PROMOTION_ACTION = 'wpjm-deactivate-promotion';

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  $$next-version$$
	 */
	private static $instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @return self Main instance.
	 * @since  $$next-version$$
	 * @static
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
		add_filter( 'manage_edit-job_listing_columns', [ $this, 'promoted_jobs_columns' ] );
		add_action( 'manage_job_listing_posts_custom_column', [ $this, 'promoted_jobs_custom_columns' ], 2 );
		add_action( 'admin_action_' . self::PROMOTE_JOB_ACTION, [ $this, 'handle_promote_job' ] );
		add_action( 'admin_action_' . self::DEACTIVATE_PROMOTION_ACTION, [ $this, 'handle_deactivate_promotion' ] );
		add_action( 'admin_footer', [ $this, 'promoted_jobs_admin_footer' ] );
		add_action( 'wpjm_job_listing_bulk_actions', [ $this, 'add_action_notice' ] );
	}

	/**
	 * Add a column to the job listings admin page.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function promoted_jobs_columns( $columns ) {
		$columns['promoted_jobs'] = __( 'Promote', 'wp-job-manager' );

		return $columns;
	}

	/**
	 * Handle request to deactivate promotion for a job.
	 */
	public function handle_deactivate_promotion() {
		$post_id = absint( $_GET['post_id'] ?? 0 );
		check_admin_referer( self::DEACTIVATE_PROMOTION_ACTION . '-' . $post_id );

		if ( ! $post_id ) {
			wp_die( esc_html__( 'No job listing ID provided for deactivation of the promotion.', 'wp-job-manager' ), '', [ 'back_link' => true ] );
		}

		if ( ! $this->can_promote_job( $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to deactivate the promotion for this job listing.', 'wp-job-manager' ), '', [ 'back_link' => true ] );
		}

		$this->deactivate_promotion( $post_id );

		wp_safe_redirect(
			add_query_arg(
				[
					'action_performed' => 'promotion_deactivated',
					'handled_jobs'     => [ $post_id ],
					'post_type'        => 'job_listing',
					'action'           => false,
					'post_id'          => false,
					'_wpnonce'         => false,
				],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Check if a user can promote a job. They must have permission to manage job listings and the post type must be a published job_listing.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool Returns true if they can promote a job.
	 */
	private function can_promote_job( int $post_id ) {
		if ( 'job_listing' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			return false;
		}

		return current_user_can( 'manage_job_listings', $post_id );
	}

	/**
	 * Add feedback notice after successful deactivation.
	 *
	 * @param array $actions_handled
	 *
	 * @return array
	 */
	public function add_action_notice( $actions_handled ) {
		$actions_handled['promotion_deactivated'] = [
			// translators: Placeholder (%s) is the name of the job listing affected.
			'notice' => __( 'Promotion for %s deactivated', 'wp-job-manager' ),
		];

		return $actions_handled;
	}

	/**
	 * Handle the action to promote a job listing, validating as well as redirecting to the form on WPJobManager.com.
	 *
	 * @return void
	 */
	public function handle_promote_job() {
		$post_id = absint( $_GET['post_id'] ?? 0 );
		check_admin_referer( self::PROMOTE_JOB_ACTION . '-' . $post_id );
		if ( ! $post_id ) {
			wp_die( esc_html__( 'No job listing ID provided for promotion.', 'wp-job-manager' ), '', [ 'back_link' => true ] );
		}
		if ( ! $this->can_promote_job( $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to promote this job listing.', 'wp-job-manager' ), '', [ 'back_link' => true ] );
		}
		$current_user = get_current_user_id();
		$site_trust   = WP_Job_Manager_Site_Trust_Token::instance();
		$token        = $site_trust->generate( 'user', $current_user );
		if ( is_wp_error( $token ) ) {
			wp_die( esc_html( $token->get_error_message() ) );
		}
		$site_url            = home_url( '', 'https' );
		$job_endpoint_url    = rest_url( '/wpjm-internal/v1/promoted-jobs/' . $post_id, 'https' );
		$job_endpoint_url    = substr( $job_endpoint_url, strlen( $site_url ) );
		$verify_endpoint_url = rest_url( '/wpjm-internal/v1/promoted-jobs/verify-token', 'https' );
		$verify_endpoint_url = substr( $verify_endpoint_url, strlen( $site_url ) );

		$url = add_query_arg(
			[
				'user_id'             => $current_user,
				'job_id'              => $post_id,
				'job_endpoint_url'    => rawurlencode( $job_endpoint_url ),
				'verify_endpoint_url' => rawurlencode( $verify_endpoint_url ),
				'token'               => $token,
				'site_url'            => rawurlencode( $site_url ),
				'locale'              => get_user_locale( $current_user ),
			],
			WP_Job_Manager_Helper_API::get_wpjmcom_url() . self::PROMOTE_JOB_FORM_PATH
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Check if a job is promoted.
	 *
	 * @param int $post_id
	 *
	 * @return boolean
	 */
	public function is_promoted( $post_id ) {
		$promoted = get_post_meta( $post_id, '_promoted', true );

		return (bool) $promoted;
	}

	/**
	 * Deactivate promotion for a job.
	 *
	 * @param int $post_id
	 *
	 * @return boolean
	 */
	public function deactivate_promotion( $post_id ) {
		return update_post_meta( $post_id, '_promoted', 0 );
	}

	/**
	 * Handle display of new column
	 *
	 * @param string $column
	 */
	public function promoted_jobs_custom_columns( $column ) {
		global $post;

		if ( 'promoted_jobs' !== $column ) {
			return;
		}

		if ( ! $this->can_promote_job( $post->ID ) ) {
			return;
		}

		$base_url    = admin_url( 'admin.php' );
		$promote_url = add_query_arg(
			[
				'action'   => self::PROMOTE_JOB_ACTION,
				'post_id'  => $post->ID,
				'_wpnonce' => wp_create_nonce( self::PROMOTE_JOB_ACTION . '-' . $post->ID ),
			],
			$base_url
		);

		if ( $this->is_promoted( $post->ID ) ) {
			$deactivate_action_link = add_query_arg(
				[
					'action'   => self::DEACTIVATE_PROMOTION_ACTION,
					'post_id'  => $post->ID,
					'_wpnonce' => wp_create_nonce( self::DEACTIVATE_PROMOTION_ACTION . '-' . $post->ID ),
				],
				$base_url
			);
			echo '
			<span class="jm-promoted__status-promoted">' . esc_html__( 'Promoted', 'wp-job-manager' ) . '</span>
			<div class="row-actions">
				<a href="' . esc_url( $promote_url ) . '" class="jm-promoted__edit" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Edit', 'wp-job-manager' ) . '</a>
				|
				<a class="jm-promoted__deactivate delete" href="#" data-href="' . esc_url( $deactivate_action_link ) . '">' . esc_html__( 'Deactivate', 'wp-job-manager' ) . '</a>
			</div>
			';
		} else {
			echo '<button class="promote_job button button-primary" data-href=' . esc_url( $promote_url ) . '>' . esc_html__( 'Promote', 'wp-job-manager' ) . '</button>';
		}
	}

	/**
	 * Store the promoted jobs template from wpjobmanager.com.
	 *
	 * @return string
	 */
	public function get_promote_jobs_template() {
		$promote_template_option_name          = 'promote-jobs-template_' . get_user_locale();
		$promote_jobs_template_next_check_name = '_promote-jobs-template_next_check_' . get_user_locale();
		$promote_template                      = get_option( $promote_template_option_name, false );
		$promote_jobs_template_next_check      = get_option( $promote_jobs_template_next_check_name );

		if ( ! $promote_jobs_template_next_check || $promote_jobs_template_next_check < time() ) {
			$check_for_updated_template = true;
		}

		if ( $check_for_updated_template ) {
			$response = wp_safe_remote_get( 'https://wpjobmanager.com/wp-json/promoted-jobs/v1/assets/promote-dialog/?lang=' . get_user_locale() );
			if (
				is_wp_error( $response )
				|| 200 !== wp_remote_retrieve_response_code( $response )
				|| empty( wp_remote_retrieve_body( $response ) )
			) {
				update_option( $promote_jobs_template_next_check_name, time() + MINUTE_IN_SECONDS * 5, false );
				return $promote_template;
			} else {
				$assets           = json_decode( wp_remote_retrieve_body( $response ), true );
				$promote_template = $assets['assets'][0]['content'];
				update_option( $promote_template_option_name, $promote_template, false );
				update_option( $promote_jobs_template_next_check_name, time() + HOUR_IN_SECONDS, false );
			}
		}
		if ( ! is_wp_error( $promote_template ) ) {
			return $promote_template;
		}
	}

	/**
	 * Output the promote jobs template
	 *
	 * @return void
	 */
	public function promoted_jobs_admin_footer() {
		$screen = get_current_screen();
		if ( 'edit-job_listing' !== $screen->id ) {
			return;
		}
		?>
		<template id="promote-job-template">
			<?php echo $this->get_promote_jobs_template(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</template>
		<dialog class="wpjm-dialog" id="promote-dialog"></dialog>

		<dialog class="wpjm-dialog deactivate-dialog" id="deactivate-dialog">
			<form class="dialog deactivate-button" method="dialog">
				<button class="dialog-close" type="submit">X</button>
			</form>
			<h2 class="deactivate-modal-heading">
				<?php esc_html_e( 'Are you sure you want to deactivate promotion for this job?', 'wp-job-manager' ); ?>
			</h2>
			<p>
				<?php esc_html_e( 'If you still have time until the promotion expires, this time will be lost and the promotion of the job will be canceled.', 'wp-job-manager' ); ?>
			</p>
			<form method="dialog">
				<div class="deactivate-action promote-buttons-group">
					<button class="dialog-close button button-secondary" type="submit">
						<?php esc_html_e( 'Cancel', 'wp-job-manager' ); ?>
					</button>
					<a class="deactivate-promotion button button-primary">
						<?php esc_html_e( 'Deactivate', 'wp-job-manager' ); ?>
					</a>
				</div>
			</form>
		</dialog>
		<?php
	}

}

WP_Job_Manager_Promoted_Jobs_Admin::instance();
