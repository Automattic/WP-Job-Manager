<?php
/**
 * WP_Job_Manager_Content class.
 */
class WP_Job_Manager_Post_Types {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_types' ), 0 );
		add_filter( 'admin_head', array( $this, 'admin_head' ) );
		add_action( 'job_manager_check_for_expired_jobs', array( $this, 'check_for_expired_jobs' ) );
		add_action( 'job_manager_delete_old_previews', array( $this, 'delete_old_previews' ) );

		add_action( 'pending_to_publish', array( $this, 'set_expiry' ) );
		add_action( 'preview_to_publish', array( $this, 'set_expiry' ) );
		add_action( 'draft_to_publish', array( $this, 'set_expiry' ) );
		add_action( 'auto-draft_to_publish', array( $this, 'set_expiry' ) );
		add_action( 'expired_to_publish', array( $this, 'set_expiry' ) );

		add_filter( 'the_job_description', 'wptexturize'        );
		add_filter( 'the_job_description', 'convert_smilies'    );
		add_filter( 'the_job_description', 'convert_chars'      );
		add_filter( 'the_job_description', 'wpautop'            );
		add_filter( 'the_job_description', 'shortcode_unautop'  );
		add_filter( 'the_job_description', 'prepend_attachment' );
		if ( ! empty( $GLOBALS['wp_embed'] ) ) {
			add_filter( 'the_job_description', array( $GLOBALS['wp_embed'], 'run_shortcode' ), 8 );
			add_filter( 'the_job_description', array( $GLOBALS['wp_embed'], 'autoembed' ), 8 );
		}

		add_action( 'job_manager_application_details_email', array( $this, 'application_details_email' ) );
		add_action( 'job_manager_application_details_url', array( $this, 'application_details_url' ) );

		add_filter( 'wp_insert_post_data', array( $this, 'fix_post_name' ), 10, 2 );
		add_action( 'add_post_meta', array( $this, 'maybe_add_geolocation_data' ), 10, 3 );
		add_action( 'update_post_meta', array( $this, 'update_post_meta' ), 10, 4 );
		add_action( 'wp_insert_post', array( $this, 'maybe_add_default_meta_data' ), 10, 2 );

		// WP ALL Import
		add_action( 'pmxi_saved_post', array( $this, 'pmxi_saved_post' ), 10, 1 );

		// RP4WP
		add_filter( 'rp4wp_get_template', array( $this, 'rp4wp_template' ), 10, 3 );
		add_filter( 'rp4wp_related_meta_fields', array( $this, 'rp4wp_related_meta_fields' ), 10, 3 );
		add_filter( 'rp4wp_related_meta_fields_weight', array( $this, 'rp4wp_related_meta_fields_weight' ), 10, 3 );

