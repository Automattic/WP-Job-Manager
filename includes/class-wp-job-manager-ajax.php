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
		add_action( 'wp_ajax_nopriv_job_manager_upload_file', array( $this, 'upload_file' ) );
		add_action( 'wp_ajax_job_manager_upload_file', array( $this, 'upload_file' ) );
	}

	/**
	 * Get listings via ajax
	 */
	public function get_listings() {
		global $wp_post_types;

		$result            = array();
		$search_location   = sanitize_text_field( stripslashes( $_REQUEST['search_location'] ) );
		$search_keywords   = sanitize_text_field( stripslashes( $_REQUEST['search_keywords'] ) );
		$search_categories = isset( $_REQUEST['search_categories'] ) ? $_REQUEST['search_categories'] : '';
		$filter_job_types  = isset( $_REQUEST['filter_job_type'] ) ? array_filter( array_map( 'sanitize_title', (array) $_REQUEST['filter_job_type'] ) ) : null;
		$types             = get_job_listing_types();
		$post_type_label   = $wp_post_types['job_listing']->labels->name;

		if ( is_array( $search_categories ) ) {
			$search_categories = array_filter( array_map( 'sanitize_text_field', array_map( 'stripslashes', $search_categories ) ) );
		} else {
			$search_categories = array_filter( array( sanitize_text_field( stripslashes( $search_categories ) ) ) );
		}

		$args = array(
			'search_location'    => $search_location,
			'search_keywords'    => $search_keywords,
			'search_categories'  => $search_categories,
			'job_types'          => is_null( $filter_job_types ) ? '' : $filter_job_types + array( 0 ),
			'orderby'            => sanitize_text_field( $_REQUEST['orderby'] ),
			'order'              => sanitize_text_field( $_REQUEST['order'] ),
			'offset'             => ( absint( $_REQUEST['page'] ) - 1 ) * absint( $_REQUEST['per_page'] ),
			'posts_per_page'     => absint( $_REQUEST['per_page'] )
		);

		if ( isset( $_REQUEST['featured'] ) && ( $_REQUEST['featured'] === 'true' || $_REQUEST['featured'] === 'false' ) ) {
			$args['featured'] = $_REQUEST['featured'] === 'true' ? true : false;
		}

		if ( isset( $_REQUEST['filled'] ) && ( $_REQUEST['filled'] === 'true' || $_REQUEST['filled'] === 'false' ) ) {
			$args['filled'] = $_REQUEST['filled'] === 'true' ? true : false;
		}

		ob_start();

		$jobs = get_job_listings( apply_filters( 'job_manager_get_listings_args', $args ) );

		$result['found_jobs'] = false;

		if ( $jobs->have_posts() ) : $result['found_jobs'] = true; ?>

			<?php while ( $jobs->have_posts() ) : $jobs->the_post(); ?>

				<?php get_job_manager_template_part( 'content', 'job_listing' ); ?>

			<?php endwhile; ?>

		<?php else : ?>

			<?php get_job_manager_template_part( 'content', 'no-jobs-found' ); ?>

		<?php endif;

		$result['html']    = ob_get_clean();
		$result['showing'] = array();

		// Generate 'showing' text
		$showing_types = array();
		$unmatched     = false;

		foreach ( $types as $type ) {
			if ( in_array( $type->slug, $filter_job_types ) ) {
				$showing_types[] = $type->name;
			} else {
				$unmatched = true;
			}
		}

		if ( sizeof( $showing_types ) == 1 ) {
			$result['showing'][] = implode( ', ', $showing_types );
		} elseif ( $unmatched && $showing_types ) {
			$last_type           = array_pop( $showing_types );
			$result['showing'][] = implode( ', ', $showing_types ) . " &amp; $last_type";
		}

		if ( $search_categories ) {
			$showing_categories = array();

			foreach ( $search_categories as $category ) {
				$category_object = get_term_by( is_numeric( $category ) ? 'id' : 'slug', $category, 'job_listing_category' );

				if ( ! is_wp_error( $category_object ) ) {
					$showing_categories[] = $category_object->name;
				}
			}

			$result['showing'][] = implode( ', ', $showing_categories );
		}

		if ( $search_keywords ) {
			$result['showing'][] = '&ldquo;' . $search_keywords . '&rdquo;';
		}

		$result['showing'][] = $post_type_label;

		if ( $search_location ) {
			$result['showing'][] = sprintf( __( 'located in &ldquo;%s&rdquo;', 'wp-job-manager' ), $search_location );
		}

		if ( 1 === sizeof( $result['showing'] ) ) {
			$result['showing_all'] = true;
		}

		$result['showing'] = apply_filters( 'job_manager_get_listings_custom_filter_text', sprintf( __( 'Showing all %s', 'wp-job-manager' ), implode( ' ', $result['showing'] ) ) );

		// Generate RSS link
		$result['showing_links'] = job_manager_get_filtered_links( array(
			'filter_job_types'  => $filter_job_types,
			'search_location'   => $search_location,
			'search_categories' => $search_categories,
			'search_keywords'   => $search_keywords
		) );

		// Generate pagination
		if ( isset( $_REQUEST['show_pagination'] ) && $_REQUEST['show_pagination'] === 'true' ) {
			$result['pagination'] = get_job_listing_pagination( $jobs->max_num_pages, absint( $_REQUEST['page'] ) );
		}

		$result['max_num_pages'] = $jobs->max_num_pages;

		echo '<!--WPJM-->';
		echo json_encode( apply_filters( 'job_manager_get_listings_result', $result, $jobs ) );
		echo '<!--WPJM_END-->';

		die();
	}

	/**
	 * Filter the upload directory
	 */
	public static function upload_dir( $pathdata ) {
		global $job_manager_ajax_uploading_file;

		$subdir = apply_filters( 'job_manager_ajax_upload_dir_' . sanitize_key( $job_manager_ajax_uploading_file ), sanitize_key( $job_manager_ajax_uploading_file ) );
		$dir    = 'job-manager-uploads/' . $subdir;

		if ( empty( $pathdata['subdir'] ) ) {
			$pathdata['path']   = $pathdata['path'] . '/' . $dir;
			$pathdata['url']    = $pathdata['url'] . '/' . $dir;
			$pathdata['subdir'] = '/' . $dir;
		} else {
			$new_subdir         = '/' . $dir . $pathdata['subdir'];
			$pathdata['path']   = str_replace( $pathdata['subdir'], $new_subdir, $pathdata['path'] );
			$pathdata['url']    = str_replace( $pathdata['subdir'], $new_subdir, $pathdata['url'] );
			$pathdata['subdir'] = str_replace( $pathdata['subdir'], $new_subdir, $pathdata['subdir'] );
		}
		return $pathdata;
	}

	/**
	 * Upload file via ajax
	 *
	 * No nonce field since the form may be statically cached.
	 */
	public function upload_file() {
		global $job_manager_ajax_uploading_file;

		$data               = array( 'files' => array() );
		$allowed_mime_types = get_allowed_mime_types();

		include_once( ABSPATH . 'wp-admin/includes/file.php' );
		include_once( ABSPATH . 'wp-admin/includes/media.php' );

		add_filter( 'upload_dir', array( $this, 'upload_dir' ) );

		if ( ! empty( $_FILES ) ) {
			foreach ( $_FILES as $key => $posted_file ) {
				// Filter the upload dir based on the field being uploaded using this.
				$job_manager_ajax_uploading_file = $key;
				$file                            = new stdClass();

				if ( ! in_array( $posted_file['type'], $allowed_mime_types ) ) {
	    			$file->error = sprintf( __( 'Uploaded files need to be one of the following file types: %s', 'wp-job-manager' ), implode( ', ', array_keys( $allowed_mime_types ) ) );
				} else {
					$upload = wp_handle_upload( $posted_file, apply_filters( 'submit_job_wp_handle_upload_overrides', array( 'test_form' => false ) ) );
					if ( ! empty( $upload['error'] ) ) {
						$file->error = $upload['error'];
					} else {
						$file->url       = $upload['url'];
						$file->name      = basename( $upload['file'] );
						$file->type      = $upload['type'];
						$file->size      = $posted_file['size'];
						$file->extension = substr( strrchr( $file->name, '.' ), 1 );
					}
				}
				$data['files'][] = $file;
			}
		}

		wp_send_json( $data );
	}
}

new WP_Job_Manager_Ajax();