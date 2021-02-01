<?php
/**
 * Global WP Job Manager functions.
 *
 * New global functions are discouraged whenever possible.
 *
 * @package wp-job-manager
 */

if ( ! function_exists( 'get_job_listings' ) ) :
	/**
	 * Queries job listings with certain criteria and returns them.
	 *
	 * @since 1.0.5
	 * @param string|array|object $args Arguments used to retrieve job listings.
	 * @return WP_Query
	 */
	function get_job_listings( $args = [] ) {
		global $job_manager_keyword;

		$args = wp_parse_args(
			$args,
			[
				'search_location'   => '',
				'search_keywords'   => '',
				'search_categories' => [],
				'job_types'         => [],
				'post_status'       => [],
				'offset'            => 0,
				'posts_per_page'    => 20,
				'orderby'           => 'date',
				'order'             => 'DESC',
				'featured'          => null,
				'filled'            => null,
				'fields'            => 'all',
			]
		);

		/**
		 * Perform actions that need to be done prior to the start of the job listings query.
		 *
		 * @since 1.26.0
		 *
		 * @param array $args Arguments used to retrieve job listings.
		 */
		do_action( 'get_job_listings_init', $args );

		if ( ! empty( $args['post_status'] ) ) {
			$post_status = $args['post_status'];
		} elseif ( 0 === intval( get_option( 'job_manager_hide_expired', get_option( 'job_manager_hide_expired_content', 1 ) ) ) ) {
			$post_status = [ 'publish', 'expired' ];
		} else {
			$post_status = 'publish';
		}

		$query_args = [
			'post_type'              => 'job_listing',
			'post_status'            => $post_status,
			'ignore_sticky_posts'    => 1,
			'offset'                 => absint( $args['offset'] ),
			'posts_per_page'         => intval( $args['posts_per_page'] ),
			'orderby'                => $args['orderby'],
			'order'                  => $args['order'],
			'tax_query'              => [],
			'meta_query'             => [],
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'cache_results'          => false,
			'fields'                 => $args['fields'],
		];

		if ( $args['posts_per_page'] < 0 ) {
			$query_args['no_found_rows'] = true;
		}

		if ( ! empty( $args['search_location'] ) ) {
			$location_meta_keys = [ 'geolocation_formatted_address', '_job_location', 'geolocation_state_long' ];
			$location_search    = [ 'relation' => 'OR' ];
			foreach ( $location_meta_keys as $meta_key ) {
				$location_search[] = [
					'key'     => $meta_key,
					'value'   => $args['search_location'],
					'compare' => 'like',
				];
			}
			$query_args['meta_query'][] = $location_search;
		}

		if ( ! is_null( $args['featured'] ) ) {
			$query_args['meta_query'][] = [
				'key'     => '_featured',
				'value'   => '1',
				'compare' => $args['featured'] ? '=' : '!=',
			];
		}

		if ( ! is_null( $args['filled'] ) || 1 === absint( get_option( 'job_manager_hide_filled_positions' ) ) ) {
			$query_args['meta_query'][] = [
				'key'     => '_filled',
				'value'   => '1',
				'compare' => $args['filled'] ? '=' : '!=',
			];
		}

		if ( ! empty( $args['job_types'] ) ) {
			$query_args['tax_query'][] = [
				'taxonomy' => 'job_listing_type',
				'field'    => 'slug',
				'terms'    => $args['job_types'],
			];
		}

		if ( ! empty( $args['search_categories'] ) ) {
			$field                     = is_numeric( $args['search_categories'][0] ) ? 'term_id' : 'slug';
			$operator                  = 'all' === get_option( 'job_manager_category_filter_type', 'all' ) && count( $args['search_categories'] ) > 1 ? 'AND' : 'IN';
			$query_args['tax_query'][] = [
				'taxonomy'         => 'job_listing_category',
				'field'            => $field,
				'terms'            => array_values( $args['search_categories'] ),
				'include_children' => 'AND' !== $operator,
				'operator'         => $operator,
			];
		}

		if ( 'featured' === $args['orderby'] ) {
			$query_args['orderby'] = [
				'menu_order' => 'ASC',
				'date'       => 'DESC',
				'ID'         => 'DESC',
			];
		}

		if ( 'rand_featured' === $args['orderby'] ) {
			$query_args['orderby'] = [
				'menu_order' => 'ASC',
				'rand'       => 'ASC',
			];
		}

		$job_manager_keyword = sanitize_text_field( $args['search_keywords'] );

		if ( ! empty( $job_manager_keyword ) && strlen( $job_manager_keyword ) >= apply_filters( 'job_manager_get_listings_keyword_length_threshold', 2 ) ) {
			$query_args['s'] = $job_manager_keyword;
			add_filter( 'posts_search', 'get_job_listings_keyword_search' );
		}

		$query_args = apply_filters( 'job_manager_get_listings', $query_args, $args );

		if ( empty( $query_args['meta_query'] ) ) {
			unset( $query_args['meta_query'] );
		}

		if ( empty( $query_args['tax_query'] ) ) {
			unset( $query_args['tax_query'] );
		}

		/** This filter is documented in wp-job-manager.php */
		$query_args['lang'] = apply_filters( 'wpjm_lang', null );

		// Filter args.
		$query_args = apply_filters( 'get_job_listings_query_args', $query_args, $args );

		do_action( 'before_get_job_listings', $query_args, $args );

		// Cache results.
		if ( apply_filters( 'get_job_listings_cache_results', true ) ) {
			$to_hash              = wp_json_encode( $query_args );
			$query_args_hash      = 'jm_' . md5( $to_hash . JOB_MANAGER_VERSION ) . WP_Job_Manager_Cache_Helper::get_transient_version( 'get_job_listings' );
			$result               = false;
			$cached_query_results = true;
			$cached_query_posts   = get_transient( $query_args_hash );
			if ( is_string( $cached_query_posts ) ) {
				$cached_query_posts = json_decode( $cached_query_posts, false );
				if (
					$cached_query_posts
					&& is_object( $cached_query_posts )
					&& isset( $cached_query_posts->max_num_pages )
					&& isset( $cached_query_posts->found_posts )
					&& isset( $cached_query_posts->posts )
					&& is_array( $cached_query_posts->posts )
				) {
					if ( in_array( $query_args['fields'], [ 'ids', 'id=>parent' ], true ) ) {
						// For these special requests, just return the array of results as set.
						$posts = $cached_query_posts->posts;
					} else {
						$posts = array_map( 'get_post', $cached_query_posts->posts );
					}

					$result = new WP_Query();
					$result->parse_query( $query_args );
					$result->posts         = $posts;
					$result->found_posts   = intval( $cached_query_posts->found_posts );
					$result->max_num_pages = intval( $cached_query_posts->max_num_pages );
					$result->post_count    = count( $posts );
				}
			}

			if ( false === $result ) {
				$result               = new WP_Query( $query_args );
				$cached_query_results = false;

				$cacheable_result                  = [];
				$cacheable_result['posts']         = array_values( $result->posts );
				$cacheable_result['found_posts']   = $result->found_posts;
				$cacheable_result['max_num_pages'] = $result->max_num_pages;
				set_transient( $query_args_hash, wp_json_encode( $cacheable_result ), DAY_IN_SECONDS );
			}

			if ( $cached_query_results ) {
				// random order is cached so shuffle them.
				if ( 'rand_featured' === $args['orderby'] ) {
					usort( $result->posts, '_wpjm_shuffle_featured_post_results_helper' );
				} elseif ( 'rand' === $args['orderby'] ) {
					shuffle( $result->posts );
				}
			}
		} else {
			$result = new WP_Query( $query_args );
		}

		do_action( 'after_get_job_listings', $query_args, $args );

		remove_filter( 'posts_search', 'get_job_listings_keyword_search' );

		return $result;
	}
