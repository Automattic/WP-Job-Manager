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

	if ( ! empty( $args['search_categories'] ) )
		$query_args['tax_query'][] = array(
			'taxonomy' => 'job_listing_category',
			'field'    => 'slug',
			'terms'    => $args['search_categories']
		);

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

		$post_ids = $post_ids + $wpdb->get_col( $wpdb->prepare( "
		    SELECT DISTINCT ID FROM {$wpdb->posts}
		    WHERE post_title LIKE '%%%s%%'
		    OR post_content LIKE '%%%s%%'
		", $args['search_keywords'], $args['search_keywords'] ) );

		$query_args['post__in'] = $post_ids + array( 0 );
	}

	$query_args = apply_filters( 'job_manager_get_listings', $query_args );

	if ( empty( $query_args['meta_query'] ) )
		unset( $query_args['meta_query'] );

	if ( empty( $query_args['tax_query'] ) )
		unset( $query_args['tax_query'] );

	return new WP_Query( $query_args );
}
endif;

if ( ! function_exists( 'get_job_listing_types' ) ) :
/**
 * Outputs a form to submit a new job to the site from the frontend.
 *
 * @access public
 * @return void
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

if ( ! function_exists( 'job_manager_encode_email' ) ) :
/**
 * Munge an email address
 *
 * @param  string $email
 * @return string
 */
function wp_job_manager_encode_email( $email ) {
    $encmail = "";
    for ( $i = 0; $i < strlen( $email ); $i++ ) {
    	$char   = substr( $email, $i, 1 );

    	if ( $char == '@' ) {
    		$encmail .= ' [at] ';
    	} elseif ( $char == '.' ) {
    		$encmail .= ' [dot] ';
    	} else {
	        $encMod = rand( 0, 2 );
	        switch ( $encMod ) {
	        	case 0: // None
	           		$encmail .= $char;
	            break;
	       		case 1: // Decimal
	            	$encmail .= "&#" . ord( $char ) . ';';
	            break;
	        	case 2: // Hexadecimal
	            	$encmail .= "&#x" . dechex( ord( $char ) ) . ';';
	            break;
	        }
   		}
    }
	return $encmail;
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
 * @param  [type] $account_email
 * @return WP_error | bool was an account created?
 */
function wp_job_manager_create_account( $account_email ) {
	global  $current_user;

	$user_email = apply_filters( 'user_registration_email', sanitize_email( $account_email ) );

	if ( empty( $user_email ) )
		return false;

	if ( ! is_email( $user_email ) )
		return new WP_Error( 'validation-error', __( 'Your email address isn&#8217;t correct.', 'job_manager' ) );

	if ( email_exists( $user_email ) )
		return new WP_Error( 'validation-error', __( 'This email is already registered, please choose another one.', 'job_manager' ) );

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
    	'user_email' => $user_email
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