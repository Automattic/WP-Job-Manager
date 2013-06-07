<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WP_Job_Manager_Ajax class.
 */
class WP_Job_Manager_Ajax {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_ajax_nopriv_job_manager_get_listings', array( $this, 'get_listings' ) );
		add_action( 'wp_ajax_job_manager_get_listings', array( $this, 'get_listings' ) );
	}

	/**
	 * Get listings via ajax
	 */
	public function get_listings() {
		global $job_manager, $wpdb;

		ob_start();

		$search_location  = sanitize_text_field( stripslashes( $_POST['search_location'] ) );
		$search_keywords  = sanitize_text_field( stripslashes( $_POST['search_keywords'] ) );
		$search_category  = isset( $_POST['search_category'] ) ? sanitize_text_field( stripslashes( $_POST['search_category'] ) ) : '';
		$filter_job_types = isset( $_POST['filter_job_type'] ) ? array_filter( array_map( 'sanitize_title', (array) $_POST['filter_job_type'] ) ) : array();

		$args = array(
			'post_type'           => 'job_listing',
			'post_status'         => 'publish',
			'ignore_sticky_posts' => 1,
			'offset'              => ( absint( $_POST['page'] ) - 1 ) * absint( $_POST['per_page'] ),
			'posts_per_page'      => absint( $_POST['per_page'] ),
			'orderby'             => sanitize_text_field( $_POST['orderby'] ),
			'order'               => sanitize_text_field( $_POST['order'] ),
			'tax_query'           => array(
				array(
					'taxonomy' => 'job_listing_type',
					'field'    => 'slug',
					'terms'    => $filter_job_types + array( 0 )
				)
			),
			'meta_query'          => array()
		);

		if ( get_option( 'job_manager_hide_filled_positions' ) == 1 )
			$args['meta_query'][] = array(
				'key'     => '_filled',
				'value'   => '1',
				'compare' => '!='
			);

		// Location search
		if ( $search_location )
			$args['meta_query'][] = array(
				'key'     => '_job_location',
				'value'   => $search_location,
				'compare' => 'LIKE'
			);

		// Keyword search - search meta as well as post content
		if ( $search_keywords ) {
			$post_ids = $wpdb->get_col( $wpdb->prepare( "
			    SELECT DISTINCT post_id FROM {$wpdb->postmeta}
			    WHERE meta_value LIKE '%%%s%%'
			", $search_keywords ) );

			$post_ids = $post_ids + $wpdb->get_col( $wpdb->prepare( "
			    SELECT DISTINCT ID FROM {$wpdb->posts}
			    WHERE post_title LIKE '%%%s%%'
			    OR post_content LIKE '%%%s%%'
			", $search_keywords, $search_keywords ) );

			$args['post__in'] = $post_ids + array( 0 );
		}

		// Category search
		if ( $search_category ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'job_listing_category',
				'field'    => 'slug',
				'terms'    => array( $search_category, 0 )
			);
		}

		$jobs = new WP_Query( $args );

		$result = array();
		$result['found_jobs'] = false;

		if ( $jobs->have_posts() ) : $result['found_jobs'] = true; ?>

			<?php while ( $jobs->have_posts() ) : $jobs->the_post(); ?>

				<?php get_job_manager_template_part( 'content', 'job_listing' ); ?>

			<?php endwhile; ?>

		<?php else : ?>

			<li class="no_job_listings_found"><?php _e( 'No more jobs found matching your selection.', 'job_manager' ); ?></li>

		<?php endif;

		$result['html']    = ob_get_clean();

		// Generate 'showing' text
		$types = get_job_listing_types();

		if ( sizeof( $filter_job_types ) > 0 && ( sizeof( $filter_job_types ) !== sizeof( $types ) || $search_keywords || $search_location || $search_category ) ) {
			$showing_types = array();
			$unmatched     = false;

			foreach ( $types as $type ) {
				if ( in_array( $type->slug, $filter_job_types ) )
					$showing_types[] = $type->name;
				else
					$unmatched = true;
			}

			if ( ! $unmatched )
				$showing_types  = '';
			elseif ( sizeof( $showing_types ) == 1 ) {
				$showing_types  = implode( ', ', $showing_types ) . ' ';
			} else {
				$last           = array_pop( $showing_types );
				$showing_types  = implode( ', ', $showing_types );
				$showing_types .= " &amp; $last ";
			}

			$showing_category = '';

			if ( $search_category  ) {
				$category = get_term_by( 'slug', $search_category, 'job_listing_category' );
				if ( ! is_wp_error( $category ) )
					$showing_category = $category->name . ' ';
			}

			if ( $search_keywords ) {
				$showing_jobs  = sprintf( __( 'Showing %s&ldquo;%s&rdquo; %sjobs', 'job_manager' ), $showing_types, $search_keywords, $showing_category );
			} else {
				$showing_jobs  = sprintf( __( 'Showing all %s%sjobs', 'job_manager' ), $showing_types, $showing_category );
			}

			$showing_location  = $search_location ? sprintf( ' ' . __( 'located in &ldquo;%s&rdquo;', 'job_manager' ), $search_location ) : '';

			$result['showing'] = $showing_jobs . $showing_location;

		} else {
			$result['showing'] = '';
		}

		// Generate RSS link
		$result['rss'] = get_job_listing_rss_link( array(
			'type'         => implode( ',', $filter_job_types ),
			'location'     => $search_location,
			'job_category' => $search_category,
			's'            => $search_keywords,
		) );

		$result['max_num_pages'] = $jobs->max_num_pages;

		echo json_encode( $result );

		die();
	}
}

new WP_Job_Manager_Ajax();