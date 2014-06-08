<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WP_Job_Manager_Shortcodes class.
 */
class WP_Job_Manager_Shortcodes {

	private $job_dashboard_message = '';

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'shortcode_action_handler' ) );
		add_action( 'job_manager_job_dashboard_content_edit', array( $this, 'edit_job' ) );
		add_action( 'job_manager_job_filters_end', array( $this, 'job_filter_job_types' ), 20 );
		add_action( 'job_manager_job_filters_end', array( $this, 'job_filter_results' ), 30 );

		add_shortcode( 'submit_job_form', array( $this, 'submit_job_form' ) );
		add_shortcode( 'job_dashboard', array( $this, 'job_dashboard' ) );
		add_shortcode( 'jobs', array( $this, 'output_jobs' ) );
		add_shortcode( 'job', array( $this, 'output_job' ) );
		add_shortcode( 'job_summary', array( $this, 'output_job_summary' ) );
	}

	/**
	 * Handle actions which need to be run before the shortcode e.g. post actions
	 */
	public function shortcode_action_handler() {
		global $post;

		if ( is_page() && strstr( $post->post_content, '[job_dashboard' ) ) {
			$this->job_dashboard_handler();
		}
	}

	/**
	 * Show the job submission form
	 */
	public function submit_job_form() {
		return $GLOBALS['job_manager']->forms->get_form( 'submit-job' );
	}

	/**
	 * Handles actions on job dashboard
	 */
	public function job_dashboard_handler() {
		if ( ! empty( $_REQUEST['action'] ) && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'job_manager_my_job_actions' ) ) {

			$action = sanitize_title( $_REQUEST['action'] );
			$job_id = absint( $_REQUEST['job_id'] );

			try {
				// Get Job
				$job    = get_post( $job_id );

				// Check ownership
				if ( $job->post_author != get_current_user_id() ) {
					throw new Exception( __( 'Invalid Job ID', 'wp-job-manager' ) );
				}

				switch ( $action ) {
					case 'mark_filled' :
						// Check status
						if ( $job->_filled == 1 )
							throw new Exception( __( 'This job is already filled', 'wp-job-manager' ) );

						// Update
						update_post_meta( $job_id, '_filled', 1 );

						// Message
						$this->job_dashboard_message = '<div class="job-manager-message">' . sprintf( __( '%s has been filled', 'wp-job-manager' ), $job->post_title ) . '</div>';
						break;
					case 'mark_not_filled' :
						// Check status
						if ( $job->_filled != 1 )
							throw new Exception( __( 'This job is already not filled', 'wp-job-manager' ) );

						// Update
						update_post_meta( $job_id, '_filled', 0 );

						// Message
						$this->job_dashboard_message = '<div class="job-manager-message">' . sprintf( __( '%s has been marked as not filled', 'wp-job-manager' ), $job->post_title ) . '</div>';
						break;
					case 'delete' :
						// Trash it
						wp_trash_post( $job_id );

						// Message
						$this->job_dashboard_message = '<div class="job-manager-message">' . sprintf( __( '%s has been deleted', 'wp-job-manager' ), $job->post_title ) . '</div>';

						break;
					case 'relist' :
						// redirect to post page
						wp_redirect( add_query_arg( array( 'step' => 'preview', 'job_id' => absint( $job_id ) ), get_permalink( get_page_by_path( get_option( 'job_manager_submit_page_slug' ) )->ID ) ) );

						break;
					default :
						do_action( 'job_manager_job_dashboard_do_action_' . $action );
						break;
				}

				do_action( 'job_manager_my_job_do_action', $action, $job_id );

			} catch ( Exception $e ) {
				$this->job_dashboard_message = '<div class="job-manager-error">' . $e->getMessage() . '</div>';
			}
		}
	}

	/**
	 * Shortcode which lists the logged in user's jobs
	 */
	public function job_dashboard( $atts ) {
		if ( ! is_user_logged_in() ) {
			return __( 'You need to be signed in to manage your job listings.', 'wp-job-manager' );
		}

		extract( shortcode_atts( array(
			'posts_per_page' => '25',
		), $atts ) );

		wp_enqueue_script( 'wp-job-manager-job-dashboard' );

		ob_start();

		// If doing an action, show conditional content if needed....
		if ( ! empty( $_REQUEST['action'] ) ) {
			$action = sanitize_title( $_REQUEST['action'] );

			// Show alternative content if a plugin wants to
			if ( has_action( 'job_manager_job_dashboard_content_' . $action ) ) {
				do_action( 'job_manager_job_dashboard_content_' . $action, $atts );

				return ob_get_clean();
			}
		}

		// ....If not show the job dashboard
		$args     = apply_filters( 'job_manager_get_dashboard_jobs_args', array(
			'post_type'           => 'job_listing',
			'post_status'         => array( 'publish', 'expired', 'pending' ),
			'ignore_sticky_posts' => 1,
			'posts_per_page'      => $posts_per_page,
			'offset'              => ( max( 1, get_query_var('paged') ) - 1 ) * $posts_per_page,
			'orderby'             => 'date',
			'order'               => 'desc',
			'author'              => get_current_user_id()
		) );

		$jobs = new WP_Query;

		echo $this->job_dashboard_message;

		$job_dashboard_columns = apply_filters( 'job_manager_job_dashboard_columns', array(
			'job_title' => __( 'Job Title', 'wp-job-manager' ),
			'filled'    => __( 'Filled?', 'wp-job-manager' ),
			'date'      => __( 'Date Posted', 'wp-job-manager' ),
			'expires'   => __( 'Date Expires', 'wp-job-manager' )
		) );

		get_job_manager_template( 'job-dashboard.php', array( 'jobs' => $jobs->query( $args ), 'max_num_pages' => $jobs->max_num_pages, 'job_dashboard_columns' => $job_dashboard_columns ) );

		return ob_get_clean();
	}

	/**
	 * Edit job form
	 */
	public function edit_job() {
		global $job_manager;

		echo $job_manager->forms->get_form( 'edit-job' );
	}

	/**
	 * output_jobs function.
	 *
	 * @access public
	 * @param mixed $args
	 * @return void
	 */
	public function output_jobs( $atts ) {
		ob_start();

		extract( $atts = shortcode_atts( apply_filters( 'job_manager_output_jobs_defaults', array(
			'per_page'           => get_option( 'job_manager_per_page' ),
			'orderby'            => 'featured',
			'order'              => 'DESC',
			
			// Filters + cats
			'show_filters'       => true,
			'show_categories'    => true,
			
			// Limit what jobs are shown based on category and type
			'categories'         => '',
			'job_types'          => '',
			'featured'           => null, // True to show only featured, false to hide featuref, leave null to show both.
			'show_featured_only' => false, // Deprecated
			
			// Default values for filters
			'location'           => '', 
			'keywords'           => '',
			'selected_category'  => '',
			'selected_job_types' => implode( ',', array_values( get_job_listing_types( 'id=>slug' ) ) ),
		) ), $atts ) );

		if ( ! get_option( 'job_manager_enable_categories' ) ) {
			$show_categories = false;
		}

		// String and bool handling
		$show_filters       = ( is_bool( $show_filters ) && $show_filters ) || in_array( $show_filters, array( '1', 'true', 'yes' ) ) ? true : false;
		$show_categories    = ( is_bool( $show_categories ) && $show_categories ) || in_array( $show_categories, array( '1', 'true', 'yes' ) ) ? true : false;
		$show_featured_only = ( is_bool( $show_featured_only ) && $show_featured_only ) || in_array( $show_featured_only, array( '1', 'true', 'yes' ) ) ? true : false;

		if ( ! is_null( $featured ) ) {
			$featured = ( is_bool( $featured ) && $featured ) || in_array( $featured, array( '1', 'true', 'yes' ) ) ? true : false;
		} elseif( $show_featured_only ) {
			$featured = true;
		}

		// Array handling
		$categories         = array_filter( array_map( 'trim', explode( ',', $categories ) ) );
		$job_types          = array_filter( array_map( 'trim', explode( ',', $job_types ) ) );
		$selected_job_types = array_filter( array_map( 'trim', explode( ',', $selected_job_types ) ) );

		// Get keywords and location from querystring if set
		if ( ! empty( $_GET['search_keywords'] ) ) {
			$keywords = sanitize_text_field( $_GET['search_keywords'] );
		}
		if ( ! empty( $_GET['search_location'] ) ) {
			$location = sanitize_text_field( $_GET['search_location'] );
		}
		if ( ! empty( $_GET['search_category'] ) ) {
			$selected_category = sanitize_text_field( $_GET['search_category'] );
		}

		if ( $show_filters ) {

			get_job_manager_template( 'job-filters.php', array( 'per_page' => $per_page, 'orderby' => $orderby, 'order' => $order, 'show_categories' => $show_categories, 'categories' => $categories, 'selected_category' => $selected_category, 'job_types' => $job_types, 'atts' => $atts, 'location' => $location, 'keywords' => $keywords, 'selected_job_types' => $selected_job_types ) );

			?><ul class="job_listings"></ul><a class="load_more_jobs" href="#" style="display:none;"><strong><?php _e( 'Load more job listings', 'wp-job-manager' ); ?></strong></a><?php

		} else {

			$jobs = get_job_listings( apply_filters( 'job_manager_output_jobs_args', array(
				'search_location'   => $location,
				'search_keywords'   => $keywords,
				'search_categories' => $categories,
				'job_types'         => $job_types,
				'orderby'           => $orderby,
				'order'             => $order,
				'posts_per_page'    => $per_page,
				'featured'          => $featured
			) ) );

			if ( $jobs->have_posts() ) : ?>

				<ul class="job_listings">

					<?php while ( $jobs->have_posts() ) : $jobs->the_post(); ?>

						<?php get_job_manager_template_part( 'content', 'job_listing' ); ?>

					<?php endwhile; ?>

				</ul>

				<?php if ( $jobs->found_posts > $per_page ) : ?>

					<?php wp_enqueue_script( 'wp-job-manager-ajax-filters' ); ?>

					<a class="load_more_jobs" href="#"><strong><?php _e( 'Load more job listings', 'wp-job-manager' ); ?></strong></a>

				<?php endif; ?>

			<?php endif;

			wp_reset_postdata();
		}

		$data_attributes_string = '';
		$data_attributes        = array(
			'location'     => $location,
			'keywords'     => $keywords,
			'show_filters' => $show_filters ? 'true' : 'false',
			'per_page'     => $per_page,
			'orderby'      => $orderby,
			'order'        => $order,
			'categories'   => implode( ',', $categories )
		);
		if ( ! is_null( $featured ) ) {
			$data_attributes[ 'featured' ] = $featured ? 'true' : 'false';
		}
		foreach ( $data_attributes as $key => $value ) {
			$data_attributes_string .= 'data-' . esc_attr( $key ) . '="' . esc_attr( $value ) . '" ';
		}

		return '<div class="job_listings" ' . $data_attributes_string . '>' . ob_get_clean() . '</div>';
	}

	/**
	 * Show job types 
	 * @param  array $atts
	 */
	public function job_filter_job_types( $atts ) {
		extract( $atts );

		$job_types          = array_filter( array_map( 'trim', explode( ',', $job_types ) ) );
		$selected_job_types = array_filter( array_map( 'trim', explode( ',', $selected_job_types ) ) );

		get_job_manager_template( 'job-filter-job-types.php', array( 'job_types' => $job_types, 'atts' => $atts, 'selected_job_types' => $selected_job_types ) );
	}

	/**
	 * Show results div
	 */
	public function job_filter_results() {
		echo '<div class="showing_jobs"></div>';
	}

	/**
	 * output_job function.
	 *
	 * @access public
	 * @param array $args
	 * @return string
	 */
	public function output_job( $atts ) {
		extract( shortcode_atts( array(
			'id' => '',
		), $atts ) );

		if ( ! $id )
			return;

		ob_start();

		$args = array(
			'post_type'   => 'job_listing',
			'post_status' => 'publish',
			'p'           => $id
		);

		$jobs = new WP_Query( $args );

		if ( $jobs->have_posts() ) : ?>

			<?php while ( $jobs->have_posts() ) : $jobs->the_post(); ?>

				<h1><?php the_title(); ?></h1>

				<?php get_job_manager_template_part( 'content-single', 'job_listing' ); ?>

			<?php endwhile; ?>

		<?php endif;

		wp_reset_postdata();

		return '<div class="job_shortcode single_job_listing">' . ob_get_clean() . '</div>';
	}

	/**
	 * Job Summary shortcode
	 *
	 * @access public
	 * @param array $args
	 * @return string
	 */
	public function output_job_summary( $atts ) {
		extract( shortcode_atts( array(
			'id'    => '',
			'width' => '250px',
			'align' => 'left'
		), $atts ) );

		if ( ! $id )
			return;

		ob_start();

		$args = array(
			'post_type'   => 'job_listing',
			'post_status' => 'publish',
			'p'           => $id
		);

		$jobs = new WP_Query( $args );

		if ( $jobs->have_posts() ) : ?>

			<?php while ( $jobs->have_posts() ) : $jobs->the_post(); ?>

				<div class="job_summary_shortcode align<?php echo $align ?>" style="width: <?php echo $width ? $width : auto; ?>">

					<?php get_job_manager_template_part( 'content-summary', 'job_listing' ); ?>

				</div>

			<?php endwhile; ?>

		<?php endif;

		wp_reset_postdata();

		return ob_get_clean();
	}
}

new WP_Job_Manager_Shortcodes();