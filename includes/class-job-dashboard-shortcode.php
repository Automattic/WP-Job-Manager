<?php
/**
 * File containing the class Job_Dashboard_Shortcode.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

use WP_Job_Manager\UI\Notice;
use WP_Job_Manager\UI\UI_Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Job Dashboard Shortcode.
 *
 * @since $$next-version$$
 */
class Job_Dashboard_Shortcode {

	use Singleton;

	/**
	 * Dashboard message.
	 *
	 * @access private
	 * @var string
	 */
	private $job_dashboard_message = '';

	/**
	 * Cache of job post IDs currently displayed on job dashboard.
	 *
	 * @var int[]
	 */
	private $job_dashboard_job_ids;

	/**
	 * Constructor.
	 */
	public function __construct() {

		add_action( 'wp', [ $this, 'shortcode_action_handler' ] );

		add_shortcode( 'job_dashboard', [ $this, 'output_job_dashboard_shortcode' ] );

		add_action( 'job_manager_job_dashboard_content_edit', [ $this, 'edit_job' ] );

		add_filter( 'paginate_links', [ $this, 'filter_paginate_links' ], 10, 1 );

		add_action( 'job_manager_job_dashboard_column_date', [ $this, 'job_dashboard_date_column_expires' ] );
		add_action( 'job_manager_job_dashboard_column_job_title', [ $this, 'job_dashboard_title_column_status' ] );
	}

	/**
	 * Handles shortcode which lists the logged in user's jobs.
	 *
	 * @param array $attrs
	 *
	 * @return string
	 */
	public function output_job_dashboard_shortcode( $attrs ) {
		if ( ! is_user_logged_in() ) {
			ob_start();
			get_job_manager_template( 'job-dashboard-login.php' );

			return ob_get_clean();
		}

		$attrs          = shortcode_atts(
			[
				'posts_per_page' => '25',
			],
			$attrs
		);
		$posts_per_page = $attrs['posts_per_page'];

		\WP_Job_Manager::register_style( 'wp-job-manager-job-dashboard', 'css/job-dashboard.css', [ 'wp-job-manager-ui' ] );
		wp_enqueue_style( 'wp-job-manager-job-dashboard' );
		wp_enqueue_script( 'wp-job-manager-job-dashboard' );

		ob_start();

		// If doing an action, show conditional content if needed....
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
		$action = isset( $_REQUEST['action'] ) ? sanitize_title( wp_unslash( $_REQUEST['action'] ) ) : false;
		if ( ! empty( $action ) ) {
			// Show alternative content if a plugin wants to.
			if ( has_action( 'job_manager_job_dashboard_content_' . $action ) ) {
				do_action( 'job_manager_job_dashboard_content_' . $action, $attrs );

				return ob_get_clean();
			}
		}

		// ....If not show the job dashboard.
		$jobs = new \WP_Query( $this->get_job_dashboard_query_args( $posts_per_page ) );

		// Cache IDs for access check later on.
		$this->job_dashboard_job_ids = wp_list_pluck( $jobs->posts, 'ID' );

		echo '<div class="alignwide">' . wp_kses_post( $this->job_dashboard_message ) . '</div>';

		$job_dashboard_columns = apply_filters(
			'job_manager_job_dashboard_columns',
			[
				'job_title' => __( 'Title', 'wp-job-manager' ),
				'date'      => __( 'Date', 'wp-job-manager' ),
			]
		);

		$job_actions = [];
		foreach ( $jobs->posts as $job ) {
			$job_actions[ $job->ID ] = $this->get_job_actions( $job );
		}

		get_job_manager_template(
			'job-dashboard.php',
			[
				'jobs'                  => $jobs->posts,
				'job_actions'           => $job_actions,
				'max_num_pages'         => $jobs->max_num_pages,
				'job_dashboard_columns' => $job_dashboard_columns,
			]
		);

		return ob_get_clean();
	}

	/**
	 * Handles actions which need to be run before the shortcode e.g. post actions.
	 */
	public function shortcode_action_handler() {
		/**
		 * Determine if the shortcode action handler should run.
		 *
		 * @since 1.35.0
		 *
		 * @param bool $should_run_handler Should the handler run.
		 */
		$should_run_handler = apply_filters( 'job_manager_should_run_shortcode_action_handler', $this->is_job_dashboard_page() );

		if ( $should_run_handler ) {
			$this->job_dashboard_handler();
		}
	}

