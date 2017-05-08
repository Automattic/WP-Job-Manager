<?php
/**
 * Template Functions
 *
 * Template functions specifically created for job listings
 *
 * @author 		Mike Jolley
 * @category 	Core
 * @package 	Job Manager/Template
 * @version     1.25.3
 */

/**
 * Gets and includes template files.
 *
 * @since 1.0.0
 * @param mixed  $template_name
 * @param array  $args (default: array())
 * @param string $template_path (default: '')
 * @param string $default_path (default: '')
 */
function get_job_manager_template( $template_name, $args = array(), $template_path = 'job_manager', $default_path = '' ) {
	if ( $args && is_array( $args ) ) {
		extract( $args );
	}
	include( locate_job_manager_template( $template_name, $template_path, $default_path ) );
}

/**
 * Locates a template and return the path for inclusion.
 *
 * This is the load order:
 *
 *		yourtheme		/	$template_path	/	$template_name
 *		yourtheme		/	$template_name
 *		$default_path	/	$template_name
 *
 * @since 1.0.0
 * @param string      $template_name
 * @param string      $template_path (default: 'job_manager')
 * @param string|bool $default_path (default: '') False to not load a default
 * @return string
 */
function locate_job_manager_template( $template_name, $template_path = 'job_manager', $default_path = '' ) {
	// Look within passed path within the theme - this is priority
	$template = locate_template(
		array(
			trailingslashit( $template_path ) . $template_name,
			$template_name
		)
	);

	// Get default template
	if ( ! $template && $default_path !== false ) {
		$default_path = $default_path ? $default_path : JOB_MANAGER_PLUGIN_DIR . '/templates/';
		if ( file_exists( trailingslashit( $default_path ) . $template_name ) ) {
			$template = trailingslashit( $default_path ) . $template_name;
		}
	}

	// Return what we found
	return apply_filters( 'job_manager_locate_template', $template, $template_name, $template_path );
}

/**
 * Gets template part (for templates in loops).
 *
 * @since 1.0.0
 * @param string      $slug
 * @param string      $name (default: '')
 * @param string      $template_path (default: 'job_manager')
 * @param string|bool $default_path (default: '') False to not load a default
 */
function get_job_manager_template_part( $slug, $name = '', $template_path = 'job_manager', $default_path = '' ) {
	$template = '';

	if ( $name ) {
		$template = locate_job_manager_template( "{$slug}-{$name}.php", $template_path, $default_path );
	}

	// If template file doesn't exist, look in yourtheme/slug.php and yourtheme/job_manager/slug.php
	if ( ! $template ) {
		$template = locate_job_manager_template( "{$slug}.php", $template_path, $default_path );
	}

	if ( $template ) {
		load_template( $template, false );
	}
}

/**
 * Adds custom body classes.
 *
 * @since 1.16.0
 * @param  array $classes
 * @return array
 */
function job_manager_body_class( $classes ) {
	$classes   = (array) $classes;
	$classes[] = sanitize_title( wp_get_theme() );

	return array_unique( $classes );
}

add_filter( 'body_class', 'job_manager_body_class' );

/**
 * Get jobs pagination for [jobs] shortcode.
 *
 * @since 1.13.0
 * @param int $max_num_pages
 * @param int $current_page
 * @return string
 */
function get_job_listing_pagination( $max_num_pages, $current_page = 1 ) {
	ob_start();
	get_job_manager_template( 'job-pagination.php', array( 'max_num_pages' => $max_num_pages, 'current_page' => absint( $current_page ) ) );
	return ob_get_clean();
}

/**
 * Displays the jobs status.
 *
 * @since 1.0.0
 * @param int|WP_Post $post
 */
function the_job_status( $post = null ) {
	echo get_the_job_status( $post );
}

/**
 * Gets the jobs status.
 *
 * @since 1.
 * @param int|WP_Post $post
 * @return string
 */
function get_the_job_status( $post = null ) {
	$post     = get_post( $post );
	$status   = $post->post_status;
	$statuses = get_job_listing_post_statuses();

	if ( isset( $statuses[ $status ] ) ) {
		$status = $statuses[ $status ];
	} else {
		$status = __( 'Inactive', 'wp-job-manager' );
	}

	return apply_filters( 'the_job_status', $status, $post );
}

