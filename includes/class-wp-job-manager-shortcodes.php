<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WP_Job_Manager_Shortcodes class.
 */
class WP_Job_Manager_Shortcodes {

	private $job_dashboard_message = '';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'shortcode_action_handler' ) );
		add_action( 'job_manager_job_dashboard_content_edit', array( $this, 'edit_job' ) );
		add_action( 'job_manager_job_filters_end', array( $this, 'job_filter_job_types' ), 20 );
		add_action( 'job_manager_job_filters_end', array( $this, 'job_filter_results' ), 30 );
		add_action( 'job_manager_output_jobs_no_results', array( $this, 'output_no_results' ) );
		add_shortcode( 'submit_job_form', array( $this, 'submit_job_form' ) );
		add_shortcode( 'job_dashboard', array( $this, 'job_dashboard' ) );
		add_shortcode( 'jobs', array( $this, 'output_jobs' ) );
		add_shortcode( 'job', array( $this, 'output_job' ) );
		add_shortcode( 'job_summary', array( $this, 'output_job_summary' ) );
		add_shortcode( 'job_apply', array( $this, 'output_job_apply' ) );
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
	public function submit_job_form( $atts = array() ) {
		return $GLOBALS['job_manager']->forms->get_form( 'submit-job', $atts );
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
				if ( ! job_manager_user_can_edit_job( $job_id ) ) {
					throw new Exception( __( 'Invalid ID', 'wp-job-manager' ) );
				}

				switch ( $action ) {
					case 'mark_filled' :
						// Check status
						if ( $job->_filled == 1 )
							throw new Exception( __( 'This position has already been filled', 'wp-job-manager' ) );

						// Update
						update_post_meta( $job_id, '_filled', 1 );

						// Message
						$this->job_dashboard_message = '<div class="job-manager-message">' . sprintf( __( '%s has been filled', 'wp-job-manager' ), $job->post_title ) . '</div>';
						break;
					case 'mark_not_filled' :
						// Check status
						if ( $job->_filled != 1 ) {
							throw new Exception( __( 'This position is not filled', 'wp-job-manager' ) );
						}

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
					case 'duplicate' :
						if ( ! job_manager_get_permalink( 'submit_job_form' ) ) {
							throw new Exception( __( 'Missing submission page.', 'wp-job-manager' ) );
						}

						$new_job_id = job_manager_duplicate_listing( $job_id );

						if ( $new_job_id ) {
							wp_redirect( add_query_arg( array( 'job_id' => absint( $new_job_id ) ), job_manager_get_permalink( 'submit_job_form' ) ) );
							exit;
						}

						break;
					case 'relist' :
						if ( ! job_manager_get_permalink( 'submit_job_form' ) ) {
							throw new Exception( __( 'Missing submission page.', 'wp-job-manager' ) );
						}

						// redirect to post page
						wp_redirect( add_query_arg( array( 'job_id' => absint( $job_id ) ), job_manager_get_permalink( 'submit_job_form' ) ) );
						exit;

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
			ob_start();
			get_job_manager_template( 'job-dashboard-login.php' );
			return ob_get_clean();
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
			'job_title' => __( 'Title', 'wp-job-manager' ),
			'filled'    => __( 'Filled?', 'wp-job-manager' ),
			'date'      => __( 'Date Posted', 'wp-job-manager' ),
			'expires'   => __( 'Listing Expires', 'wp-job-manager' )
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
			'per_page'                  => get_option( 'job_manager_per_page' ),
			'orderby'                   => 'featured',
			'order'                     => 'DESC',

			// Filters + cats
			'show_filters'              => true,
			'show_categories'           => true,
			'show_category_multiselect' => get_option( 'job_manager_enable_default_category_multiselect', false ),
			'show_pagination'           => false,
			'show_more'                 => true,

			// Limit what jobs are shown based on category and type
			'categories'                => '',
			'job_types'                 => '',
			'featured'                  => null, // True to show only featured, false to hide featured, leave null to show both.
			'filled'                    => null, // True to show only filled, false to hide filled, leave null to show both/use the settings.

			// Default values for filters
			'location'                  => '',
			'keywords'                  => '',
			'selected_category'         => '',
			'selected_job_types'        => implode( ',', array_values( get_job_listing_types( 'id=>slug' ) ) ),
		) ), $atts ) );

		if ( ! get_option( 'job_manager_enable_categories' ) ) {
			$show_categories = false;
		}

		// String and bool handling
		$show_filters              = $this->string_to_bool( $show_filters );
		$show_categories           = $this->string_to_bool( $show_categories );
		$show_category_multiselect = $this->string_to_bool( $show_category_multiselect );
		$show_more                 = $this->string_to_bool( $show_more );
		$show_pagination           = $this->string_to_bool( $show_pagination );

		if ( ! is_null( $featured ) ) {
			$featured = ( is_bool( $featured ) && $featured ) || in_array( $featured, array( '1', 'true', 'yes' ) ) ? true : false;
		}

		if ( ! is_null( $filled ) ) {
			$filled = ( is_bool( $filled ) && $filled ) || in_array( $filled, array( '1', 'true', 'yes' ) ) ? true : false;
		}

		// Array handling
		$categories         = is_array( $categories ) ? $categories : array_filter( array_map( 'trim', explode( ',', $categories ) ) );
		$job_types          = is_array( $job_types ) ? $job_types : array_filter( array_map( 'trim', explode( ',', $job_types ) ) );
		$selected_job_types = is_array( $selected_job_types ) ? $selected_job_types : array_filter( array_map( 'trim', explode( ',', $selected_job_types ) ) );

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

			get_job_manager_template( 'job-filters.php', array( 'per_page' => $per_page, 'orderby' => $orderby, 'order' => $order, 'show_categories' => $show_categories, 'categories' => $categories, 'selected_category' => $selected_category, 'job_types' => $job_types, 'atts' => $atts, 'location' => $location, 'keywords' => $keywords, 'selected_job_types' => $selected_job_types, 'show_category_multiselect' => $show_category_multiselect ) );

			get_job_manager_template( 'job-listings-start.php' );
			get_job_manager_template( 'job-listings-end.php' );

			if ( ! $show_pagination && $show_more ) {
				echo '<a class="load_more_jobs" href="#" style="display:none;"><strong>' . __( 'Load more listings', 'wp-job-manager' ) . '</strong></a>';
			}

		} else {

			$jobs = get_job_listings( apply_filters( 'job_manager_output_jobs_args', array(
				'search_location'   => $location,
				'search_keywords'   => $keywords,
				'search_categories' => $categories,
				'job_types'         => $job_types,
				'orderby'           => $orderby,
				'order'             => $order,
				'posts_per_page'    => $per_page,
				'featured'          => $featured,
				'filled'            => $filled
			) ) );

			if ( $jobs->have_posts() ) : ?>

				<?php get_job_manager_template( 'job-listings-start.php' ); ?>

				<?php while ( $jobs->have_posts() ) : $jobs->the_post(); ?>
					<?php get_job_manager_template_part( 'content', 'job_listing' ); ?>
				<?php endwhile; ?>

				<?php get_job_manager_template( 'job-listings-end.php' ); ?>

				<?php if ( $jobs->found_posts > $per_page && $show_more ) : ?>

					<?php wp_enqueue_script( 'wp-job-manager-ajax-filters' ); ?>

					<?php if ( $show_pagination ) : ?>
						<?php echo get_job_listing_pagination( $jobs->max_num_pages ); ?>
					<?php else : ?>
						<a class="load_more_jobs" href="#"><strong><?php _e( 'Load more listings', 'wp-job-manager' ); ?></strong></a>
					<?php endif; ?>

				<?php endif; ?>

			<?php else :
				do_action( 'job_manager_output_jobs_no_results' );
			endif;

			wp_reset_postdata();
		}

		$data_attributes_string = '';
		$data_attributes        = array(
			'location'        => $location,
			'keywords'        => $keywords,
			'show_filters'    => $show_filters ? 'true' : 'false',
			'show_pagination' => $show_pagination ? 'true' : 'false',
			'per_page'        => $per_page,
			'orderby'         => $orderby,
			'order'           => $order,
			'categories'      => implode( ',', $categories ),
		);
		if ( ! is_null( $featured ) ) {
			$data_attributes[ 'featured' ] = $featured ? 'true' : 'false';
		}
		if ( ! is_null( $filled ) ) {
			$data_attributes[ 'filled' ]   = $filled ? 'true' : 'false';
		}
		foreach ( $data_attributes as $key => $value ) {
			$data_attributes_string .= 'data-' . esc_attr( $key ) . '="' . esc_attr( $value ) . '" ';
		}

		$job_listings_output = apply_filters( 'job_manager_job_listings_output', ob_get_clean() );

		return '<div class="job_listings" ' . $data_attributes_string . '>' . $job_listings_output . '</div>';
	}

	/**
	 * Output some content when no results were found
	 */
	public function output_no_results() {
		get_job_manager_template( 'content-no-jobs-found.php' );
	}

	/**
	 * Get string as a bool
	 * @param  string $value
	 * @return bool
	 */
	public function string_to_bool( $value ) {
		return ( is_bool( $value ) && $value ) || in_array( $value, array( '1', 'true', 'yes' ) ) ? true : false;
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
			'id'       => '',
			'width'    => '250px',
			'align'    => 'left',
			'featured' => null, // True to show only featured, false to hide featured, leave null to show both (when leaving out id)
			'limit'    => 1
		), $atts ) );

		ob_start();

		$args = array(
			'post_type'   => 'job_listing',
			'post_status' => 'publish'
		);

		if ( ! $id ) {
			$args['posts_per_page'] = $limit;
			$args['orderby']        = 'rand';
			if ( ! is_null( $featured ) ) {
				$args['meta_query'] = array( array(
					'key'     => '_featured',
					'value'   => '1',
					'compare' => $featured ? '=' : '!='
				) );
			}
		} else {
			$args['p'] = absint( $id );
		}

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

	/**
	 * Show the application area
	 */
	public function output_job_apply( $atts ) {
		extract( shortcode_atts( array(
			'id'       => ''
		), $atts ) );

		ob_start();

		$args = array(
			'post_type'   => 'job_listing',
			'post_status' => 'publish'
		);

		if ( ! $id ) {
			return '';
		} else {
			$args['p'] = absint( $id );
		}

		$jobs = new WP_Query( $args );

		if ( $jobs->have_posts() ) : ?>

			<?php while ( $jobs->have_posts() ) :
				$jobs->the_post();
				$apply = get_the_job_application_method();
				?>

				<?php do_action( 'job_manager_before_job_apply_' . absint( $id ) ); ?>

				<?php if ( apply_filters( 'job_manager_show_job_apply_' . absint( $id ), true ) ) : ?>
					<div class="job-manager-application-wrapper">
						<?php do_action( 'job_manager_application_details_' . $apply->type, $apply ); ?>
					</div>
				<?php endif; ?>

				<?php do_action( 'job_manager_after_job_apply_' . absint( $id ) ); ?>

			<?php endwhile; ?>

		<?php endif;

		wp_reset_postdata();

		return ob_get_clean();
	}
}

new WP_Job_Manager_Shortcodes();
