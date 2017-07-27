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

	if ( ! $post || 'job_listing' !== $post->post_type ) {
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
 * Get the employment types for the job listing.
 *
 * @since 1.28.0
 *
 * @param WP_Post|int|null $post
 * @return bool|array
 */
function wpjm_get_job_employment_types( $post = null) {
	if ( ! wpjm_job_listing_employment_type_enabled() ) {
		return false;
	}
	$employment_types = array();
	$job_types = wpjm_get_the_job_types( $post );

	if ( ! empty( $job_types ) ) {
		foreach ( $job_types as $job_type ) {
			$employment_type = get_term_meta( $job_type->term_id, 'employment_type', true );
			if ( ! empty( $employment_type ) ) {
				$employment_types[] = $employment_type;
			}
		}
	}

	/**
	 * Filter the employment types for a job listing.
	 *
	 * @since 1.28.0
	 *
	 * @param array            $employment_types Employment types.
	 * @param WP_Post|int|null $post
	 */
	return apply_filters( 'wpjm_get_job_employment_types', array_unique( $employment_types ), $post );
}

/**
 * Returns if we allow indexing of a job listing.
 *
 * @since 1.28.0
 *
 * @param WP_Post|int|null $post
 * @return bool
 */
function wpjm_allow_indexing_job_listing( $post = null ) {
	$post = get_post( $post );

	if ( ! $post || 'job_listing' !== $post->post_type ) {
		return true;
	}

	// Only index job listings that are un-filled and published.
	$index_job_listing = ! is_position_filled( $post ) && 'publish' === $post->post_status;

	/**
	 * Filter if we should allow indexing of job listing.
	 *
	 * @since 1.28.0
	 *
	 * @param bool             $index_job_listing True if we should allow indexing of job listing.
	 * @param WP_Post|int|null $post
	 */
	return apply_filters( 'wpjm_allow_indexing_job_listing', $index_job_listing, $post );
}

/**
 * Returns if we output job listing structured data for a post.
 *
 * @since 1.28.0
 *
 * @param WP_Post|int|null $post
 * @return bool
 */
function wpjm_output_job_listing_structured_data( $post = null ) {
	$post = get_post( $post );

	if ( ! $post || 'job_listing' !== $post->post_type ) {
		return false;
	}

	// Only show structured data for un-filled and published job listings.
	$output_structured_data = ! is_position_filled( $post ) && 'publish' === $post->post_status;

	/**
	 * Filter if we should output structured data.
	 *
	 * @since 1.28.0
	 *
	 * @param bool             $output_structured_data True if we should show structured data for post.
	 * @param WP_Post|int|null $post
	 */
	return apply_filters( 'wpjm_output_job_listing_structured_data', $output_structured_data, $post );
}

/**
 * Gets the structured data for the job listing.
 *
 * @since 1.28.0
 * @see https://developers.google.com/search/docs/data-types/job-postings
 *
 * @param WP_Post|int|null $post
 * @return bool|array False if functionality is disabled; otherwise array of structured data.
 */
function wpjm_get_job_listing_structured_data( $post = null ) {
	$post = get_post( $post );

	if ( ! $post || 'job_listing' !== $post->post_type ) {
		return false;
	}

	$data = array();
	$data['@context'] = 'http://schema.org/';
	$data['@type'] = 'JobPosting';
	$data['datePosted'] = get_post_time( 'c', false, $post );

	$job_expires = get_post_meta( $post->ID, '_job_expires', true );
	if ( ! empty( $job_expires ) ) {
		$data[ 'validThrough' ] = date( 'c', strtotime( $job_expires ) );
	}

	$data['title'] = strip_tags( wpjm_get_the_job_title( $post ) );
	$data['description'] = wpjm_get_the_job_description( $post );

	$employmentTypes = wpjm_get_job_employment_types();
	if ( ! empty( $employmentTypes ) ) {
		$data['employmentType'] = $employmentTypes;
	}

	$data['hiringOrganization'] = array();
	$data['hiringOrganization']['@type'] = 'Organization';
	$data['hiringOrganization']['name'] = get_the_company_name( $post );
	if ( $company_website = get_the_company_website( $post ) ) {
		$data['hiringOrganization']['sameAs'] = $company_website;
		$data['hiringOrganization']['url'] = $company_website;
	}

	$data['identifier'] = array();
	$data['identifier']['@type'] = 'PropertyValue';
	$data['identifier']['name'] = get_the_company_name( $post );
	$data['identifier']['value'] = get_the_guid( $post );

	$location = get_the_job_location( $post );
	if ( ! empty( $location ) ) {
		$data['jobLocation'] = array();
		$data['jobLocation']['@type'] = 'Place';
		$data['jobLocation']['address'] = wpjm_get_job_listing_location_structured_data( $post );
		if ( empty( $data['jobLocation']['address'] ) ) {
			$data['jobLocation']['address'] = $location;
		}
	}

	/**
	 * Filter the structured data for a job listing.
	 *
	 * @since 1.28.0
	 *
	 * @param bool|array $structured_data False if functionality is disabled; otherwise array of structured data.
	 * @param WP_Post    $post
	 */
	return apply_filters( 'wpjm_get_job_listing_structured_data', $data, $post );
}

/**
 * Gets the job listing location data.
 *
 * @see http://schema.org/PostalAddress
 *
 * @param WP_Post $post
 * @return array|bool
 */
function wpjm_get_job_listing_location_structured_data( $post ) {
	$post = get_post( $post );

	if ( ! $post || 'job_listing' !== $post->post_type ) {
		return false;
	}

	$mapping = array();
	$mapping['streetAddress'] = array( 'street_number', 'street' );
	$mapping['addressLocality'] = 'city';
	$mapping['addressRegion'] = 'state_short';
	$mapping['postalCode'] = 'postcode';
	$mapping['addressCountry'] = 'country_short';

	$address = array();
	$address['@type'] = 'PostalAddress';
	foreach ( $mapping as $schema_key => $geolocation_key ) {
		if ( is_array( $geolocation_key ) ) {
			$values = array();
			foreach ( $geolocation_key as $sub_geo_key ) {
				$geo_value = get_post_meta( $post->ID, 'geolocation_' . $sub_geo_key, true );
				if ( ! empty( $geo_value ) ) {
					$values[] = $geo_value;
				}
			}
			$value = implode( ' ', $values );
		} else {
			$value = get_post_meta( $post->ID, 'geolocation_' . $geolocation_key, true );
		}
		if ( ! empty( $value ) ) {
			$address[ $schema_key ] = $value;
		}
	}

	// No address parts were found
	if ( 1 === count( $address ) ) {
		$address = false;
	}

	/**
	 * Gets the job listing location structured data.
	 *
	 * @since 1.28.0
	 *
	 * @param array|bool $address Array of address data.
	 * @param WP_Post    $post
	 */
	return apply_filters( 'wpjm_get_job_listing_location_structured_data', $address, $post );
}

/**
 * Displays the job title for the listing.
 *
 * @since 1.27.0
 * @param int|WP_Post $post
 * @return string
 */
function wpjm_the_job_title( $post = null ) {
	if ( $job_title = wpjm_get_the_job_title( $post ) ) {
		echo $job_title;
	}
}

/**
 * Gets the job title for the listing.
 *
 * @since 1.27.0
 * @param int|WP_Post $post (default: null)
 * @return string|bool|null
 */
function wpjm_get_the_job_title( $post = null ) {
	$post = get_post( $post );
	if ( ! $post || 'job_listing' !== $post->post_type ) {
		return;
	}

	$title = esc_html( get_the_title( $post ) );

	/**
	 * Filter for the job title.
	 *
	 * @since 1.27.0
	 * @param string      $title Title to be filtered.
	 * @param int|WP_Post $post
	 */
	return apply_filters( 'wpjm_the_job_title', $title, $post );
}

/**
 * Displays the job description for the listing.
 *
 * @since 1.28.0
 * @param int|WP_Post $post
 * @return string
 */
function wpjm_the_job_description( $post = null ) {
	if ( $job_description = wpjm_get_the_job_description( $post ) ) {
		echo $job_description;
	}
}

/**
 * Gets the job description for the listing.
 *
 * @since 1.28.0
 * @param int|WP_Post $post (default: null)
 * @return string|bool|null
 */
function wpjm_get_the_job_description( $post = null ) {
	$post = get_post( $post );
	if ( ! $post || 'job_listing' !== $post->post_type ) {
		return;
	}

	$description = apply_filters( 'the_job_description', get_the_content( $post ) );

	/**
	 * Filter for the job description.
	 *
	 * @since 1.28.0
	 * @param string      $title Title to be filtered.
	 * @param int|WP_Post $post
	 */
	return apply_filters( 'wpjm_the_job_description', $description, $post );
}

/**
 * Displays multiple job types for the listing.
 *
 * @since 1.27.0
 *
 * @param int|WP_Post $post Current post object.
 * @param string      $separator String to join the term names with.
 */
function wpjm_the_job_types( $post = null, $separator = ', ' ) {
	if ( ! get_option( 'job_manager_enable_types' ) ) {
		return;
	}

	$job_types = wpjm_get_the_job_types( $post );

	if ( $job_types ) {
		$names = wp_list_pluck( $job_types, 'name' );

		echo esc_html( implode( $separator, $names ) );
	}
}

/**
 * Gets the job type for the listing.
 *
 * @since 1.27.0
 *
 * @param int|WP_Post $post (default: null).
 * @return bool|array
 */
function wpjm_get_the_job_types( $post = null ) {
	$post = get_post( $post );

	if ( ! $post || 'job_listing' !== $post->post_type ) {
		return false;
	}

	$types = get_the_terms( $post->ID, 'job_listing_type' );

	// Return single if not enabled.
	if ( ! empty( $types ) && ! job_manager_multi_job_type() ) {
		$types = array( current( $types ) );
	}

	/**
	 * Filter the returned job types for a post.
	 *
	 * @since 1.27.0
	 *
	 * @param array   $types
	 * @param WP_Post $post
	 */
	return apply_filters( 'wpjm_the_job_types', $types, $post );
}

/**
 * Returns the registration fields used when an account is required.
 *
 * @since 1.27.0
 *
 * @return array $registration_fields
 */
function wpjm_get_registration_fields() {
	$generate_username_from_email      = job_manager_generate_username_from_email();
	$use_standard_password_setup_email = wpjm_use_standard_password_setup_email();
	$account_required  = job_manager_user_requires_account();

	$registration_fields = array();
	if ( job_manager_enable_registration() ) {
		if ( ! $generate_username_from_email ) {
			$registration_fields['create_account_username'] = array(
				'type'     => 'text',
				'label'    => __( 'Username', 'wp-job-manager' ),
				'required' => $account_required,
				'value'    => isset( $_POST['create_account_username'] ) ? $_POST['create_account_username'] : '',
			);
		}
		if ( ! $use_standard_password_setup_email ) {
			$registration_fields['create_account_password'] = array(
				'type'         => 'password',
				'label'        => __( 'Password', 'wp-job-manager' ),
				'autocomplete' => false,
				'required'     => $account_required,
			);
			$password_hint = wpjm_get_password_rules_hint();
			if ( $password_hint ) {
				$registration_fields['create_account_password']['description'] = $password_hint;
			}
			$registration_fields['create_account_password_verify'] = array(
				'type'         => 'password',
				'label'        => __( 'Verify Password', 'wp-job-manager' ),
				'autocomplete' => false,
				'required'     => $account_required,
			);
		}
		$registration_fields['create_account_email'] = array(
			'type'        => 'text',
			'label'       => __( 'Your email', 'wp-job-manager' ),
			'placeholder' => __( 'you@yourdomain.com', 'wp-job-manager' ),
			'required'    => $account_required,
			'value'       => isset( $_POST['create_account_email'] ) ? $_POST['create_account_email'] : '',
		);
	}

	/**
	 * Filters the fields used at registration.
	 *
	 * @since 1.27.0
	 *
	 * @param array $registration_fields
	 */
	return apply_filters( 'wpjm_get_registration_fields', $registration_fields );
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
		$display_date = __( 'Posted on ', 'wp-job-manager' ) . date_i18n( get_option( 'date_format' ), get_post_time( 'U' ) );
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
	if ( ! $post || 'job_listing' !== $post->post_type ) {
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
	if ( ! $post || 'job_listing' !== $post->post_type ) {
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
	if ( ! $post || 'job_listing' !== $post->post_type ) {
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

	if ( ! $post || 'job_listing' !== $post->post_type )
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

	if ( ! $post || 'job_listing' !== $post->post_type )
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
	if ( ! $post || 'job_listing' !== $post->post_type )
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
	$post = get_post( $post_id );

	if (  empty( $post ) || 'job_listing' !== $post->post_type ) {
		return array();
	}

	$classes = array();

	if ( ! empty( $class ) ) {
		if ( ! is_array( $class ) ) {
			$class = preg_split( '#\s+#', $class );
		}
		$classes = array_merge( $classes, $class );
	}

	return get_post_class( $classes, $post->ID );
}

/**
 * Adds post classes with meta info and the status of the job listing.
 *
 * @since 1.27.0
 *
 * @param array $classes An array of post classes.
 * @param array $class   An array of additional classes added to the post.
 * @param int   $post_id The post ID.
 * @return array
 */
function wpjm_add_post_class( $classes, $class, $post_id ) {
	$post = get_post( $post_id );

	if (  empty( $post ) || 'job_listing' !== $post->post_type ) {
		return $classes;
	}

	$classes[] = 'job_listing';

	if ( get_option( 'job_manager_enable_types' ) ) {
		$job_types = wpjm_get_the_job_types( $post );
		if ( ! empty( $job_types ) ) {
			foreach ( $job_types as $job_type ) {
				$classes[] = 'job-type-' . sanitize_title( $job_type->name );
			}
		}
	}

	if ( is_position_filled( $post ) ) {
		$classes[] = 'job_position_filled';
	}

	if ( is_position_featured( $post ) ) {
		$classes[] = 'job_position_featured';
	}

	return $classes;
}
add_action( 'post_class', 'wpjm_add_post_class', 10, 3 );

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