/**
 * Checks whether or not the position has been marked as filled.
 *
 * @since 1.0.0
 * @param  WP_Post|int $post
 * @return boolean
 */
function is_position_filled( $post = null ) {
	$post = get_post( $post );
	return $post->_filled ? true : false;
}

/**
 * Checks whether or not the position has been featured.
 *
 * @since 1.2.0
 * @param  WP_Post|int $post
 * @return boolean
 */
function is_position_featured( $post = null ) {
	$post = get_post( $post );
	return $post->_featured ? true : false;
}

/**
 * Checks whether or not applications are allowed.
 *
 * @since 1.21.0
 * @param  WP_Post|int $post
 * @return boolean
 */
function candidates_can_apply( $post = null ) {
	$post = get_post( $post );
	return apply_filters( 'job_manager_candidates_can_apply', ( ! is_position_filled() && ! in_array( $post->post_status, array( 'preview', 'expired' ) ) ), $post );
}

/**
 * Displays the permalink for the job listing post.
 *
 * @since 1.0.0
 * @param int|WP_Post $post (default: null)
 * @return void
 */
function the_job_permalink( $post = null ) {
	echo get_the_job_permalink( $post );
}

/**
 * Gets the permalink for a job listing.
 *
 * @since 1.0.0
 * @param int|WP_Post $post (default: null)
 * @return string
 */
function get_the_job_permalink( $post = null ) {
	$post = get_post( $post );
	$link = get_permalink( $post );

	return apply_filters( 'the_job_permalink', $link, $post );
}

/**
 * Gets the application method for the job listing.
 *
 * @since 1.0.0
 * @param int|WP_Post $post (default: null)
 * @return stdClass|bool|null
 */
function get_the_job_application_method( $post = null ) {
	$post = get_post( $post );

	if ( $post && $post->post_type !== 'job_listing' ) {
		return;
	}

	$method = new stdClass();
	$apply  = $post->_application;

	if ( empty( $apply ) )
		return false;

	if ( strstr( $apply, '@' ) && is_email( $apply ) ) {
		$method->type      = 'email';
		$method->raw_email = $apply;
		$method->email     = antispambot( $apply );
		$method->subject   = apply_filters( 'job_manager_application_email_subject', sprintf( __( 'Application via "%s" listing on %s', 'wp-job-manager' ), $post->post_title, home_url() ), $post );
	} else {
		if ( strpos( $apply, 'http' ) !== 0 )
			$apply = 'http://' . $apply;
		$method->type = 'url';
		$method->url  = $apply;
	}

	return apply_filters( 'the_job_application_method', $method, $post );
}
/**
 * Displays the job type for the listing.
 *
 * @since 1.0.0
 * @param int|WP_Post $post
 * @return string
 */
function the_job_type( $post = null ) {
	if ( ! get_option( 'job_manager_enable_types' ) ) {
		return '';
	}
	if ( $job_type = get_the_job_type( $post ) ) {
		echo $job_type->name;
	}
}

/**
 * Gets the job type for the listing.
 *
 * @since 1.0.0
 * @param int|WP_Post $post (default: null)
 * @return string|bool|null
 */
function get_the_job_type( $post = null ) {
	$post = get_post( $post );
	if ( $post->post_type !== 'job_listing' ) {
		return;
	}

	$types = wp_get_post_terms( $post->ID, 'job_listing_type' );

	if ( $types ) {
		$type = current( $types );
	} else {
		$type = false;
	}

	return apply_filters( 'the_job_type', $type, $post );
}


/**
 * Displays the published date of the job listing.
 *
 * @since 1.25.3
 * @param int|WP_Post $post (default: null)
 */
function the_job_publish_date( $post = null ) {
	$date_format = get_option( 'job_manager_date_format' );

	if ( $date_format === 'default' ) {
		$display_date = __( 'Posted on ', 'wp-job-manager' ) . get_post_time( get_option( 'date_format' ) );
	} else {
		$display_date = sprintf( __( 'Posted %s ago', 'wp-job-manager' ), human_time_diff( get_post_time( 'U' ), current_time( 'timestamp' ) ) );
	}

	echo '<time datetime="' . get_post_time( 'Y-m-d' ) . '">' . $display_date . '</time>';
}


