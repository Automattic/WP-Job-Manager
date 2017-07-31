<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Handles Job Manager's Ajax endpoints.
 *
 * @package wp-job-manager
 * @since 1.0.0
 */
class WP_Job_Manager_Ajax {

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.26.0
	 */
	private static $_instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.26.0
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
		add_action( 'template_redirect', array( __CLASS__, 'do_jm_ajax' ), 0 );

		// JM Ajax endpoints
		add_action( 'job_manager_ajax_get_listings', array( $this, 'get_listings' ) );
		add_action( 'job_manager_ajax_upload_file', array( $this, 'upload_file' ) );

		// BW compatible handlers
		add_action( 'wp_ajax_nopriv_job_manager_get_listings', array( $this, 'get_listings' ) );
		add_action( 'wp_ajax_job_manager_get_listings', array( $this, 'get_listings' ) );
		add_action( 'wp_ajax_nopriv_job_manager_upload_file', array( $this, 'upload_file' ) );
		add_action( 'wp_ajax_job_manager_upload_file', array( $this, 'upload_file' ) );
	}

	/**
	 * Adds endpoint for frontend Ajax requests.
	 */
	public static function add_endpoint() {
		add_rewrite_tag( '%jm-ajax%', '([^/]*)' );
		add_rewrite_rule( 'jm-ajax/([^/]*)/?', 'index.php?jm-ajax=$matches[1]', 'top' );
		add_rewrite_rule( 'index.php/jm-ajax/([^/]*)/?', 'index.php?jm-ajax=$matches[1]', 'top' );
	}

	/**
	 * Gets Job Manager's Ajax Endpoint.
	 *
	 * @param  string $request      Optional
	 * @param  string $ssl (Unused) Optional
	 * @return string
	 */
	public static function get_endpoint( $request = '%%endpoint%%', $ssl = null ) {
		if ( strstr( get_option( 'permalink_structure' ), '/index.php/' ) ) {
			$endpoint = trailingslashit( home_url( '/index.php/jm-ajax/' . $request . '/', 'relative' ) );
		} elseif ( get_option( 'permalink_structure' ) ) {
			$endpoint = trailingslashit( home_url( '/jm-ajax/' . $request . '/', 'relative' ) );
		} else {
			$endpoint = add_query_arg( 'jm-ajax', $request, trailingslashit( home_url( '', 'relative' ) ) );
		}
		return esc_url_raw( $endpoint );
	}

	/**
	 * Performs Job Manager's Ajax actions.
	 */
	public static function do_jm_ajax() {
		global $wp_query;

		if ( ! empty( $_GET['jm-ajax'] ) ) {
			 $wp_query->set( 'jm-ajax', sanitize_text_field( $_GET['jm-ajax'] ) );
		}

		if ( $action = $wp_query->get( 'jm-ajax' ) ) {
			if ( ! defined( 'DOING_AJAX' ) ) {
				define( 'DOING_AJAX', true );
			}

			// Not home - this is an ajax endpoint
			$wp_query->is_home = false;

			/**
			 * Performs an Ajax action.
			 * The dynamic part of the action, $action, is the predefined Ajax action to be performed.
			 *
			 * @since 1.23.0
			 */
			do_action( 'job_manager_ajax_' . sanitize_text_field( $action ) );
			wp_die();
		}
	}

	/**
	 * Returns Job Listings for Ajax endpoint.
	 */
	public function get_listings() {
		global $wp_post_types;

		$result             = array();
		$search_location    = sanitize_text_field( stripslashes( $_REQUEST['search_location'] ) );
		$search_keywords    = sanitize_text_field( stripslashes( $_REQUEST['search_keywords'] ) );
		$search_categories  = isset( $_REQUEST['search_categories'] ) ? $_REQUEST['search_categories'] : '';
		$filter_job_types   = isset( $_REQUEST['filter_job_type'] ) ? array_filter( array_map( 'sanitize_title', (array) $_REQUEST['filter_job_type'] ) ) : null;
		$filter_post_status = isset( $_REQUEST['filter_post_status'] ) ? array_filter( array_map( 'sanitize_title', (array) $_REQUEST['filter_post_status'] ) ) : null;
		$types              = get_job_listing_types();
		$post_type_label    = $wp_post_types['job_listing']->labels->name;
		$orderby            = sanitize_text_field( $_REQUEST['orderby'] );

		if ( is_array( $search_categories ) ) {
			$search_categories = array_filter( array_map( 'sanitize_text_field', array_map( 'stripslashes', $search_categories ) ) );
		} else {
			$search_categories = array_filter( array( sanitize_text_field( stripslashes( $search_categories ) ) ) );
		}

		$args = array(
			'search_location'    => $search_location,
			'search_keywords'    => $search_keywords,
			'search_categories'  => $search_categories,
			'job_types'          => is_null( $filter_job_types ) || sizeof( $types ) === sizeof( $filter_job_types ) ? '' : $filter_job_types + array( 0 ),
			'post_status'        => $filter_post_status,
			'orderby'            => $orderby,
			'order'              => sanitize_text_field( $_REQUEST['order'] ),
			'offset'             => ( absint( $_REQUEST['page'] ) - 1 ) * absint( $_REQUEST['per_page'] ),
			'posts_per_page'     => absint( $_REQUEST['per_page'] ),
		);

		if ( isset( $_REQUEST['filled'] ) && ( $_REQUEST['filled'] === 'true' || $_REQUEST['filled'] === 'false' ) ) {
			$args['filled'] = $_REQUEST['filled'] === 'true' ? true : false;
		}

		if ( isset( $_REQUEST['featured'] ) && ( $_REQUEST['featured'] === 'true' || $_REQUEST['featured'] === 'false' ) ) {
			$args['featured'] = $_REQUEST['featured'] === 'true' ? true : false;
			$args['orderby']  = 'featured' === $orderby ? 'date' : $orderby;
		}

		/**
		 * Get the arguments to use when building the Job Listing WP Query.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args Arguments used for generating Job Listing query (see `get_job_listings()`)
		 */
		$jobs = get_job_listings( apply_filters( 'job_manager_get_listings_args', $args ) );

		$result = array(
			'found_jobs' => $jobs->have_posts(),
			'showing' => '',
			'max_num_pages' => $jobs->max_num_pages,
		);

		if ( $jobs->post_count && ( $search_location || $search_keywords || $search_categories ) ) {
			$message = sprintf( _n( 'Search completed. Found %d matching record.', 'Search completed. Found %d matching records.', $jobs->found_posts, 'wp-job-manager' ), $jobs->found_posts );
			$result['showing_all'] = true;
		} else {
			$message = '';
		}

		$search_values = array(
			'location'   => $search_location,
			'keywords'   => $search_keywords,
			'categories' => $search_categories,
		);

		/**
		 * Filter the message that describes the results of the search query.
		 *
		 * @since 1.0.0
		 *
		 * @param string $message Default message that is generated when posts are found.
		 * @param array $search_values {
		 *  Helpful values often used in the generation of this message.
		 *
		 *  @type string $location   Query used to filter by job listing location.
		 *  @type string $keywords   Query used to filter by general keywords.
		 *  @type array  $categories List of the categories to filter by.
		 * }
		 */
		$result['showing'] = apply_filters( 'job_manager_get_listings_custom_filter_text', $message, $search_values );

		// Generate RSS link
		$result['showing_links'] = job_manager_get_filtered_links( array(
			'filter_job_types'  => $filter_job_types,
			'search_location'   => $search_location,
			'search_categories' => $search_categories,
			'search_keywords'   => $search_keywords,
		) );

		/**
		 * Send back a response to the AJAX request without creating HTML.
		 *
		 * @since 1.26.0
		 *
		 * @param array $result
		 * @param WP_Query $jobs
		 * @return bool True by default. Change to false to halt further response.
		 */
		if ( true !== apply_filters( 'job_manager_ajax_get_jobs_html_results', true, $result, $jobs ) ) {
			/**
			 * Filters the results of the job listing Ajax query to be sent back to the client.
			 *
			 * @since 1.0.0
			 *
			 * @param array $result {
			 *  Package of the query results along with meta information.
			 *
			 *  @type bool   $found_jobs    Whether or not jobs were found in the query.
			 *  @type string $showing       Description of the search query and results.
			 *  @type int    $max_num_pages Number of pages in the search result.
			 *  @type string $html          HTML representation of the search results (only if filter
			 *                              `job_manager_ajax_get_jobs_html_results` returns true).
			 *  @type array $pagination     Pagination links to use for stepping through filter results.
			 * }
			 */
			return wp_send_json( apply_filters( 'job_manager_get_listings_result', $result, $jobs ) );
		}

		ob_start();

		if ( $result['found_jobs'] ) : ?>

			<?php while ( $jobs->have_posts() ) : $jobs->the_post(); ?>

				<?php get_job_manager_template_part( 'content', 'job_listing' ); ?>

			<?php endwhile; ?>

		<?php else : ?>

			<?php get_job_manager_template_part( 'content', 'no-jobs-found' ); ?>

		<?php endif;

		$result['html'] = ob_get_clean();

		// Generate pagination
		if ( isset( $_REQUEST['show_pagination'] ) && $_REQUEST['show_pagination'] === 'true' ) {
			$result['pagination'] = get_job_listing_pagination( $jobs->max_num_pages, absint( $_REQUEST['page'] ) );
		}

		/** This filter is documented in includes/class-wp-job-manager-ajax.php (above) */
		wp_send_json( apply_filters( 'job_manager_get_listings_result', $result, $jobs ) );
	}

	/**
	 * Uploads file from an Ajax request.
	 *
	 * No nonce field since the form may be statically cached.
	 */
	public function upload_file() {
		if ( ! job_manager_user_can_upload_file_via_ajax() ) {
			wp_send_json_error( __( 'You must be logged in to upload files using this method.', 'wp-job-manager' ) );
			return;
		}
		$data = array(
			'files' => array(),
		);

		if ( ! empty( $_FILES ) ) {
			foreach ( $_FILES as $file_key => $file ) {
				$files_to_upload = job_manager_prepare_uploaded_files( $file );
				foreach ( $files_to_upload as $file_to_upload ) {
					$uploaded_file = job_manager_upload_file( $file_to_upload, array(
						'file_key' => $file_key,
					) );

					if ( is_wp_error( $uploaded_file ) ) {
						$data['files'][] = array(
							'error' => $uploaded_file->get_error_message(),
						);
					} else {
						$data['files'][] = $uploaded_file;
					}
				}
			}
		}

		wp_send_json( $data );
	}
}

WP_Job_Manager_Ajax::instance();
