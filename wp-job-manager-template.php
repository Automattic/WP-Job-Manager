<?php
/**
 * Template Functions
 *
 * Template functions specifically created for job listings
 *
 * @author 		Mike Jolley
 * @category 	Core
 * @package 	Job Manager/Template
 * @version     1.0.0
 */

/**
 * Get and include template files.
 *
 * @param mixed $template_name
 * @param array $args (default: array())
 * @param string $template_path (default: '')
 * @param string $default_path (default: '')
 * @return void
 */
function get_job_manager_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
	if ( $args && is_array($args) )
		extract( $args );

	include( locate_job_manager_template( $template_name, $template_path, $default_path ) );
}

/**
 * Locate a template and return the path for inclusion.
 *
 * This is the load order:
 *
 *		yourtheme		/	$template_path	/	$template_name
 *		yourtheme		/	$template_name
 *		$default_path	/	$template_name
 *
 * @param mixed $template_name
 * @param string $template_path (default: '')
 * @param string $default_path (default: '')
 * @return string
 */
function locate_job_manager_template( $template_name, $template_path = '', $default_path = '' ) {
	if ( ! $template_path )
		$template_path = 'job_manager';
	if ( ! $default_path )
		$default_path = JOB_MANAGER_PLUGIN_DIR . '/templates/';

	// Look within passed path within the theme - this is priority
	$template = locate_template(
		array(
			trailingslashit( $template_path ) . $template_name,
			$template_name
		)
	);

	// Get default template
	if ( ! $template )
		$template = $default_path . $template_name;

	// Return what we found
	return apply_filters( 'job_manager_locate_template', $template, $template_name, $template_path );
}

/**
 * Get template part (for templates in loops).
 *
 * @param mixed $slug
 * @param string $name (default: '')
 * @return void
 */
function get_job_manager_template_part( $slug, $name = '', $template_path = '', $default_path = '' ) {
	if ( ! $template_path )
		$template_path = 'job_manager';
	if ( ! $default_path )
		$default_path = JOB_MANAGER_PLUGIN_DIR . '/templates/';

	$template = '';

	// Look in yourtheme/slug-name.php and yourtheme/job_manager/slug-name.php
	if ( $name )
		$template = locate_template( array ( "{$slug}-{$name}.php", "{$template_path}/{$slug}-{$name}.php" ) );

	// Get default slug-name.php
	if ( ! $template && $name && file_exists( $default_path . "{$slug}-{$name}.php" ) )
		$template = $default_path . "{$slug}-{$name}.php";

	// If template file doesn't exist, look in yourtheme/slug.php and yourtheme/job_manager/slug.php
	if ( ! $template )
		$template = locate_template( array( "{$slug}.php", "{$template_path}/{$slug}.php" ) );

	if ( $template )
		load_template( $template, false );
}

/**
 * Outputs the jobs status
 *
 * @return void
 */
function the_job_status( $post = null ) {
	echo get_the_job_status( $post );
}

/**
 * Gets the jobs status
 *
 * @return string
 */
function get_the_job_status( $post = null ) {
	$post = get_post( $post );

	$status = $post->post_status;

	if ( $status == 'publish' )
		$status = __( 'Active', 'wp-job-manager' );
	elseif ( $status == 'expired' )
		$status = __( 'Expired', 'wp-job-manager' );
	elseif ( $status == 'pending' )
		$status = __( 'Pending Review', 'wp-job-manager' );
	else
		$status = __( 'Inactive', 'wp-job-manager' );

	return apply_filters( 'the_job_status', $status, $post );
}

/**
 * Return whether or not the position has been marked as filled
 *
 * @param  object $post
 * @return boolean
 */
function is_position_filled( $post = null ) {
	$post = get_post( $post );

	return $post->_filled ? true : false;
}

/**
 * Return whether or not the position has been featured
 *
 * @param  object $post
 * @return boolean
 */
function is_position_featured( $post = null ) {
	$post = get_post( $post );

	return $post->_featured ? true : false;
}

/**
 * the_job_permalink function.
 *
 * @access public
 * @return void
 */
function the_job_permalink( $post = null ) {
	echo get_the_job_permalink( $post );
}

/**
 * get_the_job_permalink function.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return string
 */
function get_the_job_permalink( $post = null ) {
	$post = get_post( $post );
	$link = get_permalink( $post );

	return apply_filters( 'the_job_permalink', $link, $post );
}

/**
 * get_the_job_application_method function.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return object
 */