endif;

if ( ! function_exists( '_wpjm_shuffle_featured_post_results_helper' ) ) :
	/**
	 * Helper function to maintain featured status when shuffling results.
	 *
	 * @param WP_Post $a
	 * @param WP_Post $b
	 *
	 * @return bool
	 */
	function _wpjm_shuffle_featured_post_results_helper( $a, $b ) {
		if ( -1 === $a->menu_order || -1 === $b->menu_order ) {
			// Left is featured.
			if ( 0 === $b->menu_order ) {
				return -1;
			}
			// Right is featured.
			if ( 0 === $a->menu_order ) {
				return 1;
			}
		}
		return wp_rand( -1, 1 );
	}
endif;

if ( ! function_exists( 'get_job_listings_keyword_search' ) ) :
	/**
	 * Adds join and where query for keywords.
	 *
	 * @since 1.21.0
	 * @since 1.26.0 Moved from the `posts_clauses` filter to the `posts_search` to use WP Query's keyword
	 *               search for `post_title` and `post_content`.
	 * @param string $search
	 * @return string
	 */
	function get_job_listings_keyword_search( $search ) {
		global $wpdb, $job_manager_keyword;

		// Searchable Meta Keys: set to empty to search all meta keys.
		$searchable_meta_keys = [
			'_job_location',
			'_company_name',
			'_application',
			'_company_name',
			'_company_tagline',
			'_company_website',
			'_company_twitter',
		];

		$searchable_meta_keys = apply_filters( 'job_listing_searchable_meta_keys', $searchable_meta_keys );

		// Set Search DB Conditions.
		$conditions = [];

		// Search Post Meta.
		if ( apply_filters( 'job_listing_search_post_meta', true ) ) {

			// Only selected meta keys.
			if ( $searchable_meta_keys ) {
				$conditions[] = "{$wpdb->posts}.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ( '" . implode( "','", array_map( 'esc_sql', $searchable_meta_keys ) ) . "' ) AND meta_value LIKE '%" . esc_sql( $job_manager_keyword ) . "%' )";
			} else {
				// No meta keys defined, search all post meta value.
				$conditions[] = "{$wpdb->posts}.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value LIKE '%" . esc_sql( $job_manager_keyword ) . "%' )";
			}
		}

		// Search taxonomy.
		$conditions[] = "{$wpdb->posts}.ID IN ( SELECT object_id FROM {$wpdb->term_relationships} AS tr LEFT JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id LEFT JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id WHERE t.name LIKE '%" . esc_sql( $job_manager_keyword ) . "%' )";

		/**
		 * Filters the conditions to use when querying job listings. Resulting array is joined with OR statements.
		 *
		 * @since 1.26.0
		 *
		 * @param array  $conditions          Conditions to join by OR when querying job listings.
		 * @param string $job_manager_keyword Search query.
		 */
		$conditions = apply_filters( 'job_listing_search_conditions', $conditions, $job_manager_keyword );
		if ( empty( $conditions ) ) {
			return $search;
		}

		$conditions_str = implode( ' OR ', $conditions );

		if ( ! empty( $search ) ) {
			$search = preg_replace( '/^ AND /', '', $search );
			$search = " AND ( {$search} OR ( {$conditions_str} ) )";
		} else {
			$search = " AND ( {$conditions_str} )";
		}

		return $search;
	}
endif;

if ( ! function_exists( 'get_job_listing_post_statuses' ) ) :
	/**
	 * Gets post statuses used for jobs.
	 *
	 * @since 1.12.0
	 * @return array
	 */
	function get_job_listing_post_statuses() {
		return apply_filters(
			'job_listing_post_statuses',
			[
				'draft'           => _x( 'Draft', 'post status', 'wp-job-manager' ),
				'expired'         => _x( 'Expired', 'post status', 'wp-job-manager' ),
				'preview'         => _x( 'Preview', 'post status', 'wp-job-manager' ),
				'pending'         => _x( 'Pending approval', 'post status', 'wp-job-manager' ),
				'pending_payment' => _x( 'Pending payment', 'post status', 'wp-job-manager' ),
				'publish'         => _x( 'Active', 'post status', 'wp-job-manager' ),
			]
		);
	}
endif;

if ( ! function_exists( 'get_featured_job_ids' ) ) :
	/**
	 * Gets the ids of featured jobs.
	 *
	 * @since 1.0.4
	 * @return array
	 */
	function get_featured_job_ids() {
		return get_posts(
			[
				'posts_per_page'   => -1,
				'suppress_filters' => false,
				'post_type'        => 'job_listing',
				'post_status'      => 'publish',
				'meta_key'         => '_featured',
				'meta_value'       => '1',
				'fields'           => 'ids',
			]
		);
	}
endif;

