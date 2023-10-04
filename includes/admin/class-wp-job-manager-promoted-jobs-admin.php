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
	 * @since  1.42.0
	 */
	private static $instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @return self Main instance.
	 * @since  1.42.0
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
		add_action( 'job_manager_admin_after_job_title', [ $this, 'add_promoted_badge' ] );
		add_action( 'admin_action_' . self::PROMOTE_JOB_ACTION, [ $this, 'handle_promote_job' ] );
		add_action( 'admin_action_' . self::DEACTIVATE_PROMOTION_ACTION, [ $this, 'handle_deactivate_promotion' ] );
		add_action( 'admin_footer', [ $this, 'promoted_jobs_admin_footer' ] );
		add_action( 'wpjm_job_listing_bulk_actions', [ $this, 'add_action_notice' ] );
		add_action( 'wpjm_admin_notices', [ $this, 'maybe_add_promoted_jobs_notice' ] );
		add_action( 'wpjm_admin_notices', [ $this, 'maybe_add_trash_notice' ] );
		add_action( 'post_row_actions', [ $this, 'remove_delete_from_promoted_jobs' ], 10, 2 );
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

		if ( ! $this->can_manage_job_promotion( $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to deactivate the promotion for this job listing.', 'wp-job-manager' ), '', [ 'back_link' => true ] );
		}

		WP_Job_Manager_Promoted_Jobs::deactivate_promotion( $post_id );

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
	 * Add promoted badge to promoted job listings.
	 *
	 * @internal
	 *
	 * @param \WP_Post $post        The post object.
	 */
	public function add_promoted_badge( $post ) {
		if (
			is_null( $post )
			|| 'job_listing' !== $post->post_type
			|| ! WP_Job_Manager_Promoted_Jobs::is_promoted( $post->ID )
		) {
			return;
		}

		$title        = '';
		$status_class = '';

		if ( 'publish' !== $post->post_status ) {
			$title        = __( 'Your job is promoted and being exposed through API but it\'s not published in your site. You can fix it by publishing it again or deactivating the promotion.', 'wp-job-manager' );
			$status_class = 'job_manager_admin_badge--not_published';
		} else {
			$title = __( 'This job has been promoted to external job boards.', 'wp-job-manager' );
		}

		echo '<span class="job_manager_admin_badge job_manager_admin_badge--promoted ' . esc_attr( $status_class ) . ' tips" title="' . esc_attr( $title ) . '" data-tip="' . esc_attr( $title ) . '">' . esc_html__( 'Promoted', 'wp-job-manager' ) . '</span>';
	}

	/**
	 * Check if a user can manage job promotion. They must have permission to manage job listings.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool Returns true if they can promote a job.
	 */
	private function can_manage_job_promotion( int $post_id ) {
		if ( 'job_listing' !== get_post_type( $post_id ) ) {
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

		$is_editing = WP_Job_Manager_Promoted_Jobs::is_promoted( $post_id );
		$can_manage = $this->can_manage_job_promotion( $post_id );
		if ( $is_editing ) {
			if ( ! $can_manage ) {
				wp_die( esc_html__( 'You do not have permission to edit this job listing.', 'wp-job-manager' ), '', [ 'back_link' => true ] );
			}
		} else {
			if ( ! $can_manage || 'publish' !== get_post_status( $post_id ) ) {
				wp_die( esc_html__( 'You do not have permission to promote this job listing.', 'wp-job-manager' ), '', [ 'back_link' => true ] );
			}
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

		WP_Job_Manager_Promoted_Jobs_Status_Handler::initialize_defaults();

		// Make sure the job contains the promoted job meta to be listed in the feed.
		if ( ! $is_editing ) {
			WP_Job_Manager_Promoted_Jobs::update_promotion( $post_id, false );
		}

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
	 * Handle display of new column
	 *
	 * @param string $column
	 */
	public function promoted_jobs_custom_columns( $column ) {
		global $post;

		if ( 'promoted_jobs' !== $column ) {
			return;
		}

		if ( ! $this->can_manage_job_promotion( $post->ID ) ) {
			return;
		}

		$promote_url = self::get_promote_url( $post->ID );

		if ( WP_Job_Manager_Promoted_Jobs::is_promoted( $post->ID ) ) {
			$deactivate_action_link = self::get_deactivate_url( $post->ID );
			echo '
			<span class="jm-promoted__status-promoted">' . esc_html__( 'Promoted', 'wp-job-manager' ) . '</span>
			<div class="row-actions">
				<a href="' . esc_url( $promote_url ) . '" class="jm-promoted__edit" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Manage', 'wp-job-manager' ) . '</a>
				|
				<a class="jm-promoted__deactivate delete" href="#" data-href="' . esc_url( $deactivate_action_link ) . '">' . esc_html__( 'Deactivate', 'wp-job-manager' ) . '</a>
			</div>
			';
		} elseif ( 'publish' === $post->post_status ) {
			echo '<button class="promote_job button button-primary" data-href=' . esc_url( $promote_url ) . '>' . esc_html__( 'Promote', 'wp-job-manager' ) . '</button>';
		} else {
			$title = __( 'The job needs to be published in order to be promoted.', 'wp-job-manager' );
			echo '<button type="button" class="button button-primary tips disabled" aria-disabled="true" title="' . esc_attr( $title ) . '" data-tip="' . esc_attr( $title ) . '">' . esc_html__( 'Promote', 'wp-job-manager' ) . '</button>';
		}
	}

	/**
	 * Get the promote URL.
	 *
	 * @param int|string $post_id Post ID placeholder string.
	 *
	 * @return string
	 */
	public static function get_promote_url( $post_id ) {
		return add_query_arg(
			[
				'action'   => self::PROMOTE_JOB_ACTION,
				'post_id'  => $post_id,
				'_wpnonce' => wp_create_nonce( self::PROMOTE_JOB_ACTION . '-' . $post_id ),
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Get the deactivate URL.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	public static function get_deactivate_url( $post_id ) {
		return add_query_arg(
			[
				'action'   => self::DEACTIVATE_PROMOTION_ACTION,
				'post_id'  => $post_id,
				'_wpnonce' => wp_create_nonce( self::DEACTIVATE_PROMOTION_ACTION . '-' . $post_id ),
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Store the promoted jobs template from wpjobmanager.com.
	 *
	 * @return string
	 */
	public function get_promote_jobs_template() {
		$locale                          = get_user_locale();
		$promote_template_transient_name = 'jm_promote-jobs-template_' . $locale;
		$promote_template                = get_transient( $promote_template_transient_name );

		if ( false !== $promote_template ) {
			return $promote_template;
		}

		$url      = WP_Job_Manager_Helper_API::get_wpjmcom_url() . '/wp-json/promoted-jobs/v1/assets/promote-dialog/?lang=' . $locale;
		$response = wp_safe_remote_get( $url );
		$fallback = '
			<div>
				<br />
				<slot name="buttons" class="promote-buttons-group"></slot>
			</div>
		';

		if (
			is_wp_error( $response )
			|| 200 !== wp_remote_retrieve_response_code( $response )
			|| empty( wp_remote_retrieve_body( $response ) )
		) {
			return $fallback;
		}

		$assets = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $assets['assets'][0]['content'] ) ) {
			return $fallback;
		}

		$template = $assets['assets'][0]['content'];

		// Persist in a transient.
		set_transient( $promote_template_transient_name, $template, DAY_IN_SECONDS );

		return $template;
	}

	/**
	 * Output the promote jobs template
	 *
	 * @return void
	 */
	public function promoted_jobs_admin_footer() {
		$screen = get_current_screen();

		if ( in_array( $screen->id, [ 'edit-job_listing', 'job_listing' ], true ) ) { // Job listing and job editor.
			?>
			<template id="promote-job-template">
				<?php echo $this->get_promote_jobs_template(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</template>
			<dialog class="wpjm-dialog" id="promote-dialog"></dialog>
			<?php
		}

		if ( 'edit-job_listing' === $screen->id ) { // Job listing.
			?>
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

	/**
	 * Add a notice to the job listing admin page if the user has promoted jobs.
	 *
	 * @internal
	 *
	 * @param array $notices Notices to filter on.
	 *
	 * @return array
	 */
	public function maybe_add_promoted_jobs_notice( $notices ) {
		if ( WP_Job_Manager_Promoted_Jobs::get_promoted_jobs_count() === 0 ) {
			return $notices;
		}

		$notices['has-promoted-job'] = [
			'type'       => 'user',
			'level'      => 'info',
			'heading'    => __( 'Congratulations! Your first job has been successfully promoted.', 'wp-job-manager' ),
			'message'    => __( 'To manage your promoted job, use the <em>Edit</em> and <em>Deactivate</em> links beside the job listing under the <strong>Promote</strong> column. Unpublishing a job listing will not deactivate the promotion.', 'wp-job-manager' ),
			'conditions' => [
				[
					'type'    => 'screens',
					'screens' => [ 'edit-job_listing' ],
				],
			],
		];

		return $notices;
	}

	/**
	 * Add a notice to the job listing admin page if there are promoted jobs on trash.
	 *
	 * @internal
	 *
	 * @param array $notices Notices to filter on.
	 *
	 * @return array
	 */
	public function maybe_add_trash_notice( $notices ) {
		$promoted_trash_count = WP_Job_Manager_Promoted_Jobs::query_promoted_jobs_count( [ 'post_status' => 'trash' ] );
		if ( 0 === $promoted_trash_count ) {
			return $notices;
		}

		$trash_url = add_query_arg(
			[
				'post_type'   => 'job_listing',
				'post_status' => 'trash',
			],
			admin_url( 'edit.php' )
		);

		$notices['promoted-job-on-trash'] = [
			'type'        => 'user',
			'dismissible' => false,
			'level'       => 'info',
			'heading'     => __( 'You have promoted jobs in the trash.', 'wp-job-manager' ),
			'message'     => __( 'Trashed jobs are not be available to your applicants. Deactivate the promotion or publish the job again to fix this.', 'wp-job-manager' ),
			'conditions'  => [
				[
					'type'    => 'screens',
					'screens' => [ 'edit-job_listing' ],
				],
			],
			'actions'     => [
				[
					'label' => __( 'Check the trash', 'wp-job-manager' ),
					'url'   => $trash_url,
				],
			],
		];

		return $notices;
	}

	/**
	 * Remove delete link from promoted jobs.
	 * The delete action is also canceled as part of
	 * `WP_Job_Manager_Promoted_Jobs::cancel_promoted_jobs_deletion`.
	 *
	 * @internal
	 *
	 * @param array   $actions
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	public function remove_delete_from_promoted_jobs( $actions, $post ) {
		if ( WP_Job_Manager_Promoted_Jobs::is_promoted( $post->ID ) ) {
			$title = __( 'You need to deactivate the promotion before deleting the job.', 'wp-job-manager' );

			$actions['delete'] = preg_replace(
				'/<a(.*?)>/',
				'<a onclick="return false;" style="opacity:0.3; cursor:help;" title="' . esc_attr( $title ) . '" data-tip="' . esc_attr( $title ) . '" class="tips" $1>',
				$actions['delete'],
				1
			);
		}

		return $actions;
	}
}

WP_Job_Manager_Promoted_Jobs_Admin::instance();