function get_the_job_application_method( $post = null ) {
	$post = get_post( $post );
	if ( $post->post_type !== 'job_listing' )
		return;

	$method = new stdClass();
	$apply  = $post->_application;

	if ( empty( $apply ) )
		return false;

	if ( strstr( $apply, '@' ) && is_email( $apply ) ) {
		$method->type      = 'email';
		$method->raw_email = $apply;
		$method->email     = antispambot( $apply );
		$method->subject   = 'Job Application via "' . $post->post_title . '" listing on ' . home_url();
	} else {
		if ( strpos( $apply, 'http' ) !== 0 )
			$apply = 'http://' . $apply;
		$method->type = 'url';
		$method->url  = $apply;
	}

	return apply_filters( 'the_job_application_method', $method, $post );
}
/**
 * the_job_type function.
 *
 * @access public
 * @return void
 */
function the_job_type( $post = null ) {
	if ( $job_type = get_the_job_type( $post ) )
		echo $job_type->name;
}

/**
 * get_the_job_type function.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return void
 */
function get_the_job_type( $post = null ) {
	$post = get_post( $post );
	if ( $post->post_type !== 'job_listing' )
		return;

	$types = wp_get_post_terms( $post->ID, 'job_listing_type' );

	if ( $types )
		$type = current( $types );
	else
		$type = false;

	return apply_filters( 'the_job_type', $type, $post );
}


/**
 * the_job_location function.
 * @param  boolean $map_link whether or not to link to the map on google maps
 * @return [type]
 */
function the_job_location( $map_link = true, $post = null ) {
	$location = get_the_job_location( $post );

	if ( $location ) {
		if ( $map_link )
			echo apply_filters( 'the_job_location_map_link', '<a class="google_map_link" href="http://maps.google.com/maps?q=' . urlencode( $location ) . '&zoom=14&size=512x512&maptype=roadmap&sensor=false">' . $location . '</a>', $location, $post );
		else
			echo $location;
	} else {
		echo apply_filters( 'the_job_location_anywhere_text', __( 'Anywhere', 'wp-job-manager' ) );
	}
}

/**
 * get_the_job_location function.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return void
 */
function get_the_job_location( $post = null ) {
	$post = get_post( $post );
	if ( $post->post_type !== 'job_listing' )
		return;

	return apply_filters( 'the_job_location', $post->_job_location, $post );
}

/**
 * the_company_logo function.
 *
 * @access public
 * @param string $size (default: 'full')
 * @param mixed $default (default: null)
 * @return void
 */
function the_company_logo( $size = 'full', $default = null, $post = null ) {
	global $job_manager;

	$logo = get_the_company_logo( $post );

	if ( ! empty( $logo ) && ( strstr( $logo, 'http' ) || file_exists( $logo ) ) ) {

		if ( $size !== 'full' )
			$logo = job_manager_get_resized_image( $logo, $size );

		echo '<img class="company_logo" src="' . $logo . '" alt="Logo" />';

	} elseif ( $default )
		echo '<img class="company_logo" src="' . $default . '" alt="Logo" />';
	else
		echo '<img class="company_logo" src="' . JOB_MANAGER_PLUGIN_URL . '/assets/images/company.png' . '" alt="Logo" />';
}

/**
 * get_the_company_logo function.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return string
 */
function get_the_company_logo( $post = null ) {
	$post = get_post( $post );
	if ( $post->post_type !== 'job_listing' )
		return;

	return apply_filters( 'the_company_logo', $post->_company_logo, $post );
}

/**
 * Resize and get url of the image
 *
 * @param  string $logo
 * @param  string $size
 * @return string
 */
function job_manager_get_resized_image( $logo, $size ) {
	global $_wp_additional_image_sizes;

	if ( $size !== 'full' && isset( $_wp_additional_image_sizes[ $size ] ) ) {

		$img_width  = $_wp_additional_image_sizes[ $size ]['width'];
		$img_height = $_wp_additional_image_sizes[ $size ]['height'];

		$logo_path         = str_replace( home_url('/'), ABSPATH, $logo );
		$path_parts        = pathinfo( $logo_path );
		$resized_logo_path = str_replace( '.' . $path_parts['extension'], '-' . $size . '.' . $path_parts['extension'], $logo_path );

		if ( ! file_exists( $resized_logo_path ) ) {
			// Generate size
			$image = wp_get_image_editor( $logo_path );

			if ( ! is_wp_error( $image ) ) {
			    $image->resize( $_wp_additional_image_sizes[ $size ]['width'], $_wp_additional_image_sizes[ $size ]['height'], $_wp_additional_image_sizes[ $size ]['crop'] );

			    $image->save( $resized_logo_path );

			    $logo = dirname( $logo ) . '/' . basename( $resized_logo_path );
			}
		} else {
			$logo = dirname( $logo ) . '/' . basename( $resized_logo_path );
		}
	}

	return $logo;
}