if ( ! function_exists( 'get_job_listing_types' ) ) :
	/**
	 * Gets job listing types.
	 *
	 * @since 1.0.0
	 * @param string|array $fields
	 * @return WP_Term[]
	 */
	function get_job_listing_types( $fields = 'all' ) {
		if ( ! get_option( 'job_manager_enable_types' ) ) {
			return [];
		} else {
			$args = [
				'fields'     => $fields,
				'hide_empty' => false,
				'order'      => 'ASC',
				'orderby'    => 'name',
			];

			$args = apply_filters( 'get_job_listing_types_args', $args );

			// Prevent users from filtering the taxonomy.
			$args['taxonomy'] = 'job_listing_type';

			return get_terms( $args );
		}
	}
endif;

if ( ! function_exists( 'get_job_listing_categories' ) ) :
	/**
	 * Gets job categories.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	function get_job_listing_categories() {
		if ( ! get_option( 'job_manager_enable_categories' ) ) {
			return [];
		}

		$args = [
			'orderby'    => 'name',
			'order'      => 'ASC',
			'hide_empty' => false,
		];

		/**
		 * Change the category query arguments.
		 *
		 * @since 1.31.0
		 *
		 * @param array $args
		 */
		$args = apply_filters( 'get_job_listing_category_args', $args );

		// Prevent users from filtering the taxonomy.
		$args['taxonomy'] = 'job_listing_category';

		return get_terms( $args );
	}
endif;

if ( ! function_exists( 'job_manager_get_filtered_links' ) ) :
	/**
	 * Shows links after filtering jobs
	 *
	 * @since 1.0.6
	 * @param array $args
	 * @return string
	 */
	function job_manager_get_filtered_links( $args = [] ) {
		$job_categories = [];
		$types          = get_job_listing_types();

		// Convert to slugs.
		if ( $args['search_categories'] ) {
			foreach ( $args['search_categories'] as $category ) {
				if ( is_numeric( $category ) ) {
					$category_object = get_term_by( 'id', $category, 'job_listing_category' );
					if ( ! is_wp_error( $category_object ) ) {
						$job_categories[] = $category_object->slug;
					}
				} else {
					$job_categories[] = $category;
				}
			}
		}

		$links = apply_filters(
			'job_manager_job_filters_showing_jobs_links',
			[
				'reset'    => [
					'name' => __( 'Reset', 'wp-job-manager' ),
					'url'  => '#',
				],
				'rss_link' => [
					'name' => __( 'RSS', 'wp-job-manager' ),
					'url'  => get_job_listing_rss_link(
						apply_filters(
							'job_manager_get_listings_custom_filter_rss_args',
							[
								'job_types'       => isset( $args['filter_job_types'] ) ? implode( ',', $args['filter_job_types'] ) : '',
								'search_location' => $args['search_location'],
								'job_categories'  => implode( ',', $job_categories ),
								'search_keywords' => $args['search_keywords'],
							]
						)
					),
				],
			],
			$args
		);

		if (
			count( (array) $args['filter_job_types'] ) === count( $types )
			&& empty( $args['search_keywords'] )
			&& empty( $args['search_location'] )
			&& empty( $args['search_categories'] )
			&& ! apply_filters( 'job_manager_get_listings_custom_filter', false )
		) {
			unset( $links['reset'] );
		}

		$return = '';

		foreach ( $links as $key => $link ) {
			$return .= '<a href="' . esc_url( $link['url'] ) . '" class="' . esc_attr( $key ) . '">' . wp_kses_post( $link['name'] ) . '</a>';
		}

		return $return;
	}
endif;

if ( ! function_exists( 'get_job_listing_rss_link' ) ) :
	/**
	 * Get the Job Listing RSS link
	 *
	 * @since 1.0.0
	 * @param array $args
	 * @return string
	 */
	function get_job_listing_rss_link( $args = [] ) {
		$rss_link = add_query_arg( urlencode_deep( array_merge( [ 'feed' => WP_Job_Manager_Post_Types::get_job_feed_name() ], $args ) ), home_url() );
		return $rss_link;
	}
endif;

if ( ! function_exists( 'wp_job_manager_notify_new_user' ) ) :
	/**
	 * Handles notification of new users.
	 *
	 * @since 1.23.10
	 * @param  int         $user_id
	 * @param  string|bool $password
	 */
	function wp_job_manager_notify_new_user( $user_id, $password ) {
		global $wp_version;

		if ( version_compare( $wp_version, '4.3.1', '<' ) ) {
			// phpcs:ignore WordPress.WP.DeprecatedParameters.Wp_new_user_notificationParam2Found
			wp_new_user_notification( $user_id, $password );
		} else {
			$notify = 'admin';
			if ( empty( $password ) ) {
				$notify = 'both';
			}
			wp_new_user_notification( $user_id, null, $notify );
		}
	}
endif;

if ( ! function_exists( 'wp_job_manager_create_account' ) ) :
	/**
	 * Handles account creation.
	 *
	 * @since 1.0.0
	 * @param  string|array|object $args containing username, email, role.
	 * @param  string              $deprecated role string.
	 * @return WP_Error|bool True if account was created.
	 */
	function wp_job_manager_create_account( $args, $deprecated = '' ) {
		// Soft Deprecated in 1.20.0.
		if ( ! is_array( $args ) ) {
			$args = [
				'username' => '',
				'password' => false,
				'email'    => $args,
				'role'     => $deprecated,
			];
		} else {
			$defaults = [
				'username' => '',
				'email'    => '',
				'password' => false,
				'role'     => get_option( 'default_role' ),
			];

			$args = wp_parse_args( $args, $defaults );
		}

		$username = sanitize_user( $args['username'], true );
		$email    = apply_filters( 'user_registration_email', sanitize_email( $args['email'] ) );

		if ( empty( $email ) ) {
			return new WP_Error( 'validation-error', __( 'Invalid email address.', 'wp-job-manager' ) );
		}

		if ( empty( $username ) ) {
			$username = sanitize_user( current( explode( '@', $email ) ), true );
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'validation-error', __( 'Your email address isn&#8217;t correct.', 'wp-job-manager' ) );
		}

		if ( email_exists( $email ) ) {
			return new WP_Error( 'validation-error', __( 'This email is already registered, please choose another one.', 'wp-job-manager' ) );
		}

		// Ensure username is unique.
		$append     = 1;
		$o_username = $username;

		while ( username_exists( $username ) ) {
			$username = $o_username . $append;
			$append ++;
		}

		// Final error checking.
		$reg_errors = new WP_Error();
		$reg_errors = apply_filters( 'job_manager_registration_errors', $reg_errors, $username, $email );

		do_action( 'job_manager_register_post', $username, $email, $reg_errors );

		if ( $reg_errors->get_error_code() ) {
			return $reg_errors;
		}

		// Create account.
		$new_user = [
			'user_login' => $username,
			'user_pass'  => $args['password'],
			'user_email' => $email,
			'role'       => $args['role'],
		];

		// User is forced to set up account with email sent to them. This password will remain a secret.
		if ( empty( $new_user['user_pass'] ) ) {
			$new_user['user_pass'] = wp_generate_password();
		}

		$user_id = wp_insert_user( apply_filters( 'job_manager_create_account_data', $new_user ) );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		/**
		 * Send notification to new users.
		 *
		 * @since 1.28.0
		 *
		 * @param  int         $user_id
		 * @param  string|bool $password
		 * @param  array       $new_user {
		 *     Information about the new user.
		 *
		 *     @type string $user_login Username for the user.
		 *     @type string $user_pass  Password for the user (may be blank).
		 *     @type string $user_email Email for the new user account.
		 *     @type string $role       New user's role.
		 * }
		 */
		do_action( 'wpjm_notify_new_user', $user_id, $args['password'], $new_user );

		// Login.
		add_action( 'set_logged_in_cookie', '_wpjm_update_global_login_cookie' );
		wp_set_auth_cookie( $user_id, true, is_ssl() );
		wp_set_current_user( $user_id );
		remove_action( 'set_logged_in_cookie', '_wpjm_update_global_login_cookie' );

		return true;
	}
