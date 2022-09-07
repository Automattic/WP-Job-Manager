<?php
/**
 * File containing the class WP_Job_Manager_Shortcodes.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the shortcodes for WP Job Manager.
 *
 * @since 1.0.0
 */
class WP_Job_Manager_Shortcodes {

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
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.26.0
	 */
	private static $instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.26.0
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
		add_action( 'wp', [ $this, 'handle_redirects' ] );
		add_action( 'wp', [ $this, 'shortcode_action_handler' ] );
		add_action( 'job_manager_job_dashboard_content_edit', [ $this, 'edit_job' ] );
		add_action( 'job_manager_job_filters_end', [ $this, 'job_filter_job_types' ], 20 );
		add_action( 'job_manager_job_filters_end', [ $this, 'job_filter_results' ], 30 );
		add_action( 'job_manager_output_jobs_no_results', [ $this, 'output_no_results' ] );
		add_shortcode( 'submit_job_form', [ $this, 'submit_job_form' ] );
		add_shortcode( 'job_dashboard', [ $this, 'job_dashboard' ] );
		add_shortcode( 'jobs', [ $this, 'output_jobs' ] );
		add_shortcode( 'job', [ $this, 'output_job' ] );
		add_shortcode( 'job_summary', [ $this, 'output_job_summary' ] );
		add_shortcode( 'job_apply', [ $this, 'output_job_apply' ] );

