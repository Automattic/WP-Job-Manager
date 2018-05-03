<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Featured Jobs widget.
 *
 * @package wp-job-manager
 * @since 1.21.0
 */
class WP_Job_Manager_Widget_Featured_Jobs extends WP_Job_Manager_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wp_post_types;

		$this->widget_cssclass    = 'job_manager widget_featured_jobs';
		$this->widget_description = __( 'Display a list of featured listings on your site.', 'wp-job-manager' );
		$this->widget_id          = 'widget_featured_jobs';
		$this->widget_name        = sprintf( __( 'Featured %s', 'wp-job-manager' ), $wp_post_types['job_listing']->labels->name );
		$this->settings           = array(
			'title' => array(
				'type'  => 'text',
				'std'   => sprintf( __( 'Featured %s', 'wp-job-manager' ), $wp_post_types['job_listing']->labels->name ),
				'label' => __( 'Title', 'wp-job-manager' ),
			),
			'number' => array(
				'type'  => 'number',
				'step'  => 1,
				'min'   => 1,
				'max'   => '',
				'std'   => 10,
				'label' => __( 'Number of listings to show', 'wp-job-manager' ),
			),
			'orderby' => array(
				'type'  => 'select',
				'std'   => 'date',
				'label' => __( 'Sort By', 'wp-job-manager' ),
				'options' => array(
					'date'           => __( 'Date', 'wp-job-manager' ),
					'title'          => __( 'Title', 'wp-job-manager' ),
					'author'         => __( 'Author', 'wp-job-manager' ),
					'rand_featured'  => __( 'Random', 'wp-job-manager' ),
				),
			),
			'order' => array(
				'type'  => 'select',
				'std'   => 'DESC',
				'label' => __( 'Sort Direction', 'wp-job-manager' ),
				'options' => array(
					'ASC'   => __( 'Ascending', 'wp-job-manager' ),
					'DESC'  => __( 'Descending', 'wp-job-manager' ),
				),
			),
		);
		$this->register();
	}

	/**
	 * Echoes the widget content.
	 *
	 * @see WP_Widget
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		wp_enqueue_style( 'wp-job-manager-job-listings' );

		if ( $this->get_cached_widget( $args ) ) {
			return;
		}

		$instance = array_merge( $this->get_default_instance(), $instance );

		ob_start();

		extract( $args );
		$titleInstance = esc_attr( $instance['title'] );
		$number  = absint( $instance['number'] );
		$orderby = esc_attr( $instance['orderby'] );
		$order   = esc_attr( $instance['order'] );
		$title   = apply_filters( 'widget_title', $titleInstance, $instance, $this->id_base );
		$jobs    = get_job_listings( array(
			'posts_per_page' => $number,
			'orderby'        => $orderby,
			'order'          => $order,
			'featured'       => true,
		) );

		if ( $jobs->have_posts() ) : ?>

			<?php echo $before_widget; ?>

			<?php if ( $title ) { echo $before_title . $title . $after_title;} ?>

			<ul class="job_listings">

				<?php while ( $jobs->have_posts() ) : $jobs->the_post(); ?>

					<?php get_job_manager_template_part( 'content-widget', 'job_listing' ); ?>

				<?php endwhile; ?>

			</ul>

			<?php echo $after_widget; ?>

		<?php else : ?>

			<?php get_job_manager_template_part( 'content-widget', 'no-jobs-found' ); ?>

		<?php endif;

		wp_reset_postdata();

		$content = ob_get_clean();

		echo $content;

		$this->cache_widget( $args, $content );
	}
}

register_widget( 'WP_Job_Manager_Widget_Featured_Jobs' );