		// Single job content
		$this->job_content_filter( true );
	}

	/**
	 * register_post_types function.
	 *
	 * @access public
	 * @return void
	 */
	public function register_post_types() {
		if ( post_type_exists( "job_listing" ) )
			return;

		$admin_capability = 'manage_job_listings';

		/**
		 * Taxonomies
		 */
		if ( get_option( 'job_manager_enable_categories' ) ) {
			$singular  = __( 'Job category', 'wp-job-manager' );
			$plural    = __( 'Job categories', 'wp-job-manager' );

			if ( current_theme_supports( 'job-manager-templates' ) ) {
				$rewrite   = array(
					'slug'         => _x( 'job-category', 'Job category slug - resave permalinks after changing this', 'wp-job-manager' ),
					'with_front'   => false,
					'hierarchical' => false
				);
				$public    = true;
			} else {
				$rewrite   = false;
				$public    = false;
			}

			register_taxonomy( "job_listing_category",
				apply_filters( 'register_taxonomy_job_listing_category_object_type', array( 'job_listing' ) ),
	       	 	apply_filters( 'register_taxonomy_job_listing_category_args', array(
		            'hierarchical' 			=> true,
		            'update_count_callback' => '_update_post_term_count',
		            'label' 				=> $plural,
		            'labels' => array(
						'name'              => $plural,
						'singular_name'     => $singular,
						'menu_name'         => ucwords( $plural ),
						'search_items'      => sprintf( __( 'Search %s', 'wp-job-manager' ), $plural ),
						'all_items'         => sprintf( __( 'All %s', 'wp-job-manager' ), $plural ),
						'parent_item'       => sprintf( __( 'Parent %s', 'wp-job-manager' ), $singular ),
						'parent_item_colon' => sprintf( __( 'Parent %s:', 'wp-job-manager' ), $singular ),
						'edit_item'         => sprintf( __( 'Edit %s', 'wp-job-manager' ), $singular ),
						'update_item'       => sprintf( __( 'Update %s', 'wp-job-manager' ), $singular ),
						'add_new_item'      => sprintf( __( 'Add New %s', 'wp-job-manager' ), $singular ),
						'new_item_name'     => sprintf( __( 'New %s Name', 'wp-job-manager' ),  $singular )
	            	),
		            'show_ui' 				=> true,
		            'public' 	     		=> $public,
		            'capabilities'			=> array(
		            	'manage_terms' 		=> $admin_capability,
		            	'edit_terms' 		=> $admin_capability,
		            	'delete_terms' 		=> $admin_capability,
		            	'assign_terms' 		=> $admin_capability,
		            ),
		            'rewrite' 				=> $rewrite,
		        ) )
		    );
		}

	    $singular  = __( 'Job type', 'wp-job-manager' );
		$plural    = __( 'Job types', 'wp-job-manager' );

		if ( current_theme_supports( 'job-manager-templates' ) ) {
			$rewrite   = array(
				'slug'         => _x( 'job-type', 'Job type slug - resave permalinks after changing this', 'wp-job-manager' ),
				'with_front'   => false,
				'hierarchical' => false
			);
			$public    = true;
		} else {
			$rewrite   = false;
			$public    = false;
		}

		register_taxonomy( "job_listing_type",
			apply_filters( 'register_taxonomy_job_listing_type_object_type', array( 'job_listing' ) ),
	        apply_filters( 'register_taxonomy_job_listing_type_args', array(
	            'hierarchical' 			=> true,
	            'label' 				=> $plural,
	            'labels' => array(
                    'name' 				=> $plural,
                    'singular_name' 	=> $singular,
                    'menu_name'         => ucwords( $plural ),
                    'search_items' 		=> sprintf( __( 'Search %s', 'wp-job-manager' ), $plural ),
                    'all_items' 		=> sprintf( __( 'All %s', 'wp-job-manager' ), $plural ),
                    'parent_item' 		=> sprintf( __( 'Parent %s', 'wp-job-manager' ), $singular ),
                    'parent_item_colon' => sprintf( __( 'Parent %s:', 'wp-job-manager' ), $singular ),
                    'edit_item' 		=> sprintf( __( 'Edit %s', 'wp-job-manager' ), $singular ),
                    'update_item' 		=> sprintf( __( 'Update %s', 'wp-job-manager' ), $singular ),
                    'add_new_item' 		=> sprintf( __( 'Add New %s', 'wp-job-manager' ), $singular ),
                    'new_item_name' 	=> sprintf( __( 'New %s Name', 'wp-job-manager' ),  $singular )
            	),
	            'show_ui' 				=> true,
	            'public' 			    => $public,
	            'capabilities'			=> array(
	            	'manage_terms' 		=> $admin_capability,
	            	'edit_terms' 		=> $admin_capability,
	            	'delete_terms' 		=> $admin_capability,
	            	'assign_terms' 		=> $admin_capability,
	            ),
	           'rewrite' 				=> $rewrite,
	        ) )
	    );

	    /**
		 * Post types
		 */
		$singular  = __( 'Job', 'wp-job-manager' );
		$plural    = __( 'Jobs', 'wp-job-manager' );

		if ( current_theme_supports( 'job-manager-templates' ) ) {
			$has_archive = _x( 'jobs', 'Post type archive slug - resave permalinks after changing this', 'wp-job-manager' );
		} else {
			$has_archive = false;
		}

		$rewrite     = array(
			'slug'       => _x( 'job', 'Job permalink - resave permalinks after changing this', 'wp-job-manager' ),
			'with_front' => false,
			'feeds'      => true,
			'pages'      => false
		);

		register_post_type( "job_listing",
			apply_filters( "register_post_type_job_listing", array(
				'labels' => array(
					'name' 					=> $plural,
					'singular_name' 		=> $singular,
					'menu_name'             => __( 'Job Listings', 'wp-job-manager' ),
					'all_items'             => sprintf( __( 'All %s', 'wp-job-manager' ), $plural ),
					'add_new' 				=> __( 'Add New', 'wp-job-manager' ),
					'add_new_item' 			=> sprintf( __( 'Add %s', 'wp-job-manager' ), $singular ),
					'edit' 					=> __( 'Edit', 'wp-job-manager' ),
					'edit_item' 			=> sprintf( __( 'Edit %s', 'wp-job-manager' ), $singular ),
					'new_item' 				=> sprintf( __( 'New %s', 'wp-job-manager' ), $singular ),
					'view' 					=> sprintf( __( 'View %s', 'wp-job-manager' ), $singular ),
					'view_item' 			=> sprintf( __( 'View %s', 'wp-job-manager' ), $singular ),
					'search_items' 			=> sprintf( __( 'Search %s', 'wp-job-manager' ), $plural ),
					'not_found' 			=> sprintf( __( 'No %s found', 'wp-job-manager' ), $plural ),
					'not_found_in_trash' 	=> sprintf( __( 'No %s found in trash', 'wp-job-manager' ), $plural ),
					'parent' 				=> sprintf( __( 'Parent %s', 'wp-job-manager' ), $singular ),
					'featured_image'        => __( 'Company Logo', 'woocommerce' ),
					'set_featured_image'    => __( 'Set company logo', 'woocommerce' ),
					'remove_featured_image' => __( 'Remove company logo', 'woocommerce' ),
					'use_featured_image'    => __( 'Use as company logo', 'woocommerce' ),
				),
				'description' => sprintf( __( 'This is where you can create and manage %s.', 'wp-job-manager' ), $plural ),
				'public' 				=> true,
				'show_ui' 				=> true,
				'capability_type' 		=> 'job_listing',
				'map_meta_cap'          => true,
				'publicly_queryable' 	=> true,
				'exclude_from_search' 	=> false,
				'hierarchical' 			=> false,
				'rewrite' 				=> $rewrite,
				'query_var' 			=> true,
				'supports' 				=> array( 'title', 'editor', 'custom-fields', 'publicize', 'thumbnail' ),
				'has_archive' 			=> $has_archive,
				'show_in_nav_menus' 	=> false
			) )
		);

		/**
		 * Feeds
		 */
		add_feed( 'job_feed', array( $this, 'job_feed' ) );

		/**
		 * Post status
		 */
		register_post_status( 'expired', array(
			'label'                     => _x( 'Expired', 'post status', 'wp-job-manager' ),
			'public'                    => true,
			'protected'                 => true,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'wp-job-manager' ),
		) );
		register_post_status( 'preview', array(
			'label'                     => _x( 'Preview', 'post status', 'wp-job-manager' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Preview <span class="count">(%s)</span>', 'Preview <span class="count">(%s)</span>', 'wp-job-manager' ),
		) );
	}

	/**
	 * Change label
	 */
	public function admin_head() {
		global $menu;

		$plural     = __( 'Job Listings', 'wp-job-manager' );
		$count_jobs = wp_count_posts( 'job_listing', 'readable' );

		if ( ! empty( $menu ) && is_array( $menu ) ) {
			foreach ( $menu as $key => $menu_item ) {
				if ( strpos( $menu_item[0], $plural ) === 0 ) {
					if ( $order_count = $count_jobs->pending ) {
						$menu[ $key ][0] .= " <span class='awaiting-mod update-plugins count-$order_count'><span class='pending-count'>" . number_format_i18n( $count_jobs->pending ) . "</span></span>" ;
					}
					break;
				}
			}
		}
	}

	/**
	 * Toggle filter on and off
	 */
	private function job_content_filter( $enable ) {
		if ( ! $enable ) {
			remove_filter( 'the_content', array( $this, 'job_content' ) );
		} else {
			add_filter( 'the_content', array( $this, 'job_content' ) );
		}
	}

	/**
	 * Add extra content before/after the post for single job listings.
	 */
	public function job_content( $content ) {
		global $post;

		if ( ! is_singular( 'job_listing' ) || ! in_the_loop() || 'job_listing' !== $post->post_type ) {
			return $content;
		}

		ob_start();

		$this->job_content_filter( false );

		do_action( 'job_content_start' );

		get_job_manager_template_part( 'content-single', 'job_listing' );

		do_action( 'job_content_end' );

		$this->job_content_filter( true );

		return apply_filters( 'job_manager_single_job_content', ob_get_clean(), $post );
	}

	/**
	 * Job listing feeds
	 */
	public function job_feed() {
		$query_args = array(
			'post_type'           => 'job_listing',
			'post_status'         => 'publish',
			'ignore_sticky_posts' => 1,
			'posts_per_page'      => isset( $_GET['posts_per_page'] ) ? absint( $_GET['posts_per_page'] ) : 10,
			'tax_query'           => array(),
			'meta_query'          => array()
		);

		if ( ! empty( $_GET['search_location'] ) ) {
			$location_meta_keys = array( 'geolocation_formatted_address', '_job_location', 'geolocation_state_long' );
			$location_search    = array( 'relation' => 'OR' );
			foreach ( $location_meta_keys as $meta_key ) {
				$location_search[] = array(
					'key'     => $meta_key,
					'value'   => sanitize_text_field( $_GET['search_location'] ),
					'compare' => 'like'
				);
			}
			$query_args['meta_query'][] = $location_search;
		}

		if ( ! empty( $_GET['job_types'] ) ) {
			$query_args['tax_query'][] = array(
				'taxonomy' => 'job_listing_type',
				'field'    => 'slug',
				'terms'    => explode( ',', sanitize_text_field( $_GET['job_types'] ) ) + array( 0 )
			);
		}

		if ( ! empty( $_GET['job_categories'] ) ) {
			$cats     = explode( ',', sanitize_text_field( $_GET['job_categories'] ) ) + array( 0 );
			$field    = is_numeric( $cats ) ? 'term_id' : 'slug';
			$operator = 'all' === get_option( 'job_manager_category_filter_type', 'all' ) && sizeof( $args['search_categories'] ) > 1 ? 'AND' : 'IN';
			$query_args['tax_query'][] = array(
				'taxonomy'         => 'job_listing_category',
				'field'            => $field,
				'terms'            => $cats,
				'include_children' => $operator !== 'AND' ,
				'operator'         => $operator
			);
		}

		if ( $job_manager_keyword = sanitize_text_field( $_GET['search_keywords'] ) ) {
			$query_args['_keyword'] = $job_manager_keyword; // Does nothing but needed for unique hash
			add_filter( 'posts_clauses', 'get_job_listings_keyword_search' );
		}

		if ( empty( $query_args['meta_query'] ) ) {
			unset( $query_args['meta_query'] );
		}

		if ( empty( $query_args['tax_query'] ) ) {
			unset( $query_args['tax_query'] );
		}

		query_posts( apply_filters( 'job_feed_args', $query_args ) );
		add_action( 'rss2_ns', array( $this, 'job_feed_namespace' ) );
		add_action( 'rss2_item', array( $this, 'job_feed_item' ) );
		do_feed_rss2( false );
	}

	/**
	 * Add a custom namespace to the job feed
	 */
	public function job_feed_namespace() {
		echo 'xmlns:job_listing="' .  site_url() . '"' . "\n";
	}

	/**
	 * Add custom data to the job feed
	 */
	public function job_feed_item() {
		$post_id  = get_the_ID();
		$location = get_the_job_location( $post_id );
		$job_type = get_the_job_type( $post_id );
		$company  = get_the_company_name( $post_id );

		if ( $location ) {
			echo "<job_listing:location><![CDATA[" . esc_html( $location ) . "]]></job_listing:location>\n";
		}
		if ( $job_type ) {
			echo "<job_listing:job_type><![CDATA[" . esc_html( $job_type->name ) . "]]></job_listing:job_type>\n";
		}
		if ( $company ) {
			echo "<job_listing:company><![CDATA[" . esc_html( $company ) . "]]></job_listing:company>\n";
		}
	}

	/**
	 * Expire jobs
	 */
	public function check_for_expired_jobs() {
		global $wpdb;

		// Change status to expired
		$job_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT postmeta.post_id FROM {$wpdb->postmeta} as postmeta
			LEFT JOIN {$wpdb->posts} as posts ON postmeta.post_id = posts.ID
			WHERE postmeta.meta_key = '_job_expires'
			AND postmeta.meta_value > 0
			AND postmeta.meta_value < %s
			AND posts.post_status = 'publish'
			AND posts.post_type = 'job_listing'
		", date( 'Y-m-d', current_time( 'timestamp' ) ) ) );

		if ( $job_ids ) {
			foreach ( $job_ids as $job_id ) {
				$job_data       = array();
				$job_data['ID'] = $job_id;
				$job_data['post_status'] = 'expired';
				wp_update_post( $job_data );
			}
		}

		// Delete old expired jobs
		if ( apply_filters( 'job_manager_delete_expired_jobs', false ) ) {
			$job_ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT posts.ID FROM {$wpdb->posts} as posts
				WHERE posts.post_type = 'job_listing'
				AND posts.post_modified < %s
				AND posts.post_status = 'expired'
			", date( 'Y-m-d', strtotime( '-' . apply_filters( 'job_manager_delete_expired_jobs_days', 30 ) . ' days', current_time( 'timestamp' ) ) ) ) );

			if ( $job_ids ) {
				foreach ( $job_ids as $job_id ) {
					wp_trash_post( $job_id );
				}
			}
		}
	}

	/**
	 * Delete old previewed jobs after 30 days to keep the DB clean
	 */
	public function delete_old_previews() {
		global $wpdb;

		// Delete old expired jobs
		$job_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT posts.ID FROM {$wpdb->posts} as posts
			WHERE posts.post_type = 'job_listing'
			AND posts.post_modified < %s
			AND posts.post_status = 'preview'
		", date( 'Y-m-d', strtotime( '-30 days', current_time( 'timestamp' ) ) ) ) );

		if ( $job_ids ) {
			foreach ( $job_ids as $job_id ) {
				wp_delete_post( $job_id, true );
			}
		}
	}

	/**
	 * Typo -.-
	 */
	public function set_expirey( $post ) {
		$this->set_expiry( $post );
	}

	/**
	 * Set expirey date when job status changes
	 */
	public function set_expiry( $post ) {
		if ( $post->post_type !== 'job_listing' ) {
			return;
		}

		// See if it is already set
		if ( metadata_exists( 'post', $post->ID, '_job_expires' ) ) {
			$expires = get_post_meta( $post->ID, '_job_expires', true );
			if ( $expires && strtotime( $expires ) < current_time( 'timestamp' ) ) {
				update_post_meta( $post->ID, '_job_expires', '' );
				$_POST[ '_job_expires' ] = '';
			}
			return;
		}

		// No metadata set so we can generate an expiry date
		// See if the user has set the expiry manually:
		if ( ! empty( $_POST[ '_job_expires' ] ) ) {
			update_post_meta( $post->ID, '_job_expires', date( 'Y-m-d', strtotime( sanitize_text_field( $_POST[ '_job_expires' ] ) ) ) );

		// No manual setting? Lets generate a date
		} else {
			$expires = calculate_job_expiry( $post->ID );
			update_post_meta( $post->ID, '_job_expires', $expires );

			// In case we are saving a post, ensure post data is updated so the field is not overridden
			if ( isset( $_POST[ '_job_expires' ] ) ) {
				$_POST[ '_job_expires' ] = $expires;
			}
		}
	}

	/**
	 * The application content when the application method is an email
	 */
	public function application_details_email( $apply ) {
		get_job_manager_template( 'job-application-email.php', array( 'apply' => $apply ) );
	}

	/**
	 * The application content when the application method is a url
	 */
	public function application_details_url( $apply ) {
		get_job_manager_template( 'job-application-url.php', array( 'apply' => $apply ) );
	}

	/**
	 * Fix post name when wp_update_post changes it
	 * @param  array $data
	 * @return array
	 */
	public function fix_post_name( $data, $postarr ) {
		 if ( 'job_listing' === $data['post_type'] && 'pending' === $data['post_status'] && ! current_user_can( 'publish_posts' ) ) {
				$data['post_name'] = $postarr['post_name'];
		 }
		 return $data;
	}

	/**
	 * Generate location data if a post is added
	 * @param  int $post_id
	 * @param  array $post
	 */
	public function maybe_add_geolocation_data( $object_id, $meta_key, $meta_value ) {
		if ( '_job_location' !== $meta_key || 'job_listing' !== get_post_type( $object_id ) ) {
			return;
		}
		do_action( 'job_manager_job_location_edited', $object_id, $meta_value );
	}

	/**
	 * Triggered when updating meta on a job listing
	 */
	public function update_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( 'job_listing' === get_post_type( $object_id ) ) {
			switch ( $meta_key ) {
				case '_job_location' :
					$this->maybe_update_geolocation_data( $meta_id, $object_id, $meta_key, $meta_value );
				break;
				case '_featured' :
					$this->maybe_update_menu_order( $meta_id, $object_id, $meta_key, $meta_value );
				break;
			}
		}
	}

	/**
	 * Generate location data if a post is updated
	 */
	public function maybe_update_geolocation_data( $meta_id, $object_id, $meta_key, $meta_value ) {
		do_action( 'job_manager_job_location_edited', $object_id, $meta_value );
	}

	/**
	 * Maybe set menu_order if the featured status of a job is changed
	 */
	public function maybe_update_menu_order( $meta_id, $object_id, $meta_key, $meta_value ) {
		global $wpdb;

		if ( '1' == $meta_value ) {
			$wpdb->update( $wpdb->posts, array( 'menu_order' => -1 ), array( 'ID' => $object_id ) );
		} else {
			$wpdb->update( $wpdb->posts, array( 'menu_order' => 0 ), array( 'ID' => $object_id, 'menu_order' => -1 ) );
		}

		clean_post_cache( $object_id );
	}

	/**
	 * Legacy
	 * @deprecated 1.19.1
	 */
	public function maybe_generate_geolocation_data( $meta_id, $object_id, $meta_key, $meta_value ) {
		$this->maybe_update_geolocation_data( $meta_id, $object_id, $meta_key, $meta_value );
	}

	/**
	 * Maybe set default meta data for job listings
	 * @param  int $post_id
	 * @param  WP_Post $post
	 */
	public function maybe_add_default_meta_data( $post_id, $post = '' ) {
		if ( empty( $post ) || 'job_listing' === $post->post_type ) {
			add_post_meta( $post_id, '_filled', 0, true );
			add_post_meta( $post_id, '_featured', 0, true );
		}
	}

	/**
	 * After importing via WP ALL Import, add default meta data
	 * @param  int $post_id
	 */
	public function pmxi_saved_post( $post_id ) {
		if ( 'job_listing' === get_post_type( $post_id ) ) {
			$this->maybe_add_default_meta_data( $post_id );
			if ( ! WP_Job_Manager_Geocode::has_location_data( $post_id ) && ( $location = get_post_meta( $post_id, '_job_location', true ) ) ) {
				WP_Job_Manager_Geocode::generate_location_data( $post_id, $location );
			}
		}
	}

	/**
	 * Replace RP4WP template with the template from Job Manager
	 * @param  string $located
	 * @param  string $template_name
	 * @param  array $args
	 * @return string
	 */
	public function rp4wp_template( $located, $template_name, $args ) {
		if ( 'related-post-default.php' === $template_name && 'job_listing' === $args['related_post']->post_type ) {
			return JOB_MANAGER_PLUGIN_DIR . '/templates/content-job_listing.php';
		}
		return $located;
	}

	/**
	 * Add meta fields for RP4WP to relate jobs by
	 * @param  array $meta_fields
	 * @param  int $post_id
	 * @param  WP_Post $post
	 * @return array
	 */
	public function rp4wp_related_meta_fields( $meta_fields, $post_id, $post ) {
		if ( 'job_listing' === $post->post_type ) {
			$meta_fields[] = '_company_name';
			$meta_fields[] = '_job_location';
		}
		return $meta_fields;
	}

	/**
	 * Add meta fields for RP4WP to relate jobs by
	 * @param  int $weight
	 * @param  WP_Post $post
	 * @param  string $meta_field
	 * @return int
	 */
	public function rp4wp_related_meta_fields_weight( $weight, $post, $meta_field ) {
		if ( 'job_listing' === $post->post_type ) {
			$weight = 100;
		}
		return $weight;
	}
}