endif;

/**
 * Allows for immediate access to the logged in cookie after mid-request login.
 *
 * @since 1.32.2
 * @access private
 *
 * @param string $logged_in_cookie Logged in cookie.
 */
function _wpjm_update_global_login_cookie( $logged_in_cookie ) {
	$_COOKIE[ LOGGED_IN_COOKIE ] = $logged_in_cookie;
}

/**
 * Checks if the user can upload a file via the Ajax endpoint.
 *
 * @since 1.26.2
 * @return bool
 */
function job_manager_user_can_upload_file_via_ajax() {
	$can_upload = is_user_logged_in() && job_manager_user_can_post_job();

	if ( has_filter( 'job_manager_ajax_file_upload_enabled' ) ) {
		_deprecated_hook( 'job_manager_ajax_file_upload_enabled', '1.30.0', 'job_manager_user_can_upload_file_via_ajax' );
		$can_upload = apply_filters( 'job_manager_ajax_file_upload_enabled', $can_upload );
	}

	/**
	 * Override ability of a user to upload a file via Ajax.
	 *
	 * @since 1.26.2
	 * @param bool $can_upload True if they can upload files from Ajax endpoint.
	 */
	return apply_filters( 'job_manager_user_can_upload_file_via_ajax', $can_upload );
}

/**
 * Checks if an the user can post a job. If accounts are required, and reg is enabled, users can post (they signup at the same time).
 *
 * @since 1.5.1
 * @return bool
 */
function job_manager_user_can_post_job() {
	$can_post = true;

	if ( ! is_user_logged_in() ) {
		if ( job_manager_user_requires_account() && ! job_manager_enable_registration() ) {
			$can_post = false;
		}
	}

	return apply_filters( 'job_manager_user_can_post_job', $can_post );
}

/**
 * Checks if the user can edit a job.
 *
 * @since 1.5.1
 * @param int|WP_Post $job_id
 * @return bool
 */
function job_manager_user_can_edit_job( $job_id ) {
	$can_edit = true;

	if ( ! is_user_logged_in() || ! $job_id ) {
		$can_edit = false;
	} else {
		$job = get_post( $job_id );

		if ( ! $job || 'job_listing' !== $job->post_type || ( absint( $job->post_author ) !== get_current_user_id() && ! current_user_can( 'edit_post', $job_id ) ) ) {
			$can_edit = false;
		}
	}

	return apply_filters( 'job_manager_user_can_edit_job', $can_edit, $job_id );
}

/**
 * Checks if the visitor is currently on a WPJM page, job listing, or taxonomy.
 *
 * @since 1.30.0
 *
 * @return bool
 */
function is_wpjm() {
	/**
	 * Filter the result of is_wpjm()
	 *
	 * @since 1.30.0
	 *
	 * @param bool $is_wpjm
	 */
	return apply_filters( 'is_wpjm', ( is_wpjm_page() || has_wpjm_shortcode() || is_wpjm_job_listing() || is_wpjm_taxonomy() ) );
}

/**
 * Checks if the visitor is currently on a WPJM page.
 *
 * @since 1.30.0
 *
 * @return bool
 */
function is_wpjm_page() {
	$is_wpjm_page = is_post_type_archive( 'job_listing' );

	if ( ! $is_wpjm_page ) {
		$wpjm_page_ids = array_filter(
			[
				get_option( 'job_manager_submit_job_form_page_id', false ),
				get_option( 'job_manager_job_dashboard_page_id', false ),
				get_option( 'job_manager_jobs_page_id', false ),
			]
		);

		/**
		 * Filters a list of all page IDs related to WPJM.
		 *
		 * @since 1.30.0
		 *
		 * @param int[] $wpjm_page_ids
		 */
		$wpjm_page_ids = array_unique( apply_filters( 'job_manager_page_ids', $wpjm_page_ids ) );

		if ( ! empty( $wpjm_page_ids ) ) {
			$is_wpjm_page = is_page( $wpjm_page_ids );
		}
	}

	/**
	 * Filter the result of is_wpjm_page()
	 *
	 * @since 1.30.0
	 *
	 * @param bool $is_wpjm_page
	 */
	return apply_filters( 'is_wpjm_page', $is_wpjm_page );
}

/**
 * Checks if the provided content or the current single page or post has a WPJM shortcode.
 *
 * @param string|null       $content   Content to check. If not provided, it uses the current post content.
 * @param string|array|null $tag Check specifically for one or more shortcodes. If not provided, checks for any WPJM shortcode.
 *
 * @return bool
 */
