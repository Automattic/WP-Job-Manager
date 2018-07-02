<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Job Manager Widget base.
 *
 * @package wp-job-manager
 * @since 1.0.0
 */
class WP_Job_Manager_Widget extends WP_Widget {

	/**
	 * Widget CSS class.
	 *
	 * @var string
	 */
	public $widget_cssclass;

	/**
	 * Widget description.
	 *
	 * @var string
	 */
	public $widget_description;

	/**
	 * Widget id.
	 *
	 * @var string
	 */
	public $widget_id;

	/**
	 * Widget name.
	 *
	 * @var string
	 */
	public $widget_name;

	/**
	 * Widget settings.
	 *
	 * @var array
	 */
	public $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register();
	}

	/**
	 * Registers widget.
	 */
	public function register() {
		$widget_ops = array(
			'classname'   => $this->widget_cssclass,
			'description' => $this->widget_description,
		);

		parent::__construct( $this->widget_id, $this->widget_name, $widget_ops );

		add_action( 'save_post', array( $this, 'flush_widget_cache' ) );
		add_action( 'deleted_post', array( $this, 'flush_widget_cache' ) );
		add_action( 'switch_theme', array( $this, 'flush_widget_cache' ) );
	}

	/**
	 * Checks for cached version of widget and outputs it if found.
	 *
	 * @param array $args
	 * @return bool
	 */
	public function get_cached_widget( $args ) {
		$cache = wp_cache_get( $this->widget_id, 'widget' );

		if ( ! is_array( $cache ) ) {
			$cache = array();
		}

		if ( isset( $cache[ $args['widget_id'] ] ) ) {
			echo $cache[ $args['widget_id'] ]; // WPCS: XSS ok.
			return true;
		}

		return false;
	}

	/**
	 * Caches the widget.
	 *
	 * @param array  $args
	 * @param string $content
	 */
	public function cache_widget( $args, $content ) {
		$cache[ $args['widget_id'] ] = $content;

		wp_cache_set( $this->widget_id, $cache, 'widget' );
	}

	/**
	 * Flushes the cache for a widget.
	 */
	public function flush_widget_cache() {
		wp_cache_delete( $this->widget_id, 'widget' );
	}

	/**
	 * Updates a widget instance settings.
	 *
	 * @see WP_Widget->update
	 * @param array $new_instance
	 * @param array $old_instance
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		if ( ! $this->settings ) {
			return $instance;
		}

		foreach ( $this->settings as $key => $setting ) {
			$instance[ $key ] = sanitize_text_field( $new_instance[ $key ] );
		}

		$this->flush_widget_cache();

		return $instance;
	}

	/**
	 * Displays widget setup form.
	 *
	 * @see WP_Widget->form
	 * @param array $instance
	 * @return void
	 */
	public function form( $instance ) {

		if ( ! $this->settings ) {
			return;
		}

		foreach ( $this->settings as $key => $setting ) {

			$value = isset( $instance[ $key ] ) ? $instance[ $key ] : $setting['std'];

			switch ( $setting['type'] ) {
				case 'text':
					?>
					<p>
						<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_html( $setting['label'] ); ?></label>
						<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" type="text" value="<?php echo esc_attr( $value ); ?>" />
					</p>
					<?php
					break;
				case 'number':
					?>
					<p>
						<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_html( $setting['label'] ); ?></label>
						<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" type="number" step="<?php echo esc_attr( $setting['step'] ); ?>" min="<?php echo esc_attr( $setting['min'] ); ?>" max="<?php echo esc_attr( $setting['max'] ); ?>" value="<?php echo esc_attr( $value ); ?>" />
					</p>
					<?php
					break;
				case 'select':
					?>
					<p>
						<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_html( $setting['label'] ); ?></label>
						<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>">
							<?php foreach ( $setting['options'] as $option_key => $option_label ) : ?>
								<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $value, $option_key ); ?>><?php echo esc_html( $option_label ); ?></option>
							<?php endforeach; ?></select>
					</p>
					<?php
					break;
				case 'checkbox':
					?>
					<p>
						<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_html( $setting['label'] ); ?></label>
						<input class="checkbox" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" type="checkbox" value="1" <?php checked( $value, 1 ); ?> />
					</p>
					<?php
					break;
			}
		}
	}

	/**
	 * Gets the instance with the default values for all settings.
	 *
	 * @return array
	 */
	protected function get_default_instance() {
		$defaults = array();
		if ( ! empty( $this->settings ) ) {
			foreach ( $this->settings as $key => $setting ) {
				$defaults[ $key ] = null;
				if ( isset( $setting['std'] ) ) {
					$defaults[ $key ] = $setting['std'];
				}
			}
		}
		return $defaults;
	}

	/**
	 * Echoes the widget content.
	 *
	 * @see    WP_Widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {}
}
