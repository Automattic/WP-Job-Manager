<?php
if ( ! function_exists( 'get_job_listings' ) ) :
/**
 * Queries job listings with certain criteria and returns them
 *
 * @access public
 * @return void
 */
function get_job_listings( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args( $args, array(
		'search_location'   => '',
		'search_keywords'   => '',
		'search_categories' => array(),
		'job_types'         => array(),
		'offset'            => '',
		'posts_per_page'    => '-1',
		'orderby'           => 'date',
		'order'             => 'DESC',
		'featured'          => null,
		'filled'            => null
	) );

	$query_args = array(
		'post_type'           => 'job_listing',
		'post_status'         => 'publish',
		'ignore_sticky_posts' => 1,
		'offset'              => absint( $args['offset'] ),
		'posts_per_page'      => intval( $args['posts_per_page'] ),
		'orderby'             => $args['orderby'],
		'order'               => $args['order'],
		'tax_query'           => array(),
		'meta_query'          => array()
	);

	if ( ! empty( $args['job_types'] ) ) {
		$query_args['tax_query'][] = array(
			'taxonomy' => 'job_listing_type',
			'field'    => 'slug',
			'terms'    => $args['job_types']
		);
	}

	if ( ! empty( $args['search_categories'] ) ) {
		$field = is_numeric( $args['search_categories'][0] ) ? 'term_id' : 'slug';

		$query_args['tax_query'][] = array(
			'taxonomy' => 'job_listing_category',
			'field'    => $field,
			'terms'    => $args['search_categories'],
			'operator' => get_option( 'job_manager_category_filter_type', 'all' ) == 'all' ? 'AND' : 'IN'
		);
	}

	if ( ! is_null( $args['featured'] ) ) {
		$query_args['meta_query'][] = array(
			'key'     => '_featured',
			'value'   => '1',
			'compare' => $args['featured'] ? '=' : '!='
		);
	}

	if ( ! is_null( $args['filled'] ) ) {
		$query_args['meta_query'][] = array(
			'key'     => '_filled',
			'value'   => '1',
			'compare' => $args['filled'] ? '=' : '!='
		);
	} elseif ( get_option( 'job_manager_hide_filled_positions' ) == 1 ) {
		$query_args['meta_query'][] = array(
			'key'     => '_filled',
			'value'   => '1',
			'compare' => '!='
		);
	}

	// Location search - search geolocation data and location meta
	if ( $args['search_location'] ) {
		$location_post_ids = $wpdb->get_col( apply_filters( 'get_job_listings_location_post_ids_sql', $wpdb->prepare( "
		    SELECT DISTINCT post_id FROM {$wpdb->postmeta}
		    WHERE meta_key IN ( 'geolocation_city', 'geolocation_country_long', 'geolocation_country_short', 'geolocation_formatted_address', 'geolocation_state_long', 'geolocation_state_short', 'geolocation_street', 'geolocation_zipcode', '_job_location' )
		    AND meta_value LIKE '%%%s%%'
		", $args['search_location'] ) ) );

		$location_post_ids = array_merge( $location_post_ids, array( 0 ) );
	} else {
		$location_post_ids = array();
	}

	// Keyword search - search meta as well as post content
	if ( $args['search_keywords'] ) {
		$search_keywords              = array_map( 'trim', explode( ',', $args['search_keywords'] ) );
		$posts_search_keywords_sql    = array();
		$postmeta_search_keywords_sql = array();

		foreach ( $search_keywords as $keyword ) {
			$postmeta_search_keywords_sql[] = " meta_value LIKE '%" . esc_sql( $keyword ) . "%' ";
			$posts_search_keywords_sql[]    = "
				post_title LIKE '%" . esc_sql( $keyword ) . "%'
				OR post_content LIKE '%" . esc_sql( $keyword ) . "%'
			";
		}

		$keyword_post_ids = $wpdb->get_col( "
		    SELECT DISTINCT post_id FROM {$wpdb->postmeta}
		    WHERE " . implode( ' OR ', $postmeta_search_keywords_sql ) . "
		" );

		$keyword_post_ids = array_merge( $keyword_post_ids, $wpdb->get_col( "
		    SELECT ID FROM {$wpdb->posts}
		    WHERE ( " . implode( ' OR ', $posts_search_keywords_sql ) . " )
		    AND post_type = 'job_listing'
		" ), array( 0 ) );
	} else {
		$keyword_post_ids = array();
	}

	// Merge post ids
	if ( ! empty( $location_post_ids ) && ! empty( $keyword_post_ids ) ) {
		$query_args['post__in'] = array_intersect( $location_post_ids, $keyword_post_ids );
	} elseif ( ! empty( $location_post_ids ) || ! empty( $keyword_post_ids ) ) {
		$query_args['post__in'] = array_merge( $location_post_ids, $keyword_post_ids );
	}

	$query_args = apply_filters( 'job_manager_get_listings', $query_args, $args );

	if ( empty( $query_args['meta_query'] ) )
		unset( $query_args['meta_query'] );

	if ( empty( $query_args['tax_query'] ) )
		unset( $query_args['tax_query'] );

	if ( $args['orderby'] == 'featured' ) {
		$query_args['orderby'] = 'meta_key';
		$query_args['meta_key'] = '_featured';
		add_filter( 'posts_clauses', 'order_featured_job_listing' );
	}

	// Filter args
	$query_args = apply_filters( 'get_job_listings_query_args', $query_args, $args );

	do_action( 'before_get_job_listings', $query_args, $args );

	$result = new WP_Query( $query_args );

	do_action( 'after_get_job_listings', $query_args, $args );

	remove_filter( 'posts_clauses', 'order_featured_job_listing' );

	return $result;
}
endif;

if ( ! function_exists( 'get_job_listing_post_statuses' ) ) :
/**
 * Get post statuses used for jobs
 *
 * @access public
 * @return array
 */
function get_job_listing_post_statuses() {
	return apply_filters( 'job_listing_post_statuses', array(
		'draft'           => _x( 'Draft', 'post status', 'wp-job-manager' ),
		'expired'         => _x( 'Expired', 'post status', 'wp-job-manager' ),
		'preview'         => _x( 'Preview', 'post status', 'wp-job-manager' ),
		'pending'         => _x( 'Pending approval', 'post status', 'wp-job-manager' ),
		'pending_payment' => _x( 'Pending payment', 'post status', 'wp-job-manager' ),
		'publish'         => _x( 'Active', 'post status', 'wp-job-manager' ),
	) );
}
endif;

if ( ! function_exists( 'order_featured_job_listing' ) ) :
	/**
	 * WP Core doens't let us change the sort direction for invidual orderby params - http://core.trac.wordpress.org/ticket/17065
	 *
	 * @access public
	 * @param array $args
	 * @return array
	 */
	function order_featured_job_listing( $args ) {
		global $wpdb;

		$args['orderby'] = "$wpdb->postmeta.meta_value+0 DESC, $wpdb->posts.post_date DESC";

		return $args;
	}
endif;

if ( ! function_exists( 'get_featured_job_ids' ) ) :
/**
 * Gets the ids of featured jobs.
 *
 * @access public
 * @return array
 */
function get_featured_job_ids() {
	return get_posts( array(
		'posts_per_page' => -1,
		'post_type'      => 'job_listing',
		'post_status'    => 'publish',
		'meta_key'       => '_featured',
		'meta_value'     => '1',
		'fields'         => 'ids'
	) );
}
endif;

if ( ! function_exists( 'get_job_listing_types' ) ) :
/**
 * Get job listing types
 *
 * @access public
 * @return array
 */
function get_job_listing_types( $fields = 'all' ) {
	return get_terms( "job_listing_type", array(
		'orderby'    => 'name',
		'order'      => 'ASC',
		'hide_empty' => false,
		'fields'     => $fields
	) );
}
endif;

if ( ! function_exists( 'get_job_listing_categories' ) ) :
/**
 * Get job categories
 *
 * @access public
 * @return array
 */
function get_job_listing_categories() {
	if ( ! get_option( 'job_manager_enable_categories' ) ) {
		return array();
	}

	return get_terms( "job_listing_category", array(
		'orderby'       => 'name',
	    'order'         => 'ASC',
	    'hide_empty'    => false,
	) );
}
endif;

if ( ! function_exists( 'job_manager_get_filtered_links' ) ) :
/**
 * Shows links after filtering jobs
 */
function job_manager_get_filtered_links( $args = array() ) {
	$job_categories = array();
	$types          = get_job_listing_types();

	// Convert to slugs
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

	$links = apply_filters( 'job_manager_job_filters_showing_jobs_links', array(
		'reset' => array(
			'name' => __( 'Reset', 'wp-job-manager' ),
			'url'  => '#'
		),
		'rss_link' => array(
			'name' => __( 'RSS', 'wp-job-manager' ),
			'url'  => get_job_listing_rss_link( apply_filters( 'job_manager_get_listings_custom_filter_rss_args', array(
				'type'           => isset( $args['filter_job_types'] ) ? implode( ',', $args['filter_job_types'] ) : '',
				'location'       => $args['search_location'],
				'job_categories' => implode( ',', $job_categories ),
				's'              => $args['search_keywords'],
			) ) )
		)
	), $args );

	if ( sizeof( $args['filter_job_types'] ) === sizeof( $types ) && ! $args['search_keywords'] && ! $args['search_location'] && ! $args['search_categories'] && ! apply_filters( 'job_manager_get_listings_custom_filter', false ) ) {
		unset( $links['reset'] );
	}

	$return = '';

	foreach ( $links as $key => $link ) {
		$return .= '<a href="' . esc_url( $link['url'] ) . '" class="' . esc_attr( $key ) . '">' . $link['name'] . '</a>';
	}

	return $return;
}
endif;

if ( ! function_exists( 'get_job_listing_rss_link' ) ) :
/**
 * Get the Job Listing RSS link
 *
 * @return string
 */
function get_job_listing_rss_link( $args = array() ) {
	$rss_link = add_query_arg( array_merge( array( 'feed' => 'job_feed' ), $args ), home_url() );

	return $rss_link;
}
endif;

if ( ! function_exists( 'job_manager_create_account' ) ) :
/**
 * Handle account creation.
 *
 * @param  array $args containing username, email, role
 * @param  string $deprecated role string
 * @return WP_error | bool was an account created?
 */
function wp_job_manager_create_account( $args, $deprecated = '' ) {
	global $current_user;

	// Soft Deprecated in 1.20.0
	if ( ! is_array( $args ) ) {
		$username = '';
		$password = wp_generate_password();
		$email    = $args;
		$role     = $deprecated;
	} else {
		$defaults = array(
			'username' => '',
			'email'    => '',
			'password' => wp_generate_password(),
			'role'     => get_option( 'default_role' )
		);

		$args = wp_parse_args( $args, $defaults );
		extract( $args );
	}

	$username = sanitize_user( $username );
	$email    = apply_filters( 'user_registration_email', sanitize_email( $email ) );

	if ( empty( $email ) ) {
		return new WP_Error( 'validation-error', __( 'Invalid email address.', 'wp-job-manager' ) );
	}

	if ( empty( $username ) ) {
		$username = sanitize_user( current( explode( '@', $email ) ) );
	}

	if ( ! is_email( $email ) ) {
		return new WP_Error( 'validation-error', __( 'Your email address isn&#8217;t correct.', 'wp-job-manager' ) );
	}

	if ( email_exists( $email ) ) {
		return new WP_Error( 'validation-error', __( 'This email is already registered, please choose another one.', 'wp-job-manager' ) );
	}

	// Ensure username is unique
	$append     = 1;
	$o_username = $username;

	while ( username_exists( $username ) ) {
		$username = $o_username . $append;
		$append ++;
	}

	// Final error checking
	$reg_errors = new WP_Error();
	$reg_errors = apply_filters( 'job_manager_registration_errors', $reg_errors, $username, $email );

	do_action( 'job_manager_register_post', $username, $email, $reg_errors );

	if ( $reg_errors->get_error_code() ) {
		return $reg_errors;
	}

	// Create account
	$new_user = array(
		'user_login' => $username,
		'user_pass'  => $password,
		'user_email' => $email,
		'role'       => $role
    );

    $user_id = wp_insert_user( apply_filters( 'job_manager_create_account_data', $new_user ) );

    if ( is_wp_error( $user_id ) ) {
    	return $user_id;
    }

    // Notify
    wp_new_user_notification( $user_id, $password );

	// Login
    wp_set_auth_cookie( $user_id, true, is_ssl() );
    $current_user = get_user_by( 'id', $user_id );

    return true;
}
endif;

/**
 * True if an the user can post a job. If accounts are required, and reg is enabled, users can post (they signup at the same time).
 *
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
 * True if an the user can edit a job.
 *
 * @return bool
 */
function job_manager_user_can_edit_job( $job_id ) {
	$can_edit = true;
	$job      = get_post( $job_id );

	if ( ! is_user_logged_in() ) {
		$can_edit = false;
	} elseif ( $job->post_author != get_current_user_id() && ! current_user_can( 'edit_post', $job_id ) ) {
		$can_edit = false;
	}

	return apply_filters( 'job_manager_user_can_edit_job', $can_edit, $job_id );
}

/**
 * True if registration is enabled.
 *
 * @return bool
 */
function job_manager_enable_registration() {
	return apply_filters( 'job_manager_enable_registration', get_option( 'job_manager_enable_registration' ) == 1 ? true : false );
}

/**
 * True if usernames are generated from email addresses.
 *
 * @return bool
 */
function job_manager_generate_username_from_email() {
	return apply_filters( 'job_manager_generate_username_from_email', get_option( 'job_manager_generate_username_from_email' ) == 1 ? true : false );
}

/**
 * True if an account is required to post a job.
 *
 * @return bool
 */
function job_manager_user_requires_account() {
	return apply_filters( 'job_manager_user_requires_account', get_option( 'job_manager_user_requires_account' ) == 1 ? true : false );
}

/**
 * True if users are allowed to edit submissions that are pending approval.
 *
 * @return bool
 */
function job_manager_user_can_edit_pending_submissions() {
	return apply_filters( 'job_manager_user_can_edit_pending_submissions', get_option( 'job_manager_user_can_edit_pending_submissions' ) == 1 ? true : false );
}

/**
 * Based on wp_dropdown_categories, with the exception of supporting multiple selected categories.
 * @see  wp_dropdown_categories
 */
function job_manager_dropdown_categories( $args = '' ) {
	$defaults = array(
		'orderby'      => 'id',
		'order'        => 'ASC',
		'show_count'   => 0,
		'hide_empty'   => 1,
		'child_of'     => 0,
		'exclude'      => '',
		'echo'         => 1,
		'selected'     => 0,
		'hierarchical' => 0,
		'name'         => 'cat',
		'id'           => '',
		'class'        => 'job-manager-category-dropdown ' . ( is_rtl() ? 'chosen-rtl' : '' ),
		'depth'        => 0,
		'taxonomy'     => 'job_listing_category',
		'value'        => 'id',
		'placeholder'  => __( 'Choose a category&hellip;', 'wp-job-manager' )
	);

	$r = wp_parse_args( $args, $defaults );

	if ( ! isset( $r['pad_counts'] ) && $r['show_count'] && $r['hierarchical'] ) {
		$r['pad_counts'] = true;
	}

	extract( $r );

	$categories = get_terms( $taxonomy, $r );
	$name       = esc_attr( $name );
	$class      = esc_attr( $class );
	$id         = $id ? esc_attr( $id ) : $name;

	$output = "<select name='{$name}[]' id='$id' class='$class' multiple='multiple' data-placeholder='{$placeholder}'>\n";

	if ( ! empty( $categories ) ) {
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-category-walker.php' );

		$walker = new WP_Job_Manager_Category_Walker;

		if ( $hierarchical ) {
			$depth = $r['depth'];  // Walk the full depth.
		} else {
			$depth = -1; // Flat.
		}

		$output .= $walker->walk( $categories, $depth, $r );
	}

	$output .= "</select>\n";

	if ( $echo ) {
		echo $output;
	}

	return $output;
}

/**
 * Get the permalink of a page if set
 * @param  string $page e.g. job_dashboard, submit_job_form, jobs
 * @return string|bool
 */
function job_manager_get_permalink( $page ) {
	$page_id = get_option( 'job_manager_' . $page . '_page_id', false );
	if ( $page_id ) {
		return get_permalink( $page_id );
	} else {
		return false;
	}
}