function has_wpjm_shortcode( $content = null, $tag = null ) {
	global $post;

	$has_wpjm_shortcode = false;

	if ( null === $content && is_singular() && is_a( $post, 'WP_Post' ) ) {
		$content = $post->post_content;
	}

	if ( ! empty( $content ) ) {
		$wpjm_shortcodes = [ 'submit_job_form', 'job_dashboard', 'jobs', 'job', 'job_summary', 'job_apply' ];
		/**
		 * Filters a list of all shortcodes associated with WPJM.
		 *
		 * @since 1.30.0
		 *
		 * @param string[] $wpjm_shortcodes
		 */
		$wpjm_shortcodes = array_unique( apply_filters( 'job_manager_shortcodes', $wpjm_shortcodes ) );

		if ( null !== $tag ) {
			if ( ! is_array( $tag ) ) {
				$tag = [ $tag ];
			}
			$wpjm_shortcodes = array_intersect( $wpjm_shortcodes, $tag );
		}

		foreach ( $wpjm_shortcodes as $shortcode ) {
			if ( has_shortcode( $content, $shortcode ) ) {
				$has_wpjm_shortcode = true;
				break;
			}
		}
	}

	/**
	 * Filter the result of has_wpjm_shortcode()
	 *
	 * @since 1.30.0
	 *
	 * @param bool $has_wpjm_shortcode
	 */
	return apply_filters( 'has_wpjm_shortcode', $has_wpjm_shortcode );
}

/**
 * Checks if the current page is a job listing.
 *
 * @since 1.30.0
 *
 * @return bool
 */
function is_wpjm_job_listing() {
	return is_singular( [ 'job_listing' ] );
}

/**
 * Checks if the visitor is on a page for a WPJM taxonomy.
 *
 * @since 1.30.0
 *
 * @return bool
 */
function is_wpjm_taxonomy() {
	return is_tax( get_object_taxonomies( 'job_listing' ) );
}

/**
 * Checks to see if the standard password setup email should be used.
 *
 * @since 1.27.0
 *
 * @return bool True if they are to use standard email, false to allow user to set password at first job creation.
 */
function wpjm_use_standard_password_setup_email() {
	$use_standard_password_setup_email = 1 === intval( get_option( 'job_manager_use_standard_password_setup_email' ) );

	/**
	 * Allows an override of the setting for if a password should be auto-generated for new users.
	 *
	 * @since 1.27.0
	 *
	 * @param bool $use_standard_password_setup_email True if a standard account setup email should be sent.
	 */
	return apply_filters( 'wpjm_use_standard_password_setup_email', $use_standard_password_setup_email );
}

/**
 * Returns the list of employment types from Google's modification of schema.org's employmentType.
 *
 * @since 1.28.0
 * @see https://developers.google.com/search/docs/data-types/job-postings#definitions
 *
 * @return array
 */
function wpjm_job_listing_employment_type_options() {
	$employment_types               = [];
	$employment_types['FULL_TIME']  = __( 'Full Time', 'wp-job-manager' );
	$employment_types['PART_TIME']  = __( 'Part Time', 'wp-job-manager' );
	$employment_types['CONTRACTOR'] = __( 'Contractor', 'wp-job-manager' );
	$employment_types['TEMPORARY']  = __( 'Temporary', 'wp-job-manager' );
	$employment_types['INTERN']     = __( 'Intern', 'wp-job-manager' );
	$employment_types['VOLUNTEER']  = __( 'Volunteer', 'wp-job-manager' );
	$employment_types['PER_DIEM']   = __( 'Per Diem', 'wp-job-manager' );
	$employment_types['OTHER']      = __( 'Other', 'wp-job-manager' );

	/**
	 * Filter the list of employment types.
	 *
	 * @since 1.28.0
	 *
	 * @param array List of employment types { string $key => string $label }.
	 */
	return apply_filters( 'wpjm_job_listing_employment_type_options', $employment_types );
}


/**
 * Check if employment type meta fields are enabled on job type terms.
 *
 * @since 1.28.0
 *
 * @return bool
 */
function wpjm_job_listing_employment_type_enabled() {
	/**
	 * Filter whether employment types are enabled for job type terms.
	 *
	 * @since 1.28.0
	 *
	 * @param bool True if employment type meta field is enabled on job type terms.
	 */
	return apply_filters( 'wpjm_job_listing_employment_type_enabled', (bool) get_option( 'job_manager_enable_types' ) );
}

/**
 * Checks if a password should be auto-generated for new users.
 *
 * @since 1.27.0
 *
 * @param string $password Password to validate.
 * @return bool True if password meets rules.
 */
function wpjm_validate_new_password( $password ) {
	// Password must be at least 8 characters long. Trimming here because `wp_hash_password()` will later on.
	$is_valid_password = strlen( trim( $password ) ) >= 8;

	/**
	 * Allows overriding default WPJM password validation rules.
	 *
	 * @since 1.27.0
	 *
	 * @param bool   $is_valid_password True if new password is validated.
	 * @param string $password          Password to validate.
	 */
	return apply_filters( 'wpjm_validate_new_password', $is_valid_password, $password );
}

/**
 * Returns the password rules hint.
 *
 * @return string
 */
function wpjm_get_password_rules_hint() {
	/**
	 * Allows overriding the hint shown below the new password input field. Describes rules set in `wpjm_validate_new_password`.
	 *
	 * @since 1.27.0
	 *
	 * @param string $password_rules Password rules description.
	 */
	return apply_filters( 'wpjm_password_rules_hint', __( 'Passwords must be at least 8 characters long.', 'wp-job-manager' ) );
}

/**
 * Checks if only one type allowed per job.
 *
 * @since 1.25.2
 * @return bool
 */
function job_manager_multi_job_type() {
	return apply_filters( 'job_manager_multi_job_type', 1 === intval( get_option( 'job_manager_multi_job_type' ) ) );
}

/**
 * Checks if registration is enabled.
 *
 * @since 1.5.1
 * @return bool
 */
function job_manager_enable_registration() {
	return apply_filters( 'job_manager_enable_registration', 1 === intval( get_option( 'job_manager_enable_registration' ) ) );
}

/**
 * Checks if usernames are generated from email addresses.
 *
 * @since 1.20.0
 * @return bool
 */
function job_manager_generate_username_from_email() {
	return apply_filters( 'job_manager_generate_username_from_email', 1 === intval( get_option( 'job_manager_generate_username_from_email' ) ) );
}

/**
 * Checks if an account is required to post a job.
 *
 * @since 1.5.1
 * @return bool
 */