/**
 * Gets the published date of the job listing.
 *
 * @since 1.25.3
 * @param int|WP_Post $post (default: null)
 * @return string|int|false
 */
function get_the_job_publish_date( $post = null ) {
	$date_format = get_option( 'job_manager_date_format' );

	if ( $date_format === 'default' ) {
		return get_post_time( get_option( 'date_format' ) );
	} else {
		return sprintf( __( 'Posted %s ago', 'wp-job-manager' ), human_time_diff( get_post_time( 'U' ), current_time( 'timestamp' ) ) );
	}
}


/**
 * Displays the location for the job listing.
 *
 * @since 1.0.0
 * @param  bool        $map_link whether or not to link to Google Maps
 * @param int|WP_Post $post
 */
function the_job_location( $map_link = true, $post = null ) {
	$location = get_the_job_location( $post );

	if ( $location ) {
		if ( $map_link ) {
			// If linking to google maps, we don't want anything but text here
			echo apply_filters( 'the_job_location_map_link', '<a class="google_map_link" href="' . esc_url( 'http://maps.google.com/maps?q=' . urlencode( strip_tags( $location ) ) . '&zoom=14&size=512x512&maptype=roadmap&sensor=false' ) . '" target="_blank">' . esc_html( strip_tags( $location ) ) . '</a>', $location, $post );
		} else {
			echo wp_kses_post( $location );
		}
	} else {
		echo wp_kses_post( apply_filters( 'the_job_location_anywhere_text', __( 'Anywhere', 'wp-job-manager' ) ) );
	}
}

/**
 * Gets the location for the job listing.
 *
 * @since 1.0.0
 * @param int|WP_Post $post (default: null)
 * @return string|null
 */
function get_the_job_location( $post = null ) {
	$post = get_post( $post );
	if ( $post->post_type !== 'job_listing' ) {
		return;
	}

	return apply_filters( 'the_job_location', $post->_job_location, $post );
}

/**
 * Displays the company logo.
 *
 * @since 1.0.0
 * @param string      $size (default: 'full')
 * @param mixed       $default (default: null)
 * @param int|WP_Post $post (default: null)
 */
function the_company_logo( $size = 'thumbnail', $default = null, $post = null ) {
	$logo = get_the_company_logo( $post, $size );

	if ( has_post_thumbnail( $post ) ) {
		echo '<img class="company_logo" src="' . esc_attr( $logo ) . '" alt="' . esc_attr( get_the_company_name( $post ) ) . '" />';

	// Before 1.24.0, logo URLs were stored in post meta.
	} elseif ( ! empty( $logo ) && ( strstr( $logo, 'http' ) || file_exists( $logo ) ) ) {
		if ( $size !== 'full' ) {
			$logo = job_manager_get_resized_image( $logo, $size );
		}
		echo '<img class="company_logo" src="' . esc_attr( $logo ) . '" alt="' . esc_attr( get_the_company_name( $post ) ) . '" />';
	} elseif ( $default ) {
		echo '<img class="company_logo" src="' . esc_attr( $default ) . '" alt="' . esc_attr( get_the_company_name( $post ) ) . '" />';
	} else {
		echo '<img class="company_logo" src="' . esc_attr( apply_filters( 'job_manager_default_company_logo', JOB_MANAGER_PLUGIN_URL . '/assets/images/company.png' ) ) . '" alt="' . esc_attr( get_the_company_name( $post ) ) . '" />';
	}
}

/**
 * Gets the company logo.
 *
 * @since 1.0.0
 * @param int|WP_Post $post (default: null)
 * @param string      $size
 * @return string Image SRC
 */
function get_the_company_logo( $post = null, $size = 'thumbnail' ) {
	$post = get_post( $post );

	if ( has_post_thumbnail( $post->ID ) ) {
		$src = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), $size );
		return $src ? $src[0] : '';
	} elseif ( ! empty( $post->_company_logo ) ) {
		// Before 1.24.0, logo URLs were stored in post meta.
		return apply_filters( 'the_company_logo', $post->_company_logo, $post );
	}

	return '';
}

/**
 * Resizes and returns the url of an image.
 *
 * @since 1.5.1
 * @param  string $logo
 * @param  string $size
 * @return string
 */
