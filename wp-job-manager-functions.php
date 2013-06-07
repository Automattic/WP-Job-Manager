<?php
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

if ( ! function_exists( 'wp_job_manager_encode_email' ) ) :
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

if ( ! function_exists( 'wp_job_manager_create_account' ) ) :
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