function job_manager_user_requires_account() {
	return apply_filters( 'job_manager_user_requires_account', 1 === intval( get_option( 'job_manager_user_requires_account' ) ) );
}

/**
 * Checks if users are allowed to edit submissions that are pending approval.
 *
 * @since 1.16.1
 * @return bool
 */
function job_manager_user_can_edit_pending_submissions() {
	return apply_filters( 'job_manager_user_can_edit_pending_submissions', 1 === intval( get_option( 'job_manager_user_can_edit_pending_submissions' ) ) );
}

/**
 * Checks if users are allowed to edit published submissions.
 *
 * @since 1.29.0
 * @return bool
 */
function wpjm_user_can_edit_published_submissions() {
	/**
	 * Override the setting for allowing a user to edit published job listings.
	 *
	 * @since 1.29.0
	 *
	 * @param bool $can_edit_published_submissions
	 */
	return apply_filters( 'job_manager_user_can_edit_published_submissions', in_array( get_option( 'job_manager_user_edit_published_submissions' ), [ 'yes', 'yes_moderated' ], true ) );
}

/**
 * Checks if moderation is required when users edit published submissions.
 *
 * @since 1.29.0
 * @return bool
 */
function wpjm_published_submission_edits_require_moderation() {
	$require_moderation = 'yes_moderated' === get_option( 'job_manager_user_edit_published_submissions' );

	/**
	 * Override the setting for user edits to job listings requiring moderation.
	 *
	 * @since 1.29.0
	 *
	 * @param bool $require_moderation
	 */
	return apply_filters( 'job_manager_published_submission_edits_require_moderation', $require_moderation );
}

/**
 * Get the category slugs from the search query string.
 * The query string is made with the category slugs separate by commas.
 *
 * @since 1.34.2
 *
 * @return string[] $search_category_slugs Array of category slugs.
 */
function wpjm_get_category_slugs_from_search_query_string() {
	$search_category_slugs = [];

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Should be checked outside of this function.
	$search_category = ! empty( $_GET['search_category'] ) ? sanitize_text_field( wp_unslash( $_GET['search_category'] ) ) : false;

	if ( $search_category ) {
		$search_category_slugs = explode( ',', $search_category );
	}

	return $search_category_slugs;
}

/**
 * Get categories by slug.
 *
 * @since 1.34.2
 *
 * @param string[]  $search_category_slugs Array of category slugs to search.
 * @param array     $default_args          Default args to search the term categories.
 * @param WP_Term[] $exclude_categories    Array of categories to exclude.
 *
 * @return WP_Term[] $categories Array of categories.
 */
function wpjm_get_categories_by_slug( $search_category_slugs, $default_args, $exclude_categories ) {
	$exclude_category_ids = wp_list_pluck( $exclude_categories, 'term_id' );

	$args = [
		'hide_empty' => false,
		'slug'       => $search_category_slugs,
		'exclude'    => $exclude_category_ids,
	];

	$args = wp_parse_args( $args, $default_args );

	return get_terms( $args );
}

/**
 * Displays category select dropdown.
 *
 * Based on wp_dropdown_categories, with the exception of supporting multiple selected categories.
 *
 * @since 1.14.0
 * @see  wp_dropdown_categories
 * @param string|array|object $args
 * @return string
 */
function job_manager_dropdown_categories( $args = '' ) {
	$defaults = [
		'orderby'         => 'id',
		'order'           => 'ASC',
		'show_count'      => 0,
		'hide_empty'      => 1,
		'parent'          => '',
		'child_of'        => 0,
		'exclude'         => '',
		'echo'            => 1,
		'selected'        => 0,
		'hierarchical'    => 0,
		'name'            => 'cat',
		'id'              => '',
		'class'           => 'job-manager-category-dropdown ' . ( is_rtl() ? 'chosen-rtl' : '' ),
		'depth'           => 0,
		'taxonomy'        => 'job_listing_category',
		'value'           => 'id',
		'multiple'        => true,
		'show_option_all' => false,
		'placeholder'     => __( 'Choose a category&hellip;', 'wp-job-manager' ),
		'no_results_text' => __( 'No results match', 'wp-job-manager' ),
		'multiple_text'   => __( 'Select Some Options', 'wp-job-manager' ),
	];

	$r = wp_parse_args( $args, $defaults );

	if ( ! isset( $r['pad_counts'] ) && $r['show_count'] && $r['hierarchical'] ) {
		$r['pad_counts'] = true;
	}

	if ( ! isset( $r['search_category_slugs'] ) ) {
		$r['search_category_slugs'] = wpjm_get_category_slugs_from_search_query_string();
	}

	/** This filter is documented in wp-job-manager.php */
	$r['lang'] = apply_filters( 'wpjm_lang', null );

	// Store in a transient to help sites with many cats.
	$categories_hash = 'jm_cats_' . md5( wp_json_encode( $r ) . WP_Job_Manager_Cache_Helper::get_transient_version( 'jm_get_' . $r['taxonomy'] ) );
	$categories      = get_transient( $categories_hash );

	if ( empty( $categories ) ) {
		$args = [
			'taxonomy'     => $r['taxonomy'],
			'orderby'      => $r['orderby'],
			'order'        => $r['order'],
			'hide_empty'   => $r['hide_empty'],
			'parent'       => $r['parent'],
			'child_of'     => $r['child_of'],
			'exclude'      => $r['exclude'],
			'hierarchical' => $r['hierarchical'],
		];

		$categories = get_terms( $args );

		if ( ! empty( $r['search_category_slugs'] ) ) {
			$categories = array_merge(
				$categories,
				wpjm_get_categories_by_slug( $r['search_category_slugs'], $args, $categories )
			);
		}

		set_transient( $categories_hash, $categories, DAY_IN_SECONDS * 7 );
	}

	$id = $r['id'] ? $r['id'] : $r['name'];

	$output = "<select name='" . esc_attr( $r['name'] ) . "[]' id='" . esc_attr( $id ) . "' class='" . esc_attr( $r['class'] ) . "' " . ( $r['multiple'] ? "multiple='multiple'" : '' ) . " data-placeholder='" . esc_attr( $r['placeholder'] ) . "' data-no_results_text='" . esc_attr( $r['no_results_text'] ) . "' data-multiple_text='" . esc_attr( $r['multiple_text'] ) . "'>\n";

	if ( $r['show_option_all'] ) {
		$output .= '<option value="">' . esc_html( $r['show_option_all'] ) . '</option>';
	}

	if ( ! empty( $categories ) ) {
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-category-walker.php';

		$walker = new WP_Job_Manager_Category_Walker();

		if ( $r['hierarchical'] ) {
			$depth = $r['depth'];  // Walk the full depth.
		} else {
			$depth = -1; // Flat.
		}

		$output .= $walker->walk( $categories, $depth, $r );
	}

	$output .= "</select>\n";

	if ( $r['echo'] ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $output;
	}

	return $output;
}

