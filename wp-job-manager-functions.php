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
		'order'             => 'DESC'
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

	if ( ! empty( $args['job_types'] ) )
		$query_args['tax_query'][] = array(
			'taxonomy' => 'job_listing_type',
			'field'    => 'slug',
			'terms'    => $args['job_types']
		);

	if ( ! empty( $args['search_categories'] ) ) {
		$field = is_numeric( $args['search_categories'][0] ) ? 'term_id' : 'slug';
		
		$query_args['tax_query'][] = array(
			'taxonomy' => 'job_listing_category',
			'field'    => $field,
			'terms'    => $args['search_categories']
		);
	}

	if ( get_option( 'job_manager_hide_filled_positions' ) == 1 )
		$query_args['meta_query'][] = array(
			'key'     => '_filled',
			'value'   => '1',
			'compare' => '!='
		);

	if ( $args['search_location'] )
		$query_args['meta_query'][] = array(
			'key'     => '_job_location',
			'value'   => $args['search_location'],
			'compare' => 'LIKE'
		);

	// Keyword search - search meta as well as post content
	if ( $args['search_keywords'] ) {
		$post_ids = $wpdb->get_col( $wpdb->prepare( "
		    SELECT DISTINCT post_id FROM {$wpdb->postmeta}
		    WHERE meta_value LIKE '%%%s%%'
		", $args['search_keywords'] ) );

		$post_ids = array_merge( $post_ids, $wpdb->get_col( $wpdb->prepare( "
		    SELECT ID FROM {$wpdb->posts}
		    WHERE post_title LIKE '%%%s%%'
		    OR post_content LIKE '%%%s%%'
		    AND post_type = 'job_listing'
		    AND post_status = 'publish'
		", $args['search_keywords'], $args['search_keywords'] ) ) );

		$query_args['post__in'] = $post_ids + array( 0 );
	}

	$query_args = apply_filters( 'job_manager_get_listings', $query_args );

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
	$query_args = apply_filters( 'get_job_listings_query_args', $query_args );

	do_action( 'before_get_job_listings', $query_args );

	$result = new WP_Query( $query_args );

	do_action( 'after_get_job_listings', $query_args );

	remove_filter( 'posts_clauses', 'order_featured_job_listing' );

	return $result;
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
 * Outputs a form to submit a new job to the site from the frontend.
 *
 * @access public
 * @return array
 */
function get_job_listing_types() {
	return get_terms( "job_listing_type", array(
		'orderby'       => 'name',
	    'order'         => 'ASC',
	    'hide_empty'    => false,
	) );
}
endif;

if ( ! function_exists( 'get_job_listing_categories' ) ) :
/**
 * Outputs a form to submit a new job to the site from the frontend.
 *
 * @access public
 * @return array
 */
function get_job_listing_categories() {
	if ( ! get_option( 'job_manager_enable_categories' ) )
		return array();

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
				'job_categories' => implode( ',', $args['search_categories'] ),
				's'              => $args['search_keywords'],
			) ) )
		)
	), $args );

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
 * @param  string $account_email
 * @param  string $role 
 * @return WP_error | bool was an account created?
 */
function wp_job_manager_create_account( $account_email, $role = '' ) {
	global  $current_user;

	$user_email = apply_filters( 'user_registration_email', sanitize_email( $account_email ) );

	if ( empty( $user_email ) )
		return false;

	if ( ! is_email( $user_email ) )
		return new WP_Error( 'validation-error', __( 'Your email address isn&#8217;t correct.', 'wp-job-manager' ) );

	if ( email_exists( $user_email ) )
		return new WP_Error( 'validation-error', __( 'This email is already registered, please choose another one.', 'wp-job-manager' ) );

	// Email is good to go - use it to create a user name
	$username = sanitize_user( current( explode( '@', $user_email ) ) );
	$password = wp_generate_password();

	// Ensure username is unique
	$append     = 1;
	$o_username = $username;

	while( username_exists( $username ) ) {
		$username = $o_username . $append;
		$append ++;
	}

	// Final error check
	$reg_errors = new WP_Error();
	do_action( 'register_post', $username, $user_email, $reg_errors );
	$reg_errors = apply_filters( 'registration_errors', $reg_errors, $username, $user_email );

	if ( $reg_errors->get_error_code() )
		return $reg_errors;

	// Create account
	$new_user = array(
		'user_login' => $username,
		'user_pass'  => $password,
		'user_email' => $user_email,
		'role'       => $role
    );

    $user_id = wp_insert_user( apply_filters( 'job_manager_create_account_data', $new_user ) );

    if ( is_wp_error( $user_id ) )
    	return $user_id;

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
	} elseif ( $job->post_author != get_current_user_id() ) {
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
 * True if an account is required to post a job.
 *
 * @return bool
 */
function job_manager_user_requires_account() {
	return apply_filters( 'job_manager_user_requires_account', get_option( 'job_manager_user_requires_account' ) == 1 ? true : false );
}