		add_filter( 'paginate_links', [ $this, 'filter_paginate_links' ], 10, 1 );
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
	 * Handle redirects
	 */
	public function handle_redirects() {
		$submit_job_form_page_id = get_option( 'job_manager_submit_job_form_page_id' );

		if ( ! is_user_logged_in() || ! is_page( $submit_job_form_page_id ) ||
			 // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
			( ! empty( $_REQUEST['job_id'] ) && job_manager_user_can_edit_job( intval( $_REQUEST['job_id'] ) ) )
		) {
			return;
		}

		$submission_limit = get_option( 'job_manager_submission_limit' );
		$job_count        = job_manager_count_user_job_listings();

		if (
			$submit_job_form_page_id
			&& $submission_limit
			&& $job_count >= $submission_limit
		) {
			$employer_dashboard_page_id = get_option( 'job_manager_job_dashboard_page_id' );
			if ( $employer_dashboard_page_id ) {
				$redirect_url = get_permalink( $employer_dashboard_page_id );
			} else {
				$redirect_url = home_url( '/' );
			}

			/**
			 * Filter on the URL visitors will be redirected upon exceeding submission limit.
			 *
			 * @since 1.35.2
			 *
			 * @param string $redirect_url     URL to redirect when user has exceeded submission limit.
			 * @param int    $submission_limit Maximum number of listings a user can submit.
			 * @param int    $job_count        Number of job listings the user has submitted.
			 */
			$redirect_url = apply_filters(
				'job_manager_redirect_url_exceeded_listing_limit',
				$redirect_url,
				$submission_limit,
				$job_count
			);

			if ( $redirect_url ) {
				wp_safe_redirect( esc_url( $redirect_url ) );

				exit;
			}
		}
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
	 * Shows the job submission form.
	 *
	 * @param array $atts
	 * @return string|null
	 */
	public function submit_job_form( $atts = [] ) {
		return $GLOBALS['job_manager']->forms->get_form( 'submit-job', $atts );
	}

	/**
	 * Handles actions on job dashboard.
	 *
	 * @throws Exception On action handling error.
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
				if ( empty( $job ) || 'job_listing' !== $job->post_type || ! job_manager_user_can_edit_job( $job_id ) ) {
					throw new Exception( __( 'Invalid ID', 'wp-job-manager' ) );
				}

				switch ( $action ) {
					case 'mark_filled':
						// Check status.
						if ( 1 === intval( $job->_filled ) ) {
							throw new Exception( __( 'This position has already been filled', 'wp-job-manager' ) );
						}

						// Update.
						update_post_meta( $job_id, '_filled', 1 );

						// Message.
						// translators: Placeholder %s is the job listing title.
						$this->job_dashboard_message = '<div class="job-manager-message">' . wp_kses_post( sprintf( __( '%s has been filled', 'wp-job-manager' ), wpjm_get_the_job_title( $job ) ) ) . '</div>';
						break;
					case 'mark_not_filled':
						// Check status.
						if ( 1 !== intval( $job->_filled ) ) {
							throw new Exception( __( 'This position is not filled', 'wp-job-manager' ) );
						}

						// Update.
						update_post_meta( $job_id, '_filled', 0 );

						// Message.
						// translators: Placeholder %s is the job listing title.
						$this->job_dashboard_message = '<div class="job-manager-message">' . wp_kses_post( sprintf( __( '%s has been marked as not filled', 'wp-job-manager' ), wpjm_get_the_job_title( $job ) ) ) . '</div>';
						break;
					case 'delete':
						// Trash it.
						wp_trash_post( $job_id );

						// Message.
						// translators: Placeholder %s is the job listing title.
						$this->job_dashboard_message = '<div class="job-manager-message">' . wp_kses_post( sprintf( __( '%s has been deleted', 'wp-job-manager' ), wpjm_get_the_job_title( $job ) ) ) . '</div>';

						break;
					case 'duplicate':
						if ( ! job_manager_get_permalink( 'submit_job_form' ) ) {
							throw new Exception( __( 'Missing submission page.', 'wp-job-manager' ) );
						}

						$new_job_id = job_manager_duplicate_listing( $job_id );

						if ( $new_job_id ) {
							wp_safe_redirect( add_query_arg( [ 'job_id' => absint( $new_job_id ) ], job_manager_get_permalink( 'submit_job_form' ) ) );
							exit;
						}

						break;
					case 'relist':
					case 'continue':
						if ( ! job_manager_get_permalink( 'submit_job_form' ) ) {
							throw new Exception( __( 'Missing submission page.', 'wp-job-manager' ) );
						}

						// redirect to post page.
						wp_safe_redirect( add_query_arg( [ 'job_id' => absint( $job_id ) ], job_manager_get_permalink( 'submit_job_form' ) ) );
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
					$this->job_dashboard_message = '<div class="job-manager-message">' . $success_message . '</div>';
				}
			} catch ( Exception $e ) {
				$this->job_dashboard_message = '<div class="job-manager-error">' . wp_kses_post( $e->getMessage() ) . '</div>';
			}
		}
	}

	/**
	 * Check if a job is listed on the current user's job dashboard page.
	 *
	 * @param WP_Post $job Job post object.
	 *
	 * @return bool
	 */
	private function is_job_available_on_dashboard( WP_Post $job ) {
		// Check cache of currently displayed job dashboard IDs first to avoid lots of queries.
		if ( isset( $this->job_dashboard_job_ids ) && in_array( (int) $job->ID, $this->job_dashboard_job_ids, true ) ) {
			return true;
		}

		$args           = $this->get_job_dashboard_query_args();
		$args['p']      = $job->ID;
		$args['fields'] = 'ids';

		$query = new WP_Query( $args );

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
			'post_type'           => 'job_listing',
			'post_status'         => [ 'publish', 'expired', 'pending', 'draft', 'preview' ],
			'ignore_sticky_posts' => 1,
			'posts_per_page'      => $posts_per_page,
			'orderby'             => 'date',
			'order'               => 'desc',
			'author'              => get_current_user_id(),
		];

		if ( $posts_per_page > 0 ) {
			$job_dashboard_args['offset'] = ( max( 1, get_query_var( 'paged' ) ) - 1 ) * $posts_per_page;
		}

		/**
		 * Customize the query that is used to get jobs on the job dashboard.
		 *
		 * @since 1.0.0
		 *
		 * @param array $job_dashboard_args Arguments to pass to WP_Query.
		 */
		return apply_filters( 'job_manager_get_dashboard_jobs_args', $job_dashboard_args );
	}