/**
 * Gets the page ID of a page if set.
 *
 * @since 1.23.12
 * @param  string $page e.g. job_dashboard, submit_job_form, jobs.
 * @return int
 */
function job_manager_get_page_id( $page ) {
	$page_id = get_option( 'job_manager_' . $page . '_page_id', false );
	if ( $page_id ) {
		/**
		 * Filters the page ID for a WPJM page.
		 *
		 * @since 1.26.0
		 *
		 * @param int $page_id
		 */
		return apply_filters( 'wpjm_page_id', $page_id );
	} else {
		return 0;
	}
}

/**
 * Gets the permalink of a page if set.
 *
 * @since 1.16.0
 * @param  string $page e.g. job_dashboard, submit_job_form, jobs.
 * @return string|bool
 */
function job_manager_get_permalink( $page ) {
	$page_id = job_manager_get_page_id( $page );
	if ( $page_id ) {
		return get_permalink( $page_id );
	} else {
		return false;
	}
}

/**
 * Filters the upload dir when $job_manager_upload is true.
 *
 * @since 1.21.0
 * @param  array $pathdata
 * @return array
 */
function job_manager_upload_dir( $pathdata ) {
	global $job_manager_upload, $job_manager_uploading_file;

	if ( ! empty( $job_manager_upload ) ) {
		$dir = untrailingslashit( apply_filters( 'job_manager_upload_dir', 'job-manager-uploads/' . sanitize_key( $job_manager_uploading_file ), sanitize_key( $job_manager_uploading_file ) ) );

		if ( empty( $pathdata['subdir'] ) ) {
			$pathdata['path']   = $pathdata['path'] . '/' . $dir;
			$pathdata['url']    = $pathdata['url'] . '/' . $dir;
			$pathdata['subdir'] = '/' . $dir;
		} else {
			$new_subdir         = '/' . $dir . $pathdata['subdir'];
			$pathdata['path']   = str_replace( $pathdata['subdir'], $new_subdir, $pathdata['path'] );
			$pathdata['url']    = str_replace( $pathdata['subdir'], $new_subdir, $pathdata['url'] );
			$pathdata['subdir'] = $new_subdir;
		}
	}

	return $pathdata;
}
add_filter( 'upload_dir', 'job_manager_upload_dir' );

/**
 * Prepares files for upload by standardizing them into an array. This adds support for multiple file upload fields.
 *
 * @since 1.21.0
 * @param  array $file_data
 * @return array
 */
function job_manager_prepare_uploaded_files( $file_data ) {
	$files_to_upload = [];

	if ( is_array( $file_data['name'] ) ) {
		foreach ( $file_data['name'] as $file_data_key => $file_data_value ) {
			if ( $file_data['name'][ $file_data_key ] ) {
				$type              = wp_check_filetype( $file_data['name'][ $file_data_key ] ); // Map mime type to one WordPress recognises.
				$files_to_upload[] = [
					'name'     => $file_data['name'][ $file_data_key ],
					'type'     => $type['type'],
					'tmp_name' => $file_data['tmp_name'][ $file_data_key ],
					'error'    => $file_data['error'][ $file_data_key ],
					'size'     => $file_data['size'][ $file_data_key ],
				];
			}
		}
	} else {
		$type              = wp_check_filetype( $file_data['name'] ); // Map mime type to one WordPress recognises.
		$file_data['type'] = $type['type'];
		$files_to_upload[] = $file_data;
	}

	return apply_filters( 'job_manager_prepare_uploaded_files', $files_to_upload );
}

/**
 * Uploads a file using WordPress file API.
 *
 * @since 1.21.0
 * @param  array|WP_Error      $file Array of $_FILE data to upload.
 * @param  string|array|object $args Optional arguments.
 * @return stdClass|WP_Error Object containing file information, or error.
 */
function job_manager_upload_file( $file, $args = [] ) {
	global $job_manager_upload, $job_manager_uploading_file;

	include_once ABSPATH . 'wp-admin/includes/file.php';
	include_once ABSPATH . 'wp-admin/includes/media.php';

	$args = wp_parse_args(
		$args,
		[
			'file_key'           => '',
			'file_label'         => '',
			'allowed_mime_types' => '',
		]
	);

	$job_manager_upload         = true;
	$job_manager_uploading_file = $args['file_key'];
	$uploaded_file              = new stdClass();
	if ( '' === $args['allowed_mime_types'] ) {
		$allowed_mime_types = job_manager_get_allowed_mime_types( $job_manager_uploading_file );
	} else {
		$allowed_mime_types = $args['allowed_mime_types'];
	}

	/**
	 * Filter file configuration before upload
	 *
	 * This filter can be used to modify the file arguments before being uploaded, or return a WP_Error
	 * object to prevent the file from being uploaded, and return the error.
	 *
	 * @since 1.25.2
	 *
	 * @param array $file               Array of $_FILE data to upload.
	 * @param array $args               Optional file arguments.
	 * @param array $allowed_mime_types Array of allowed mime types from field config or defaults.
	 */
	$file = apply_filters( 'job_manager_upload_file_pre_upload', $file, $args, $allowed_mime_types );

	if ( is_wp_error( $file ) ) {
		return $file;
	}

	if ( ! in_array( $file['type'], $allowed_mime_types, true ) ) {
		// Replace pipe separating similar extensions (e.g. jpeg|jpg) to comma to match the list separator.
		$allowed_file_extensions = implode( ', ', str_replace( '|', ', ', array_keys( $allowed_mime_types ) ) );

		if ( $args['file_label'] ) {
			// translators: %1$s is the file field label; %2$s is the file type; %3$s is the list of allowed file types.
			return new WP_Error( 'upload', sprintf( __( '"%1$s" (filetype %2$s) needs to be one of the following file types: %3$s', 'wp-job-manager' ), $args['file_label'], $file['type'], $allowed_file_extensions ) );
		} else {
			// translators: %s is the list of allowed file types.
			return new WP_Error( 'upload', sprintf( __( 'Uploaded files need to be one of the following file types: %s', 'wp-job-manager' ), $allowed_file_extensions ) );
		}
	} else {
		$upload = wp_handle_upload( $file, apply_filters( 'submit_job_wp_handle_upload_overrides', [ 'test_form' => false ] ) );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'upload', $upload['error'] );
		} else {
			$uploaded_file->url       = $upload['url'];
			$uploaded_file->file      = $upload['file'];
			$uploaded_file->name      = basename( $upload['file'] );
			$uploaded_file->type      = $upload['type'];
			$uploaded_file->size      = $file['size'];
			$uploaded_file->extension = substr( strrchr( $uploaded_file->name, '.' ), 1 );
		}
	}

	$job_manager_upload         = false;
	$job_manager_uploading_file = '';

	return $uploaded_file;
}