	/**
	 * Get the actions available to the user for a job listing on the job dashboard page.
	 *
	 * @param \WP_Post $job The job post object.
	 *
	 * @return array
	 */
	public function get_job_actions( $job ) {
		if (
			! get_current_user_id()
			|| ! $job instanceof \WP_Post
			|| \WP_Job_Manager_Post_Types::PT_LISTING !== $job->post_type
			|| ! $this->is_job_available_on_dashboard( $job )
		) {
			return [];
		}

		$base_nonce_action_name = 'job_manager_my_job_actions';

		$actions = [];
		switch ( $job->post_status ) {
			case 'publish':
				if ( \WP_Job_Manager_Post_Types::job_is_editable( $job->ID ) ) {
					$actions['edit'] = [
						'label' => __( 'Edit', 'wp-job-manager' ),
						'nonce' => false,
					];
				}
				if ( is_position_filled( $job ) ) {
					$actions['mark_not_filled'] = [
						'label' => __( 'Mark not filled', 'wp-job-manager' ),
						'nonce' => $base_nonce_action_name,
					];
				} else {
					$actions['mark_filled'] = [
						'label' => __( 'Mark filled', 'wp-job-manager' ),
						'nonce' => $base_nonce_action_name,
					];
				}
				if (
					get_option( 'job_manager_renewal_days' ) > 0
					&& \WP_Job_Manager_Helper_Renewals::job_can_be_renewed( $job )
					&& \WP_Job_Manager_Helper_Renewals::is_wcpl_renew_compatible()
					&& \WP_Job_Manager_Helper_Renewals::is_spl_renew_compatible()
				) {
					$actions['renew'] = [
						'label' => __( 'Renew', 'wp-job-manager' ),
						'nonce' => $base_nonce_action_name,
					];
				}

				$actions['duplicate'] = [
					'label' => __( 'Duplicate', 'wp-job-manager' ),
					'nonce' => $base_nonce_action_name,
				];
				break;
			case 'expired':
				if ( job_manager_get_permalink( 'submit_job_form' ) ) {
					$actions['relist'] = [
						'label' => __( 'Relist', 'wp-job-manager' ),
						'nonce' => $base_nonce_action_name,
					];
				}
				break;
			case 'pending_payment':
			case 'pending':
				if ( \WP_Job_Manager_Post_Types::job_is_editable( $job->ID ) ) {
					$actions['edit'] = [
						'label' => __( 'Edit', 'wp-job-manager' ),
						'nonce' => false,
					];
				}
				break;
			case 'draft':
			case 'preview':
				$actions['continue'] = [
					'label' => __( 'Continue Submission', 'wp-job-manager' ),
					'nonce' => $base_nonce_action_name,
				];
				break;
		}

		$actions['delete'] = [
			'label' => __( 'Delete', 'wp-job-manager' ),
			'nonce' => $base_nonce_action_name,
		];

		/**
		 * Filter the actions available to the current user for a job on the job dashboard page.
		 *
		 * @since 1.0.0
		 *
		 * @param array   $actions Actions to filter.
		 * @param \WP_Post $job Job post object.
		 */
		$actions = apply_filters( 'job_manager_my_job_actions', $actions, $job );

		// For backwards compatibility, convert `nonce => true` to the nonce action name.
		foreach ( $actions as $key => $action ) {
			if ( true === $action['nonce'] ) {
				$actions[ $key ]['nonce'] = $base_nonce_action_name;
			}
		}

		return $actions;
	}

	/**
	 * Filters the url from paginate_links to avoid multiple calls for same action in job dashboard
	 *
	 * @param string $link
	 *
	 * @return string
	 */
	public function filter_paginate_links( $link ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used for comparison only.
		if ( $this->is_job_dashboard_page() && isset( $_GET['action'] ) && in_array(
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used for comparison only.
			$_GET['action'],
			[
				'mark_filled',
				'mark_not_filled',
			],
			true
		) ) {
			return remove_query_arg( [ 'action', 'job_id', '_wpnonce' ], $link );
		}

		return $link;
	}

