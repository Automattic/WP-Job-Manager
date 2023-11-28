<?php
/**
 * Notice component for the frontend.
 *
 * @package wp-job-manager
 * @since 1.32.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Notice component for the frontend.
 *
 * @since $$next-version$$
 *
 * @internal This API is still under development and subject to change.
 * @access private
 */
class WP_Job_Manager_Ui_Notice {

	/**
	 * The singleton instance of the class.
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Singleton instance getter.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Notice element.
	 *
	 * @param array $options {
	 *    Options for the notice.
	 *
	 * @type string $title Optional title.
	 * @type string $icon Notice icon. Should be a supported icon name, or safe SVG code.
	 * @type string $message Main notice message.
	 * @type string $message_icon Alternative to icon, if it should be placed next to the message, not the title.
	 * @type string $details Additional details.
	 * @type array  $buttons Action links styled as buttons.
	 * @type array  $links Action links.
	 * @type array  $classes Additional classes for the notice.
	 * }
	 *
	 * @return string HTML.
	 */
	public static function notice( $options ) {

		$options = wp_parse_args(
			$options,
			[
				'title'        => '',
				'icon'         => '',
				'message'      => '',
				'message_icon' => '',
				'details'      => '',
				'buttons'      => [],
				'links'        => [],
				'classes'      => [],
			]
		);

		$buttons = [];

		foreach ( $options['buttons'] as $index => $button ) {
			$primary   = $button['primary'] ?? ( 0 === $index );
			$class     = $primary ? 'jm-notice__button' : 'jm-notice__button--outline';
			$buttons[] = self::get_button_html( $button, $class );
		}

		$links = [];
		foreach ( $options['links'] as $link ) {
			$class   = $buttons ? 'jm-notice__button--link' : 'jm-notice__action';
			$links[] = self::get_button_html( $link, $class );
		}

		$has_actions_footer = $buttons || $links;

		$icon         = self::get_icon_html( $options['icon'] );
		$message_icon = self::get_icon_html( $options['message_icon'] );

		$classes = $options['classes'] ?? [];

		if ( $has_actions_footer ) {
			$classes[] = 'has-actions';
		}

		if ( $options['title'] ) {
			$classes[] = 'has-header';
		}

		ob_start();
		?>

		<div class="jm-notice <?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<?php if ( $options['title'] ) : ?>
				<div class="jm-notice__header">
					<?php echo $icon; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper function. ?>
					<div class="jm-notice__title"><?php echo esc_html( $options['title'] ); ?></div>
				</div>
			<?php endif; ?>
			<?php if ( $options['message'] ) : ?>
				<div
					class="jm-notice__message-wrap">
					<?php if ( ! $options['title'] && ( $icon ) ) : ?>
						<?php echo $icon; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper function. ?>
					<?php endif; ?>
					<?php echo $message_icon; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper function. ?>
					<div
						class="jm-notice__message <?php echo esc_attr( $options['details'] ? 'has-details' : '' ); ?> "><?php echo wp_kses_post( $options['message'] ); ?></div>
				</div>
			<?php endif; ?>
			<?php if ( $options['details'] ) : ?>
				<div class="jm-notice__details"><?php echo wp_kses_post( $options['details'] ); ?></div>
			<?php endif; ?>
			<?php if ( $has_actions_footer ) : ?>
				<div class="jm-notice__footer">
					<?php if ( $buttons ) : ?>
						<div class="jm-notice__buttons">
							<?php echo implode( '', $buttons ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper function. ?>
						</div>
					<?php endif; ?>
					<?php if ( $links ) : ?>
						<div class="jm-notice__actions">
							<?php echo implode( '', $links ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper function. ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php

		return ob_get_clean();
	}

	/**
	 * Generate HTML for a notice icon.
	 *
	 * @param string|null $icon Icon name or an SVG code.
	 *
	 * @return string
	 */
	private static function get_icon_html( $icon ) {
		$html = '';

		if ( ! empty( $icon ) ) {

			$is_classname = preg_match( '/^[\w-]+$/i', $icon );

			$html = '<div class="jm-notice__icon"';

			if ( $is_classname ) {
				$html .= ' data-icon="' . esc_attr( $icon ) . '"';
			}

			$html .= '>';

			if ( ! $is_classname ) {
				$html .= ( $icon );
			}

			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * Generate HTML for a button or action link.
	 *
	 * @param array  $args
	 * @param string $class
	 *
	 * @return string
	 */
	private static function get_button_html( $args, $class ) {

		$html = '';

		if ( ! empty( $args ) ) {

			$args = wp_parse_args(
				$args,
				[
					'label' => '',
					'url'   => '',
				]
			);

			$html = '<a href="' . esc_url( $args['url'] ) . '" class="' . esc_attr( $class ) . '"><span>' . esc_html( $args['text'] ) . '</span></a>';
		}

		return $html;

	}

	/**
	 * Register and enqueue styles.
	 *
	 * @access private
	 */
	public function enqueue_styles() {
		WP_Job_Manager::register_style( 'wp-job-manager-ui', 'css/ui.css', [] );

		if ( $this->has_ui ) {
			wp_enqueue_style( 'wp-job-manager-ui' );
		}
	}

	/**
	 * Request the styles to be loaded for the page.
	 */
	public static function ensure_styles() {
		self::instance()->has_ui = true;
	}
}

WP_Job_Manager_Ui::instance();