function job_manager_get_resized_image( $logo, $size ) {
	global $_wp_additional_image_sizes;

	if ( $size !== 'full' && strstr( $logo, WP_CONTENT_URL ) && ( isset( $_wp_additional_image_sizes[ $size ] ) || in_array( $size, array( 'thumbnail', 'medium', 'large' ) ) ) ) {

		if ( in_array( $size, array( 'thumbnail', 'medium', 'large' ) ) ) {
			$img_width  = get_option( $size . '_size_w' );
			$img_height = get_option( $size . '_size_h' );
			$img_crop   = get_option( $size . '_size_crop' );
		} else {
			$img_width  = $_wp_additional_image_sizes[ $size ]['width'];
			$img_height = $_wp_additional_image_sizes[ $size ]['height'];
			$img_crop   = $_wp_additional_image_sizes[ $size ]['crop'];
		}

		$upload_dir        = wp_upload_dir();
		$logo_path         = str_replace( array( $upload_dir['baseurl'], $upload_dir['url'], WP_CONTENT_URL ), array( $upload_dir['basedir'], $upload_dir['path'], WP_CONTENT_DIR ), $logo );
		$path_parts        = pathinfo( $logo_path );
		$dims              = $img_width . 'x' . $img_height;
		$resized_logo_path = str_replace( '.' . $path_parts['extension'], '-' . $dims . '.' . $path_parts['extension'], $logo_path );

		if ( strstr( $resized_logo_path, 'http:' ) || strstr( $resized_logo_path, 'https:' ) ) {
			return $logo;
		}

		if ( ! file_exists( $resized_logo_path ) ) {
			ob_start();

			$image = wp_get_image_editor( $logo_path );

			if ( ! is_wp_error( $image ) ) {

				$resize = $image->resize( $img_width, $img_height, $img_crop );

			   	if ( ! is_wp_error( $resize ) ) {

			   		$save = $image->save( $resized_logo_path );

					if ( ! is_wp_error( $save ) ) {
						$logo = dirname( $logo ) . '/' . basename( $resized_logo_path );
					}
				}
			}

			ob_get_clean();
		} else {
			$logo = dirname( $logo ) . '/' . basename( $resized_logo_path );
		}
	}

	return $logo;
}

/**
 * Displays the company video.
 *
 * @since 1.14.0
 * @param int|WP_Post $post
 */
function the_company_video( $post = null ) {
	$video_embed = false;
	$video       = get_the_company_video( $post );
	$filetype    = wp_check_filetype( $video );

	if( ! empty( $video ) ){
		// FV Wordpress Flowplayer Support for advanced video formats
		if ( shortcode_exists( 'flowplayer' ) ) {
			$video_embed = '[flowplayer src="' . esc_attr( $video ) . '"]';
		} elseif ( ! empty( $filetype[ 'ext' ] ) ) {
			$video_embed = wp_video_shortcode( array( 'src' => $video ) );
		} else {
			$video_embed = wp_oembed_get( $video );
		}
	}

	$video_embed = apply_filters( 'the_company_video_embed', $video_embed, $post );

	if ( $video_embed ) {
		echo '<div class="company_video">' . $video_embed . '</div>';
	}
}

/**
 * Gets the company video URL.
 *
 * @since 1.14.0
 * @param int|WP_Post $post (default: null)
 * @return string|null
 */
function get_the_company_video( $post = null ) {
	$post = get_post( $post );
	if ( $post->post_type !== 'job_listing' ) {
		return;
	}
	return apply_filters( 'the_company_video', $post->_company_video, $post );
}

/**
 * Displays or retrieves the current company name with optional content.
 *
 * @since 1.0.0
 * @since 1.0.1 Add the `$post` argument.
 * @param string           $before (default: '')
 * @param string           $after (default: '')
 * @param bool             $echo (default: true)
 * @param int|WP_Post|null $post (default: null)
 * @return string|void
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
 * Gets the company name.
 *
 * @since 1.0.0
 * @param int $post (default: null)
 * @return string
 */
function get_the_company_name( $post = null ) {
	$post = get_post( $post );
	if ( $post->post_type !== 'job_listing' ) {
		return '';
	}

	return apply_filters( 'the_company_name', $post->_company_name, $post );
}