/**
 * Returns mime types specifically for WPJM.
 *
 * @since 1.25.1
 * @param   string $field Field used.
 * @return  array  Array of allowed mime types
 */
function job_manager_get_allowed_mime_types( $field = '' ) {
	if ( 'company_logo' === $field ) {
		$allowed_mime_types = [
			'jpg|jpeg|jpe' => 'image/jpeg',
			'gif'          => 'image/gif',
			'png'          => 'image/png',
		];
	} else {
		$allowed_mime_types = [
			'jpg|jpeg|jpe' => 'image/jpeg',
			'gif'          => 'image/gif',
			'png'          => 'image/png',
			'pdf'          => 'application/pdf',
			'doc'          => 'application/msword',
			'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		];
	}

	/**
	 * Mime types to accept in uploaded files.
	 *
	 * Default is image, pdf, and doc(x) files.
	 *
	 * @since 1.25.1
	 *
	 * @param array  {
	 *     Array of allowed file extensions and mime types.
	 *     Key is pipe-separated file extensions. Value is mime type.
	 * }
	 * @param string $field The field key for the upload.
	 */
	return apply_filters( 'job_manager_mime_types', $allowed_mime_types, $field );
}

/**
 * Calculates and returns the job expiry date.
 *
 * @since 1.22.0
 * @since 1.35.0 Added the `$return_datetime` param.
 *
 * @param  int  $job_id          Job ID.
 * @param  bool $return_datetime Return the date time object.
 * @return string|DateTimeImmutable When `$return_datetime`, it will return either DateTimeImmutable or null.
 */
function calculate_job_expiry( $job_id, $return_datetime = false ) {
	// Get duration from the product if set...
	$duration = get_post_meta( $job_id, '_job_duration', true );

	// ...otherwise use the global option.
	if ( ! $duration ) {
		$duration = get_option( 'job_manager_submission_duration' );
	}

	if ( $duration ) {
		$new_job_expiry = current_datetime()->add( new DateInterval( 'P' . absint( $duration ) . 'D' ) );

		return $return_datetime ? WP_Job_Manager_Post_Types::instance()->prepare_job_expires_time( $new_job_expiry ) : $new_job_expiry->format( 'Y-m-d' );
	}

	return $return_datetime ? null : '';
}

/**
 * Duplicates a listing.
 *
 * @since 1.25.0
 * @param  int $post_id
 * @return int 0 on fail or the post ID.
 */
function job_manager_duplicate_listing( $post_id ) {
	global $wpdb;

	if ( empty( $post_id ) ) {
		return 0;
	}

	$post = get_post( $post_id );
	if ( ! $post || 'job_listing' !== $post->post_type ) {
		return 0;
	}

	/**
	 * Duplicate the post.
	 */
	$new_post_id = wp_insert_post(
		[
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $post->post_author,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_name'      => $post->post_name,
			'post_parent'    => $post->post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => 'preview',
			'post_title'     => $post->post_title,
			'post_type'      => $post->post_type,
			'to_ping'        => $post->to_ping,
			'menu_order'     => $post->menu_order,
		]
	);

	/**
	 * Copy taxonomies.
	 */
	$taxonomies = get_object_taxonomies( $post->post_type );

	foreach ( $taxonomies as $taxonomy ) {
		$post_terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'slugs' ] );
		wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
	}

	/*
	 * Duplicate post meta, aside from some reserved fields.
	 */

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Easiest way to retrieve raw meta values without filters.
	$post_meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id=%d", $post_id ) );

	if ( ! empty( $post_meta ) ) {
		$post_meta = wp_list_pluck( $post_meta, 'meta_value', 'meta_key' );

		$default_duplicate_ignore_keys = [ '_filled', '_featured', '_job_expires', '_job_duration', '_package_id', '_user_package_id', '_edit_lock', '_submitting_key', '_tracked_submitted', '_tracked_approved' ];
		$duplicate_ignore_keys         = apply_filters( 'job_manager_duplicate_listing_ignore_keys', $default_duplicate_ignore_keys, true );

		foreach ( $post_meta as $meta_key => $meta_value ) {
			$sanitized_key = preg_replace( "/[^\x20-\x7E]/", '', $meta_key );

			if ( in_array( $sanitized_key, $duplicate_ignore_keys, true ) ) {
				continue;
			}

			if ( 1 === preg_match( '/^(_wp_|_oembed_)/', $sanitized_key ) ) {
				continue;
			}

			update_post_meta( $new_post_id, wp_slash( $meta_key ), wp_slash( maybe_unserialize( $meta_value ) ) );
		}
	}

	update_post_meta( $new_post_id, '_filled', 0 );
	update_post_meta( $new_post_id, '_featured', 0 );

	return $new_post_id;
}

/**
 * Escape JSON for use on HTML or attribute text nodes.
 *
 * @since 1.32.2
 *
 * @param string $json JSON to escape.
 * @param bool   $html True if escaping for HTML text node, false for attributes. Determines how quotes are handled.
 * @return string Escaped JSON.
 */
function wpjm_esc_json( $json, $html = false ) {
	return _wp_specialchars(
		$json,
		$html ? ENT_NOQUOTES : ENT_QUOTES, // Escape quotes in attribute nodes only.
		'UTF-8',                           // json_encode() outputs UTF-8 (really just ASCII), not the blog's charset.
		true                               // Double escape entities: `&amp;` -> `&amp;amp;`.
	);
}