	/**
	 * Displays edit job form.
	 */
	public function edit_job() {
		global $job_manager;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output should be appropriately escaped in the form generator.
		echo $job_manager->forms->get_form( 'edit-job' );
	}

	/**
	 * Helper function used to check if page is WPJM dashboard page.
	 *
	 * Checks if page has 'job_dashboard' shortcode.
	 *
	 * @access private
	 * @return bool True if page is dashboard page, false otherwise.
	 */
	private function is_job_dashboard_page() {
		global $post;

		if ( is_page() && has_shortcode( $post->post_content, 'job_dashboard' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Handles actions on job dashboard.
	 *
	 * @throws \Exception On action handling error.
	 */
	public function job_dashboard_handler() {
		if (
			! empty( $_REQUEST['action'] )
			&& ! empty( $_REQUEST['job_id'] )
			&& ! empty( $_REQUEST['_wpnonce'] )
		) {

			$job_id = isset( $_REQUEST['job_id'] ) ? absint( $_REQUEST['job_id'] ) : 0;
			$action = sanitize_title( wp_unslash( $_REQUEST['action'] ) );

			$job         = get_post( $job_id );
			$job_actions = $this->get_job_actions( $job );

			if (
				! isset( $job_actions[ $action ] )
				|| empty( $job_actions[ $action ]['nonce'] )
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce should not be modified.
				|| ! wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ), $job_actions[ $action ]['nonce'] )
			) {
				return;
			}

			try {
				if ( empty( $job ) || \WP_Job_Manager_Post_Types::PT_LISTING !== $job->post_type || ! job_manager_user_can_edit_job( $job_id ) ) {
					throw new \Exception( __( 'Invalid ID', 'wp-job-manager' ) );
				}

				switch ( $action ) {
					case 'mark_filled':
						// Check status.
						if ( 1 === intval( $job->_filled ) ) {
							throw new \Exception( __( 'This position has already been filled', 'wp-job-manager' ) );
						}

						// Update.
						update_post_meta( $job_id, '_filled', 1 );

						// Message.
						// translators: Placeholder %s is the job listing title.
						$this->job_dashboard_message = Notice::success( sprintf( __( '%s has been filled', 'wp-job-manager' ), wpjm_get_the_job_title( $job ) ) );
						break;
					case 'mark_not_filled':
						// Check status.
						if ( 1 !== intval( $job->_filled ) ) {
							throw new \Exception( __( 'This position is not filled', 'wp-job-manager' ) );
						}

						// Update.
						update_post_meta( $job_id, '_filled', 0 );

						// Message.
						// translators: Placeholder %s is the job listing title.
						$this->job_dashboard_message = Notice::success( sprintf( __( '%s has been marked as not filled', 'wp-job-manager' ), wpjm_get_the_job_title( $job ) ) );
						break;
					case 'delete':
						// Trash it.
						wp_trash_post( $job_id );

						// Message.
						// translators: Placeholder %s is the job listing title.
						$this->job_dashboard_message = Notice::success( sprintf( __( '%s has been deleted', 'wp-job-manager' ), wpjm_get_the_job_title( $job ) ) );

						break;
					case 'duplicate':
						if ( ! job_manager_get_permalink( 'submit_job_form' ) ) {
							throw new \Exception( __( 'Missing submission page.', 'wp-job-manager' ) );
						}

						$new_job_id = job_manager_duplicate_listing( $job_id );

						if ( $new_job_id ) {
							wp_safe_redirect( add_query_arg( [ 'job_id' => absint( $new_job_id ) ], job_manager_get_permalink( 'submit_job_form' ) ) );
							exit;
						}

						break;
					case 'relist':
					case 'renew':
					case 'continue':
						if ( ! job_manager_get_permalink( 'submit_job_form' ) ) {
							throw new \Exception( __( 'Missing submission page.', 'wp-job-manager' ) );
						}

						$query_args = [
							'job_id' => absint( $job_id ),
							'action' => $action,
						];

						if ( 'renew' === $action ) {
							$query_args['nonce'] = wp_create_nonce( 'job_manager_renew_job_' . $job_id );
						}
						wp_safe_redirect( add_query_arg( $query_args, job_manager_get_permalink( 'submit_job_form' ) ) );
						exit;
					default:
						do_action( 'job_manager_job_dashboard_do_action_' . $action, $job_id );
						break;
				}

				do_action( 'job_manager_my_job_do_action', $action, $job_id );

				/**
				 * Set a success message for a custom dashboard action handler.
				 *
				 * When left empty, no success message will be shown.
				 *
				 * @since 1.31.1
				 *
				 * @param string  $message  Text for the success message. Default: empty string.
				 * @param string  $action   The name of the custom action.
				 * @param int     $job_id   The ID for the job that's been altered.
				 */
				$success_message = apply_filters( 'job_manager_job_dashboard_success_message', '', $action, $job_id );
				if ( $success_message ) {
					$this->job_dashboard_message = Notice::success( $success_message );
				}
			} catch ( Exception $e ) {
				$this->job_dashboard_message = Notice::error( $e->getMessage() );
			}
		}
	}

	/**
	 * Add expiration details to the job dashboard date column.
	 *
	 * @param \WP_Post $job
	 *
	 * @output string
	 */
	public function job_dashboard_date_column_expires( $job ) {
		$expiration = \WP_Job_Manager_Post_Types::instance()->get_job_expiration( $job );

		if ( 'publish' === $job->post_status && ! empty( $expiration ) ) {

			// translators: Placeholder is the expiration date of the job listing.
			echo '<div class="job-expires"><small>' . UI_Elements::rel_time( $expiration, __( 'Expires in %s', 'wp-job-manager' ) ) . '</small></div>';
		}
	}

	/**
	 * Add job status to the job dashboard title column.
	 *
	 * @param \WP_Post $job
	 *
	 * @output string
	 */
	public function job_dashboard_title_column_status( $job ) {

		echo '<div class="job-status">';

		$status = [];

		if ( is_position_filled( $job ) ) {
			$status[] = '<span class="job-status-filled">' . esc_html__( 'Filled', 'wp-job-manager' ) . '</span>';
		}

		if ( is_position_featured( $job ) && 'publish' === $job->post_status ) {
			$status[] = '<span class="job-status-featured">' . esc_html__( 'Featured', 'wp-job-manager' ) . '</span>';
		}

		$status[] = '<span class="job-status-' . esc_attr( $job->post_status ) . '">' . esc_html( get_the_job_status( $job ) ) . '</span>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		echo implode( ', ', $status );

		echo '</div>';
	}

	/**
	 * Check if a job is listed on the current user's job dashboard page.
	 *
	 * @param \WP_Post $job Job post object.
	 *
	 * @return bool
	 */
	private function is_job_available_on_dashboard( \WP_Post $job ) {
		// Check cache of currently displayed job dashboard IDs first to avoid lots of queries.
		if ( isset( $this->job_dashboard_job_ids ) && in_array( (int) $job->ID, $this->job_dashboard_job_ids, true ) ) {
			return true;
		}

		$args           = $this->get_job_dashboard_query_args();
		$args['p']      = $job->ID;
		$args['fields'] = 'ids';

		$query = new \WP_Query( $args );

		return (int) $query->post_count > 0;
	}

	/**
	 * Helper that generates the job dashboard query args.
	 *
	 * @param int $posts_per_page Number of posts per page.
	 *
	 * @return array
	 */
	private function get_job_dashboard_query_args( $posts_per_page = -1 ) {
		$job_dashboard_args = [
			'post_type'           => \WP_Job_Manager_Post_Types::PT_LISTING,
			'post_status'         => [ 'publish', 'expired', 'pending', 'draft', 'preview' ],
			'ignore_sticky_posts' => 1,
			'posts_per_page'      => $posts_per_page,
			'orderby'             => 'date',
			'order'               => 'desc',
			'author'              => get_current_user_id(),
		];

		if ( get_option( 'job_manager_enable_scheduled_listings' ) ) {
			$job_dashboard_args['post_status'][] = 'future';
		}

		if ( $posts_per_page > 0 ) {
			$job_dashboard_args['offset'] = ( max( 1, get_query_var( 'paged' ) ) - 1 ) * $posts_per_page;
		}

		/**
		 * Customize the query that is used to get jobs on the job dashboard.
		 *
		 * @since 1.0.0
		 *
		 * @param array $job_dashboard_args Arguments to pass to \WP_Query.
		 */
		return apply_filters( 'job_manager_get_dashboard_jobs_args', $job_dashboard_args );
	}

}
