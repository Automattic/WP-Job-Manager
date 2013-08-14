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
		add_filter( 'the_content', array( $this, 'job_content' ) );
		add_action( 'job_manager_check_for_expired_jobs', array( $this, 'check_for_expired_jobs' ) );
		add_action( 'pending_to_publish', array( $this, 'set_expirey' ) );
		add_action( 'preview_to_publish', array( $this, 'set_expirey' ) );

		add_filter( 'the_job_description', 'wptexturize'        );
		add_filter( 'the_job_description', 'convert_smilies'    );
		add_filter( 'the_job_description', 'convert_chars'      );
		add_filter( 'the_job_description', 'wpautop'            );
		add_filter( 'the_job_description', 'shortcode_unautop'  );
		add_filter( 'the_job_description', 'prepend_attachment' );
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
			$singular  = __( 'Job Category', 'job_manager' );
			$plural    = __( 'Job Categories', 'job_manager' );

			if ( current_theme_supports( 'job-manager-templates' ) ) {
				$rewrite     = array(
					'slug'         => _x( 'job-category', 'Job category slug - resave permalinks after changing this', 'job_manager' ),
					'with_front'   => false,
					'hierarchical' => false
				);
			} else {
				$rewrite = false;
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
	                    'search_items' 		=> sprintf( __( 'Search %s', 'job_manager' ), $plural ),
	                    'all_items' 		=> sprintf( __( 'All %s', 'job_manager' ), $plural ),
	                    'parent_item' 		=> sprintf( __( 'Parent %s', 'job_manager' ), $singular ),
	                    'parent_item_colon' => sprintf( __( 'Parent %s:', 'job_manager' ), $singular ),
	                    'edit_item' 		=> sprintf( __( 'Edit %s', 'job_manager' ), $singular ),
	                    'update_item' 		=> sprintf( __( 'Update %s', 'job_manager' ), $singular ),
	                    'add_new_item' 		=> sprintf( __( 'Add New %s', 'job_manager' ), $singular ),
	                    'new_item_name' 	=> sprintf( __( 'New %s Name', 'job_manager' ),  $singular )
	            	),
		            'show_ui' 				=> true,
		            'query_var' 			=> true,
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

	    $singular  = __( 'Job Type', 'job_manager' );
		$plural    = __( 'Job Types', 'job_manager' );

		if ( current_theme_supports( 'job-manager-templates' ) ) {
			$rewrite     = array(
				'slug'         => _x( 'job-type', 'Job type slug - resave permalinks after changing this', 'job_manager' ),
				'with_front'   => false,
				'hierarchical' => false
			);
		} else {
			$rewrite = false;
		}

		register_taxonomy( "job_listing_type",
	        array( "job_listing" ),
	        array(
	            'hierarchical' 			=> true,
	            'label' 				=> $plural,
	            'labels' => array(
                    'name' 				=> $plural,
                    'singular_name' 	=> $singular,
                    'search_items' 		=> sprintf( __( 'Search %s', 'job_manager' ), $plural ),
                    'all_items' 		=> sprintf( __( 'All %s', 'job_manager' ), $plural ),
                    'parent_item' 		=> sprintf( __( 'Parent %s', 'job_manager' ), $singular ),
                    'parent_item_colon' => sprintf( __( 'Parent %s:', 'job_manager' ), $singular ),
                    'edit_item' 		=> sprintf( __( 'Edit %s', 'job_manager' ), $singular ),
                    'update_item' 		=> sprintf( __( 'Update %s', 'job_manager' ), $singular ),
                    'add_new_item' 		=> sprintf( __( 'Add New %s', 'job_manager' ), $singular ),
                    'new_item_name' 	=> sprintf( __( 'New %s Name', 'job_manager' ),  $singular )
            	),
	            'show_ui' 				=> true,
	            'query_var' 			=> true,
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
		$singular  = __( 'Job Listing', 'job_manager' );
		$plural    = __( 'Job Listings', 'job_manager' );

		if ( current_theme_supports( 'job-manager-templates' ) ) {
			$has_archive = _x( 'jobs', 'Post type archive slug - resave permalinks after changing this', 'job_manager' );
		} else {
			$has_archive = false;
		}

		$rewrite     = array(
			'slug'       => _x( 'job', 'Job permalink - resave permalinks after changing this', 'job_manager' ),
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
					'all_items'             => sprintf( __( 'All %s', 'job_manager' ), $plural ),
					'add_new' 				=> __( 'Add New', 'job_manager' ),
					'add_new_item' 			=> sprintf( __( 'Add %s', 'job_manager' ), $singular ),
					'edit' 					=> __( 'Edit', 'job_manager' ),
					'edit_item' 			=> sprintf( __( 'Edit %s', 'job_manager' ), $singular ),
					'new_item' 				=> sprintf( __( 'New %s', 'job_manager' ), $singular ),
					'view' 					=> sprintf( __( 'View %s', 'job_manager' ), $singular ),
					'view_item' 			=> sprintf( __( 'View %s', 'job_manager' ), $singular ),
					'search_items' 			=> sprintf( __( 'Search %s', 'job_manager' ), $plural ),
					'not_found' 			=> sprintf( __( 'No %s found', 'job_manager' ), $plural ),
					'not_found_in_trash' 	=> sprintf( __( 'No %s found in trash', 'job_manager' ), $plural ),
					'parent' 				=> sprintf( __( 'Parent %s', 'job_manager' ), $singular )
				),
				'description' => __( 'This is where you can create and manage job listings.', 'job_manager' ),
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
					'read_post' 			=> $admin_capability
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
			'label'                     => _x( 'Expired', 'job_listing', 'job_manager' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'job_manager' ),
		) );
	}

	/**
	 * Add extra content when showing job content
	 */
	public function job_content( $content ) {
		global $post, $job_manager;

		if ( ! is_singular( 'job_listing' ) )
			return $content;

		remove_filter( 'the_content', array( $this, 'job_content' ) );

		if ( $post->post_type == 'job_listing' ) {
			ob_start();

			get_job_manager_template_part( 'content-single', 'job_listing' );

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
				'field'    => 'slug',
				'terms'    => explode( ',', sanitize_text_field( $_GET['job_categories'] ) ) + array( 0 )
			);
		}

		query_posts( apply_filters( 'job_feed_args', $args ) );

		do_feed_rss2( false );
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
		", current_time( 'mysql' ) ) );

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
		", date( 'Y-m-d H:i:s', strtotime( '-30 days', current_time( 'timestamp' ) ) ) ) );

		if ( $job_ids ) {
			foreach ( $job_ids as $job_id ) {
				wp_trash_post( $job_id );
			}
		}
	}

	/**
	 * Set expirey date when job status changes
	 */
	public function set_expirey( $post ) {
		if ( $post->post_type !== 'job_listing' )
			return;

		// Expires
		$duration = absint( get_option( 'job_manager_submission_duration' ) );

		if ( $duration ) {
			$expires = date( 'Y-m-d H:i:s', strtotime( "+{$duration} days", current_time( 'timestamp' ) ) );
			update_post_meta( $post->ID, '_job_expires', $expires );
		}
	}
}