/**
 * Gets the company website.
 *
 * @since 1.0.0
 * @param int $post (default: null)
 * @return null|string
 */
function get_the_company_website( $post = null ) {
	$post = get_post( $post );

	if ( $post->post_type !== 'job_listing' )
		return;

	$website = $post->_company_website;

	if ( $website && ! strstr( $website, 'http:' ) && ! strstr( $website, 'https:' ) ) {
		$website = 'http://' . $website;
	}

	return apply_filters( 'the_company_website', $website, $post );
}

/**
 * Displays or retrieves the current company tagline with optional content.
 *
 * @since 1.0.0
 * @param string           $before (default: '')
 * @param string           $after (default: '')
 * @param bool             $echo (default: true)
 * @param int|WP_Post|null $post (default: null)
 * @return string|void
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
 * Gets the company tagline.
 *
 * @since 1.0.0
 * @param int|WP_Post|null $post (default: null)
 * @return string|null
 */
function get_the_company_tagline( $post = null ) {
	$post = get_post( $post );

	if ( $post->post_type !== 'job_listing' )
		return;

	return apply_filters( 'the_company_tagline', $post->_company_tagline, $post );
}

/**
 * Displays or retrieves the current company Twitter link with optional content.
 *
 * @since 1.0.0
 * @param string           $before (default: '')
 * @param string           $after (default: '')
 * @param bool             $echo (default: true)
 * @param int|WP_Post|null $post (default: null)
 * @return string|void
 */
function the_company_twitter( $before = '', $after = '', $echo = true, $post = null ) {
	$company_twitter = get_the_company_twitter( $post );

	if ( strlen( $company_twitter ) == 0 )
		return;

	$company_twitter = esc_attr( strip_tags( $company_twitter ) );
	$company_twitter = $before . '<a href="http://twitter.com/' . $company_twitter . '" class="company_twitter" target="_blank">' . $company_twitter . '</a>' . $after;

	if ( $echo )
		echo $company_twitter;
	else
		return $company_twitter;
}

/**
 * Gets the company Twitter link.
 *
 * @since 1.0.0
 * @param int|WP_Post|null $post (default: null)
 * @return string|null
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
 * Outputs the job listing class.
 *
 * @since 1.0.0
 * @param string      $class (default: '')
 * @param int|WP_Post $post_id (default: null)
 */
function job_listing_class( $class = '', $post_id = null ) {
	// Separates classes with a single space, collates classes for post DIV
	echo 'class="' . join( ' ', get_job_listing_class( $class, $post_id ) ) . '"';
}

/**
 * Gets the job listing class.
 *
 * @since 1.0.0
 * @param string      $class
 * @param int|WP_Post $post_id (default: null)
 * @return array
 */
function get_job_listing_class( $class = '', $post_id = null ) {
	if ( ! get_option( 'job_manager_enable_types' ) ) {
		return get_post_class( array( 'job_classes' ), $post_id );
	}

	$post = get_post( $post_id );

	if ( $post->post_type !== 'job_listing' ) {
		return array();
	}

	$classes = array();

	if ( empty( $post ) ) {
		return $classes;
	}

	$classes[] = 'job_listing';
	if ( $job_type = get_the_job_type() ) {
		$classes[] = 'job-type-' . sanitize_title( $job_type->name );
	}

	if ( is_position_filled( $post ) ) {
		$classes[] = 'job_position_filled';
	}

	if ( is_position_featured( $post ) ) {
		$classes[] = 'job_position_featured';
	}

	if ( ! empty( $class ) ) {
		if ( ! is_array( $class ) ) {
			$class = preg_split( '#\s+#', $class );
		}
		$classes = array_merge( $classes, $class );
	}

	return get_post_class( $classes, $post->ID );
}

/**
 * Displays job meta data on the single job page.
 *
 * @since 1.14.0
 */
function job_listing_meta_display() {
	get_job_manager_template( 'content-single-job_listing-meta.php', array() );
}
add_action( 'single_job_listing_start', 'job_listing_meta_display', 20 );

/**
 * Displays job company data on the single job page.
 *
 * @since 1.14.0
 */
function job_listing_company_display() {
	get_job_manager_template( 'content-single-job_listing-company.php', array() );
}
add_action( 'single_job_listing_start', 'job_listing_company_display', 30 );