/**
 * Display or retrieve the current company name with optional content.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function the_company_name( $before = '', $after = '', $echo = true, $post = null ) {
	$company_name = get_the_company_name( $post );

	if ( strlen( $company_name ) == 0 )
		return;

	$company_name = esc_attr( strip_tags( $company_name ) );
	$company_name = $before . $company_name . $after;

	if ( $echo )
		echo $company_name;
	else
		return $company_name;
}

/**
 * get_the_company_name function.
 *
 * @access public
 * @param int $post (default: null)
 * @return void
 */
function get_the_company_name( $post = null ) {
	$post = get_post( $post );
	if ( $post->post_type !== 'job_listing' )
		return;

	return apply_filters( 'the_company_name', $post->_company_name, $post );
}

/**
 * get_the_company_website function.
 *
 * @access public
 * @param int $post (default: null)
 * @return void
 */
function get_the_company_website( $post = null ) {
	$post = get_post( $post );
	if ( $post->post_type !== 'job_listing' )
		return;

	return apply_filters( 'the_company_website', $post->_company_website, $post );
}

/**
 * Display or retrieve the current company tagline with optional content.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function the_company_tagline( $before = '', $after = '', $echo = true, $post = null ) {
	$company_tagline = get_the_company_tagline( $post );

	if ( strlen( $company_tagline ) == 0 )
		return;

	$company_tagline = esc_attr( strip_tags( $company_tagline ) );
	$company_tagline = $before . $company_tagline . $after;

	if ( $echo )
		echo $company_tagline;
	else
		return $company_tagline;
}

/**
 * get_the_company_tagline function.
 *
 * @access public
 * @param int $post (default: 0)
 * @return void
 */
function get_the_company_tagline( $post = null ) {
	$post = get_post( $post );

	if ( $post->post_type !== 'job_listing' )
		return;

	return apply_filters( 'the_company_tagline', $post->_company_tagline, $post );
}

/**
 * Display or retrieve the current company twitter link with optional content.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function the_company_twitter( $before = '', $after = '', $echo = true, $post = null ) {
	$company_twitter = get_the_company_twitter( $post );

	if ( strlen( $company_twitter ) == 0 )
		return;

	$company_twitter = esc_attr( strip_tags( $company_twitter ) );
	$company_twitter = $before . '<a href="http://twitter.com/' . $company_twitter . '" class="company_twitter">' . $company_twitter . '</a>' . $after;

	if ( $echo )
		echo $company_twitter;
	else
		return $company_twitter;
}

/**
 * get_the_company_twitter function.
 *
 * @access public
 * @param int $post (default: 0)
 * @return void
 */
function get_the_company_twitter( $post = null ) {
	$post = get_post( $post );
	if ( $post->post_type !== 'job_listing' )
		return;

	$company_twitter = $post->_company_twitter;

	if ( strlen( $company_twitter ) == 0 )
		return;

	if ( strpos( $company_twitter, '@' ) === 0 )
		$company_twitter = substr( $company_twitter, 1 );

	return apply_filters( 'the_company_twitter', $company_twitter, $post );
}

/**
 * job_listing_class function.
 *
 * @access public
 * @param string $class (default: '')
 * @param mixed $post_id (default: null)
 * @return void
 */
function job_listing_class( $class = '', $post_id = null ) {
	// Separates classes with a single space, collates classes for post DIV
	echo 'class="' . join( ' ', get_job_listing_class( $class, $post_id ) ) . '"';
}

/**
 * get_job_listing_class function.
 *
 * @access public
 * @return array
 */
function get_job_listing_class( $class = '', $post_id = null ) {
	$post = get_post( $post_id );
	if ( $post->post_type !== 'job_listing' )
		return array();

	$classes = array();

	if ( empty( $post ) )
		return $classes;

	$classes[] = 'job_listing';
	$classes[] = 'job-type-' . sanitize_title( get_the_job_type()->name );

	if ( is_position_filled( $post ) )
		$classes[] = 'job_position_filled';

	if ( is_position_featured( $post ) )
		$classes[] = 'job_position_featured';

	return get_post_class( $classes, $post->ID );
}
