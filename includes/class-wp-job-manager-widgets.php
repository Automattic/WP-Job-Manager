<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Job Manager Widget base
 */
class WP_Job_Manager_Widget extends WP_Widget {

	public $widget_cssclass;
	public $widget_description;
	public $widget_id;
	public $widget_name;
	public $settings;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register();
	}

	/**
	 * Register Widget
	 */
	public function register() {
		$widget_ops = array(
			'classname'   => $this->widget_cssclass,
			'description' => $this->widget_description
		);

		parent::__construct( $this->widget_id, $this->widget_name, $widget_ops );

		add_action( 'save_post', array( $this, 'flush_widget_cache' ) );
		add_action( 'deleted_post', array( $this, 'flush_widget_cache' ) );
		add_action( 'switch_theme', array( $this, 'flush_widget_cache' ) );
	}

	/**
	 * get_cached_widget function.
	 */
	function get_cached_widget( $args ) {
		$cache = wp_cache_get( $this->widget_id, 'widget' );

		if ( ! is_array( $cache ) )
			$cache = array();

		if ( isset( $cache[ $args['widget_id'] ] ) ) {
			echo $cache[ $args['widget_id'] ];
			return true;
		}

		return false;
	}

	/**
	 * Cache the widget
	 */
	public function cache_widget( $args, $content ) {
		$cache[ $args['widget_id'] ] = $content;

		wp_cache_set( $this->widget_id, $cache, 'widget' );
	}

	/**
	 * Flush the cache
	 * @return [type]
	 */
	public function flush_widget_cache() {
		wp_cache_delete( $this->widget_id, 'widget' );
	}

	/**
	 * update function.
	 *
	 * @see WP_Widget->update
	 * @access public
	 * @param array $new_instance
	 * @param array $old_instance
	 * @return array
	 */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		if ( ! $this->settings )
			return $instance;

		foreach ( $this->settings as $key => $setting ) {
			$instance[ $key ] = sanitize_text_field( $new_instance[ $key ] );
		}

		$this->flush_widget_cache();

		return $instance;
	}

	/**
	 * form function.
	 *
	 * @see WP_Widget->form
	 * @access public
	 * @param array $instance
	 * @return void
	 */
	function form( $instance ) {

		if ( ! $this->settings )
			return;

		foreach ( $this->settings as $key => $setting ) {

			$value = isset( $instance[ $key ] ) ? $instance[ $key ] : $setting['std'];

			switch ( $setting['type'] ) {
				case 'text' :
					?>
					<p>
						<label for="<?php echo $this->get_field_id( $key ); ?>"><?php echo $setting['label']; ?></label>
						<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo $this->get_field_name( $key ); ?>" type="text" value="<?php echo esc_attr( $value ); ?>" />
					</p>
					<?php
				break;
				case 'number' :
					?>
					<p>
						<label for="<?php echo $this->get_field_id( $key ); ?>"><?php echo $setting['label']; ?></label>
						<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo $this->get_field_name( $key ); ?>" type="number" step="<?php echo esc_attr( $setting['step'] ); ?>" min="<?php echo esc_attr( $setting['min'] ); ?>" max="<?php echo esc_attr( $setting['max'] ); ?>" value="<?php echo esc_attr( $value ); ?>" />
					</p>
					<?php
				break;
			}
		}
	}
}

/**
 * Recent Jobs Widget
 */
class WP_Job_Manager_Widget_Recent_Jobs extends WP_Job_Manager_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wp_post_types;

		$this->widget_cssclass    = 'job_manager widget_recent_jobs';
		$this->widget_description = __( 'Display a list of recent listings on your site, optionally matching a keyword and location.', 'wp-job-manager' );
		$this->widget_id          = 'widget_recent_jobs';
		$this->widget_name        = sprintf( __( 'Recent %s', 'wp-job-manager' ), $wp_post_types['job_listing']->labels->name );
		$this->settings           = array(
			'title' => array(
				'type'  => 'text',
				'std'   => sprintf( __( 'Recent %s', 'wp-job-manager' ), $wp_post_types['job_listing']->labels->name ),
				'label' => __( 'Title', 'wp-job-manager' )
			),
			'keyword' => array(
				'type'  => 'text',
				'std'   => '',
				'label' => __( 'Keyword', 'wp-job-manager' )
			),
			'location' => array(
				'type'  => 'text',
				'std'   => '',
				'label' => __( 'Location', 'wp-job-manager' )
			),
			'number' => array(
				'type'  => 'number',
				'step'  => 1,
				'min'   => 1,
				'max'   => '',
				'std'   => 10,
				'label' => __( 'Number of listings to show', 'wp-job-manager' )
			)
		);
		$this->register();
	}

	/**
	 * widget function.
	 *
	 * @see WP_Widget
	 * @access public
	 * @param array $args
	 * @param array $instance
	 * @return void
	 */
	public function widget( $args, $instance ) {
		if ( $this->get_cached_widget( $args ) ) {
			return;
		}

		ob_start();

		extract( $args );

		$title  = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
		$number = absint( $instance['number'] );
		$jobs   = get_job_listings( array(
			'search_location'   => isset( $instance['location'] ) ? $instance['location'] : '',
			'search_keywords'   => isset( $instance['keyword'] ) ? $instance['keyword'] : '',
			'posts_per_page'    => $number,
			'orderby'           => 'date',
			'order'             => 'DESC',
		) );

		if ( $jobs->have_posts() ) : ?>

			<?php echo $before_widget; ?>

			<?php if ( $title ) echo $before_title . $title . $after_title; ?>

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

/**
 * Featured Jobs Widget
 */
class WP_Job_Manager_Widget_Featured_Jobs extends WP_Job_Manager_Widget {

	/**
	 * Constructor
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
				'label' => __( 'Title', 'wp-job-manager' )
			),
			'number' => array(
				'type'  => 'number',
				'step'  => 1,
				'min'   => 1,
				'max'   => '',
				'std'   => 10,
				'label' => __( 'Number of listings to show', 'wp-job-manager' )
			)
		);
		$this->register();
	}

	/**
	 * widget function.
	 *
	 * @see WP_Widget
	 * @access public
	 * @param array $args
	 * @param array $instance
	 * @return void
	 */
	public function widget( $args, $instance ) {
		if ( $this->get_cached_widget( $args ) ) {
			return;
		}

		ob_start();

		extract( $args );

		$title  = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
		$number = absint( $instance['number'] );
		$jobs   = get_job_listings( array(
			'posts_per_page' => $number,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'featured'       => true
		) );

		if ( $jobs->have_posts() ) : ?>

			<?php echo $before_widget; ?>

			<?php if ( $title ) echo $before_title . $title . $after_title; ?>

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

register_widget( 'WP_Job_Manager_Widget_Recent_Jobs' );
register_widget( 'WP_Job_Manager_Widget_Featured_Jobs' );
