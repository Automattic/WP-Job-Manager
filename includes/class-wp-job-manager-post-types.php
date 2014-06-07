<?php
/**
 * WP_Job_Manager_Content class.
 */
class WP_Job_Manager_Post_Types {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_filter( 'admin_head', array( $this, 'admin_head' ) );
		add_filter( 'the_content', array( $this, 'job_content' ) );
		add_action( 'job_manager_check_for_expired_jobs', array( $this, 'check_for_expired_jobs' ) );
		add_action( 'job_manager_delete_old_previews', array( $this, 'delete_old_previews' ) );
		add_action( 'pending_to_publish', array( $this, 'set_expirey' ) );
		add_action( 'preview_to_publish', array( $this, 'set_expirey' ) );
		add_action( 'draft_to_publish', array( $this, 'set_expirey' ) );
		add_action( 'auto-draft_to_publish', array( $this, 'set_expirey' ) );
		add_action( 'expired_to_publish', array( $this, 'set_expirey' ) );

		add_filter( 'the_job_description', 'wptexturize'        );
		add_filter( 'the_job_description', 'convert_smilies'    );
		add_filter( 'the_job_description', 'convert_chars'      );
		add_filter( 'the_job_description', 'wpautop'            );
		add_filter( 'the_job_description', 'shortcode_unautop'  );
		add_filter( 'the_job_description', 'prepend_attachment' );

		add_action( 'job_manager_application_details_email', array( $this, 'application_details_email' ) );
		add_action( 'job_manager_application_details_url', array( $this, 'application_details_url' ) );
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
			$singular  = __( 'Job Category', 'wp-job-manager' );
			$plural    = __( 'Job Categories', 'wp-job-manager' );

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
		        array( "job_listing" ),
		        array(
		            'hierarchical' 			=> true,
		            'update_count_callback' => '_update_post_term_count',
		            'label' 				=> $plural,
		            'labels' => array(
	                    'name' 				=> $plural,
	                    'singular_name' 	=> $singular,
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
		            'public' 	     		=> $public,
		            'capabilities'			=> array(
		            	'manage_terms' 		=> $admin_capability,
		            	'edit_terms' 		=> $admin_capability,
		            	'delete_terms' 		=> $admin_capability,
		            	'assign_terms' 		=> $admin_capability,
		            ),
		            'rewrite' 				=> $rewrite,
		        )
		    );
		}

	    $singular  = __( 'Job Type', 'wp-job-manager' );
		$plural    = __( 'Job Types', 'wp-job-manager' );

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
	        array( "job_listing" ),
	        array(
	            'hierarchical' 			=> true,
	            'label' 				=> $plural,
	            'labels' => array(
                    'name' 				=> $plural,
                    'singular_name' 	=> $singular,
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
	        )
	    );

	    /**
		 * Post types
		 */
		$singular  = __( 'Job Listing', 'wp-job-manager' );
		$plural    = __( 'Job Listings', 'wp-job-manager' );

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
					'menu_name'             => $plural,
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
					'parent' 				=> sprintf( __( 'Parent %s', 'wp-job-manager' ), $singular )
				),
				'description' => __( 'This is where you can create and manage job listings.', 'wp-job-manager' ),
				'public' 				=> true,
				'show_ui' 				=> true,
				'capability_type' 		=> 'post',
				'capabilities' => array(
					'publish_posts' 		=> $admin_capability,
					'edit_posts' 			=> $admin_capability,
					'edit_others_posts' 	=> $admin_capability,
					'delete_posts' 			=> $admin_capability,
					'delete_others_posts'	=> $admin_capability,
					'read_private_posts'	=> $admin_capability,
					'edit_post' 			=> $admin_capability,
					'delete_post' 			=> $admin_capability,
					'read_post' 			=> 'read_job_listing'
				),
				'publicly_queryable' 	=> true,
				'exclude_from_search' 	=> false,
				'hierarchical' 			=> false,
				'rewrite' 				=> $rewrite,
				'query_var' 			=> true,
				'supports' 				=> array( 'title', 'editor', 'custom-fields' ),
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
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'wp-job-manager' ),
		) );
		register_post_status( 'preview', array(
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => false,
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
	 * Add extra content when showing job content
	 */
	public function job_content( $content ) {
		global $post;

		if ( ! is_singular( 'job_listing' ) || ! in_the_loop() ) {
			return $content;
		}

		remove_filter( 'the_content', array( $this, 'job_content' ) );

		if ( 'job_listing' === $post->post_type ) {
			ob_start();

			do_action( 'job_content_start' );

			get_job_manager_template_part( 'content-single', 'job_listing' );

			do_action( 'job_content_end' );

			$content = ob_get_clean();
		}

		add_filter( 'the_content', array( $this, 'job_content' ) );

		return $content;
	}

	/**
	 * Job listing feeds
	 */
	public function job_feed() {
		$args = array(
			'post_type'           => 'job_listing',
			'post_status'         => 'publish',
			'ignore_sticky_posts' => 1,
			'posts_per_page'      => 10,
			's'                   => isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '',
			'meta_query'          => array(),
			'tax_query'           => array()
		);

		if ( ! empty( $_GET['location'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_job_location',
				'value'   => sanitize_text_field( $_GET['location'] ),
				'compare' => 'LIKE'
			);
		}

		if ( ! empty( $_GET['type'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'job_listing_type',
				'field'    => 'slug',
				'terms'    => explode( ',', sanitize_text_field( $_GET['type'] ) ) + array( 0 )
			);
		}

		if ( ! empty( $_GET['job_categories'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'job_listing_category',
				'field'    => 'id',
				'terms'    => explode( ',', sanitize_text_field( $_GET['job_categories'] ) ) + array( 0 )
			);
		}

		query_posts( apply_filters( 'job_feed_args', $args ) );

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
			echo "<job_listing:location>{$location}</job_listing:location>\n";
		}
		if ( $job_type ) {
			echo "<job_listing:job_type>{$job_type->name}</job_listing:job_type>\n";
		}
		if ( $company ) {
			echo "<job_listing:company>{$company}</job_listing:company>\n";
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
		$job_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT posts.ID FROM {$wpdb->posts} as posts
			WHERE posts.post_type = 'job_listing'
			AND posts.post_modified < %s
			AND posts.post_status = 'expired'
		", date( 'Y-m-d', strtotime( '-30 days', current_time( 'timestamp' ) ) ) ) );

		if ( $job_ids ) {
			foreach ( $job_ids as $job_id ) {
				wp_trash_post( $job_id );
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
	 * Set expirey date when job status changes
	 */
	public function set_expirey( $post ) {
		if ( $post->post_type !== 'job_listing' ) {
			return;
		}

		// See if it is already set
		$expires  = get_post_meta( $post->ID, '_job_expires', true );

		if ( ! empty( $expires ) ) {
			return;
		}

		// Get duration from the product if set...
		$duration = get_post_meta( $post->ID, '_job_duration', true );

		// ...otherwise use the global option
		if ( ! $duration )
			$duration = absint( get_option( 'job_manager_submission_duration' ) );

		if ( $duration ) {
			$expires = date( 'Y-m-d', strtotime( "+{$duration} days", current_time( 'timestamp' ) ) );
			update_post_meta( $post->ID, '_job_expires', $expires );

			// In case we are saving a post, ensure post data is updated so the field is not overridden
			if ( isset( $_POST[ '_job_expires' ] ) )
				$_POST[ '_job_expires' ] = $expires;

		} else {
			update_post_meta( $post->ID, '_job_expires', '' );
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
}