	/**
	 * Handles shortcode which lists the logged in user's jobs.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function job_dashboard( $atts ) {
		if ( ! is_user_logged_in() ) {
			ob_start();
			get_job_manager_template( 'job-dashboard-login.php' );
			return ob_get_clean();
		}

		$new_atts       = shortcode_atts(
			[
				'posts_per_page' => '25',
			],
			$atts
		);
		$posts_per_page = $new_atts['posts_per_page'];

		wp_enqueue_script( 'wp-job-manager-job-dashboard' );

		ob_start();

		// If doing an action, show conditional content if needed....
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
		$action = isset( $_REQUEST['action'] ) ? sanitize_title( wp_unslash( $_REQUEST['action'] ) ) : false;
		if ( ! empty( $action ) ) {
			// Show alternative content if a plugin wants to.
			if ( has_action( 'job_manager_job_dashboard_content_' . $action ) ) {
				do_action( 'job_manager_job_dashboard_content_' . $action, $atts );

				return ob_get_clean();
			}
		}

		// ....If not show the job dashboard.
		$jobs = new WP_Query( $this->get_job_dashboard_query_args( $posts_per_page ) );

		// Cache IDs for access check later on.
		$this->job_dashboard_job_ids = wp_list_pluck( $jobs->posts, 'ID' );

		echo wp_kses_post( $this->job_dashboard_message );

		$job_dashboard_columns = apply_filters(
			'job_manager_job_dashboard_columns',
			[
				'job_title' => __( 'Title', 'wp-job-manager' ),
				'filled'    => __( 'Filled?', 'wp-job-manager' ),
				'date'      => __( 'Date Posted', 'wp-job-manager' ),
				'expires'   => __( 'Listing Expires', 'wp-job-manager' ),
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
	 * Get the actions available to the user for a job listing on the job dashboard page.
	 *
	 * @param WP_Post $job The job post object.
	 *
	 * @return array
	 */
	public function get_job_actions( $job ) {
		if (
			! get_current_user_id()
			|| ! $job instanceof WP_Post
			|| 'job_listing' !== $job->post_type
			|| ! $this->is_job_available_on_dashboard( $job )
		) {
			return [];
		}

		$base_nonce_action_name = 'job_manager_my_job_actions';

		$actions = [];
		switch ( $job->post_status ) {
			case 'publish':
				if ( WP_Job_Manager_Post_Types::job_is_editable( $job->ID ) ) {
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
				if ( WP_Job_Manager_Post_Types::job_is_editable( $job->ID ) ) {
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
		 * @param WP_Post $job     Job post object.
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
	 * @return string
	 */
	public function filter_paginate_links( $link ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used for comparison only.
		if ( $this->is_job_dashboard_page() && isset( $_GET['action'] ) && in_array( $_GET['action'], [ 'mark_filled', 'mark_not_filled' ], true ) ) {
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
	 * Lists all job listings.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function output_jobs( $atts ) {
		ob_start();

		if ( ! job_manager_user_can_browse_job_listings() ) {
			get_job_manager_template_part( 'access-denied', 'browse-job_listings' );
			return ob_get_clean();
		}

		$atts = shortcode_atts(
			apply_filters(
				'job_manager_output_jobs_defaults',
				[
					'per_page'                  => get_option( 'job_manager_per_page' ),
					'orderby'                   => 'featured',
					'order'                     => 'DESC',

					// Filters + cats.
					'show_filters'              => true,
					'show_categories'           => true,
					'show_category_multiselect' => get_option( 'job_manager_enable_default_category_multiselect', false ),
					'show_pagination'           => 'pagination' === get_option( 'job_manager_job_listing_pagination_type' ) ? true : false,
					'show_more'                 => 'load_more' === get_option( 'job_manager_job_listing_pagination_type' ) ? true : false,

					// Limit what jobs are shown based on category, post status, and type.
					'categories'                => '',
					'job_types'                 => '',
					'post_status'               => '',
					'featured'                  => null, // True to show only featured, false to hide featured, leave null to show both.
					'filled'                    => null, // True to show only filled, false to hide filled, leave null to show both/use the settings.
					'remote_position'           => null, // True to show only remote, false to hide remote, leave null to show both.

					// Default values for filters.
					'location'                  => '',
					'keywords'                  => '',
					'selected_category'         => '',
					'selected_job_types'        => implode( ',', array_values( get_job_listing_types( 'id=>slug' ) ) ),
				]
			),
			$atts
		);

		if ( ! get_option( 'job_manager_enable_categories' ) ) {
			$atts['show_categories'] = false;
		}

		// String and bool handling.
		$atts['show_filters']              = $this->string_to_bool( $atts['show_filters'] );
		$atts['show_categories']           = $this->string_to_bool( $atts['show_categories'] );
		$atts['show_category_multiselect'] = $this->string_to_bool( $atts['show_category_multiselect'] );
		$atts['show_more']                 = $this->string_to_bool( $atts['show_more'] );
		$atts['show_pagination']           = $this->string_to_bool( $atts['show_pagination'] );

		if ( ! is_null( $atts['featured'] ) ) {
			$atts['featured'] = ( is_bool( $atts['featured'] ) && $atts['featured'] ) || in_array( $atts['featured'], [ 1, '1', 'true', 'yes' ], true );
		}

		if ( ! is_null( $atts['filled'] ) ) {
			$atts['filled'] = ( is_bool( $atts['filled'] ) && $atts['filled'] ) || in_array( $atts['filled'], [ 1, '1', 'true', 'yes' ], true );
		}

		if ( ! is_null( $atts['remote_position'] ) ) {
			$atts['remote_position'] = ( is_bool( $atts['remote_position'] ) && $atts['remote_position'] ) || in_array( $atts['remote_position'], [ 1, '1', 'true', 'yes' ], true );
		}

		// By default, use client-side state to populate form fields.
		$disable_client_state = false;

		// Get keywords, location, category and type from querystring if set.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Input is used safely.
		if ( ! empty( $_GET['search_keywords'] ) ) {
			$atts['keywords']     = sanitize_text_field( wp_unslash( $_GET['search_keywords'] ) );
			$disable_client_state = true;
		}
		if ( ! empty( $_GET['search_location'] ) ) {
			$atts['location']     = sanitize_text_field( wp_unslash( $_GET['search_location'] ) );
			$disable_client_state = true;
		}
		if ( ! empty( $_GET['search_category'] ) ) {
			$atts['selected_category'] = sanitize_text_field( wp_unslash( $_GET['search_category'] ) );
			$disable_client_state      = true;
		}
		if ( ! empty( $_GET['search_job_type'] ) ) {
			$atts['selected_job_types'] = sanitize_text_field( wp_unslash( $_GET['search_job_type'] ) );
			$disable_client_state       = true;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Array handling.
		$atts['categories']         = is_array( $atts['categories'] ) ? $atts['categories'] : array_filter( array_map( 'trim', explode( ',', $atts['categories'] ) ) );
		$atts['selected_category']  = is_array( $atts['selected_category'] ) ? $atts['selected_category'] : array_filter( array_map( 'trim', explode( ',', $atts['selected_category'] ) ) );
		$atts['job_types']          = is_array( $atts['job_types'] ) ? $atts['job_types'] : array_filter( array_map( 'trim', explode( ',', $atts['job_types'] ) ) );
		$atts['post_status']        = is_array( $atts['post_status'] ) ? $atts['post_status'] : array_filter( array_map( 'trim', explode( ',', $atts['post_status'] ) ) );
		$atts['selected_job_types'] = is_array( $atts['selected_job_types'] ) ? $atts['selected_job_types'] : array_filter( array_map( 'trim', explode( ',', $atts['selected_job_types'] ) ) );

		// Normalize field for categories.
		if ( ! empty( $atts['selected_category'] ) ) {
			foreach ( $atts['selected_category'] as $cat_index => $category ) {
				if ( ! is_numeric( $category ) ) {
					$term = get_term_by( 'slug', $category, 'job_listing_category' );

					if ( $term ) {
						$atts['selected_category'][ $cat_index ] = $term->term_id;
					}
				}
			}
		}

		$data_attributes = [
			'location'                   => $atts['location'],
			'keywords'                   => $atts['keywords'],
			'show_filters'               => $atts['show_filters'] ? 'true' : 'false',
			'show_pagination'            => $atts['show_pagination'] ? 'true' : 'false',
			'per_page'                   => $atts['per_page'],
			'orderby'                    => $atts['orderby'],
			'order'                      => $atts['order'],
			'categories'                 => implode( ',', $atts['categories'] ),
			'disable-form-state-storage' => $disable_client_state,
		];

		if ( $atts['show_filters'] ) {
			get_job_manager_template(
				'job-filters.php',
				[
					'per_page'                  => $atts['per_page'],
					'orderby'                   => $atts['orderby'],
					'order'                     => $atts['order'],
					'show_categories'           => $atts['show_categories'],
					'categories'                => $atts['categories'],
					'selected_category'         => $atts['selected_category'],
					'job_types'                 => $atts['job_types'],
					'atts'                      => $atts,
					'location'                  => $atts['location'],
					'remote_position'           => $atts['remote_position'],
					'keywords'                  => $atts['keywords'],
					'selected_job_types'        => $atts['selected_job_types'],
					'show_category_multiselect' => $atts['show_category_multiselect'],
				]
			);

			get_job_manager_template( 'job-listings-start.php' );
			get_job_manager_template( 'job-listings-end.php' );

			if ( ! $atts['show_pagination'] && $atts['show_more'] ) {
				echo '<a class="load_more_jobs" href="#" style="display:none;"><strong>' . esc_html__( 'Load more listings', 'wp-job-manager' ) . '</strong></a>';
			}
		} else {
			$jobs = get_job_listings(
				apply_filters(
					'job_manager_output_jobs_args',
					[
						'search_location'   => $atts['location'],
						'search_keywords'   => $atts['keywords'],
						'post_status'       => $atts['post_status'],
						'search_categories' => $atts['categories'],
						'job_types'         => $atts['job_types'],
						'orderby'           => $atts['orderby'],
						'order'             => $atts['order'],
						'posts_per_page'    => $atts['per_page'],
						'featured'          => $atts['featured'],
						'filled'            => $atts['filled'],
						'remote_position'   => $atts['remote_position'],
					]
				)
			);

			if ( ! empty( $atts['job_types'] ) ) {
				$data_attributes['job_types'] = implode( ',', $atts['job_types'] );
			}

			if ( $jobs->have_posts() ) {
				get_job_manager_template( 'job-listings-start.php' );
				while ( $jobs->have_posts() ) {
					$jobs->the_post();
					get_job_manager_template_part( 'content', 'job_listing' );
				}
				get_job_manager_template( 'job-listings-end.php' );
				if ( $jobs->found_posts > $atts['per_page'] && $atts['show_more'] ) {
					wp_enqueue_script( 'wp-job-manager-ajax-filters' );
					if ( $atts['show_pagination'] ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output.
						echo get_job_listing_pagination( $jobs->max_num_pages );
					} else {
						echo '<a class="load_more_jobs" href="#"><strong>' . esc_html__( 'Load more listings', 'wp-job-manager' ) . '</strong></a>';
					}
				}
			} else {
				do_action( 'job_manager_output_jobs_no_results' );
			}
			wp_reset_postdata();
		}

		$data_attributes_string = '';
		if ( ! is_null( $atts['featured'] ) ) {
			$data_attributes['featured'] = $atts['featured'] ? 'true' : 'false';
		}
		if ( ! is_null( $atts['filled'] ) ) {
			$data_attributes['filled'] = $atts['filled'] ? 'true' : 'false';
		}
		if ( ! is_null( $atts['remote_position'] ) ) {
			$data_attributes['remote_position'] = $atts['remote_position'] ? 'true' : 'false';
		}
		if ( ! empty( $atts['post_status'] ) ) {
			$data_attributes['post_status'] = implode( ',', $atts['post_status'] );
		}

		$data_attributes['post_id'] = isset( $GLOBALS['post'] ) ? $GLOBALS['post']->ID : 0;

		/**
		 * Pass additional data to the job listings <div> wrapper.
		 *
		 * @since 1.34.0
		 *
		 * @param array $data_attributes {
		 *     Key => Value array of data attributes to pass.
		 *
		 *     @type string $$key Value to pass as a data attribute.
		 * }
		 * @param array $atts            Attributes for the shortcode.
		 */
		$data_attributes = apply_filters( 'job_manager_jobs_shortcode_data_attributes', $data_attributes, $atts );

		foreach ( $data_attributes as $key => $value ) {
			$data_attributes_string .= 'data-' . esc_attr( $key ) . '="' . esc_attr( $value ) . '" ';
		}

		$job_listings_output = apply_filters( 'job_manager_job_listings_output', ob_get_clean() );

		return '<div class="job_listings" ' . $data_attributes_string . '>' . $job_listings_output . '</div>';
	}

	/**
	 * Displays some content when no results were found.
	 */
	public function output_no_results() {
		get_job_manager_template( 'content-no-jobs-found.php' );
	}

	/**
	 * Gets string as a bool.
	 *
	 * @param  string $value
	 * @return bool
	 */
	public function string_to_bool( $value ) {
		return ( is_bool( $value ) && $value ) || in_array( $value, [ 1, '1', 'true', 'yes' ], true );
	}

	/**
	 * Shows job types.
	 *
	 * @param  array $atts
	 */
	public function job_filter_job_types( $atts ) {
		$job_types          = is_array( $atts['job_types'] ) ? $atts['job_types'] : array_filter( array_map( 'trim', explode( ',', $atts['job_types'] ) ) );
		$selected_job_types = is_array( $atts['selected_job_types'] ) ? $atts['selected_job_types'] : array_filter( array_map( 'trim', explode( ',', $atts['selected_job_types'] ) ) );

		get_job_manager_template(
			'job-filter-job-types.php',
			[
				'job_types'          => $job_types,
				'atts'               => $atts,
				'selected_job_types' => $selected_job_types,
			]
		);
	}

	/**
	 * Shows results div.
	 */
	public function job_filter_results() {
		echo '<div class="showing_jobs"></div>';
	}

	/**
	 * Shows a single job.
	 *
	 * @param array $atts
	 * @return string|null
	 */
	public function output_job( $atts ) {
		$atts = shortcode_atts(
			[
				'id' => '',
			],
			$atts
		);

		if ( ! $atts['id'] ) {
			return null;
		}

		ob_start();

		$args = [
			'post_type'   => 'job_listing',
			'post_status' => 'publish',
			'p'           => $atts['id'],
		];

		$jobs = new WP_Query( $args );

		if ( $jobs->have_posts() ) {
			while ( $jobs->have_posts() ) {
				$jobs->the_post();
				echo '<h1>' . wp_kses_post( wpjm_get_the_job_title() ) . '</h1>';
				get_job_manager_template_part( 'content-single', 'job_listing' );
			}
		}

		wp_reset_postdata();

		return '<div class="job_shortcode single_job_listing">' . ob_get_clean() . '</div>';
	}

	/**
	 * Handles the Job Summary shortcode.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function output_job_summary( $atts ) {
		$atts = shortcode_atts(
			[
				'id'       => '',
				'width'    => '250px',
				'align'    => 'left',
				'featured' => null, // True to show only featured, false to hide featured, leave null to show both (when leaving out id).
				'limit'    => 1,
			],
			$atts
		);

		ob_start();

		$args = [
			'post_type'   => 'job_listing',
			'post_status' => 'publish',
		];

		if ( ! $atts['id'] ) {
			$args['posts_per_page'] = $atts['limit'];
			$args['orderby']        = 'rand';
			if ( ! is_null( $atts['featured'] ) ) {
				$args['meta_query'] = [
					[
						'key'     => '_featured',
						'value'   => '1',
						'compare' => $atts['featured'] ? '=' : '!=',
					],
				];
			}
		} else {
			$args['p'] = absint( $atts['id'] );
		}

		$jobs = new WP_Query( $args );

		if ( $jobs->have_posts() ) {
			while ( $jobs->have_posts() ) {
				$jobs->the_post();
				$width = $atts['width'] ? $atts['width'] : 'auto';
				echo '<div class="job_summary_shortcode align' . esc_attr( $atts['align'] ) . '" style="width: ' . esc_attr( $width ) . '">';
				get_job_manager_template_part( 'content-summary', 'job_listing' );
				echo '</div>';
			}
		}

		wp_reset_postdata();

		return ob_get_clean();
	}

	/**
	 * Shows the application area.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function output_job_apply( $atts ) {
		$new_atts = shortcode_atts(
			[
				'id' => '',
			],
			$atts
		);
		$id       = $new_atts['id'];

		ob_start();

		$args = [
			'post_type'   => 'job_listing',
			'post_status' => 'publish',
		];

		if ( ! $id ) {
			return '';
		} else {
			$args['p'] = absint( $id );
		}

		$jobs = new WP_Query( $args );

		if ( $jobs->have_posts() ) {
			while ( $jobs->have_posts() ) {
				$jobs->the_post();
				$apply = get_the_job_application_method();
				do_action( 'job_manager_before_job_apply_' . absint( $id ) );
				if ( apply_filters( 'job_manager_show_job_apply_' . absint( $id ), true ) ) {
					echo '<div class="job-manager-application-wrapper">';
					do_action( 'job_manager_application_details_' . $apply->type, $apply );
					echo '</div>';
				}
				do_action( 'job_manager_after_job_apply_' . absint( $id ) );
			}
			wp_reset_postdata();
		}

		return ob_get_clean();
	}
}

WP_Job_Manager_Shortcodes::instance();
