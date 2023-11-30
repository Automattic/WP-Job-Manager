<?php
/**
 * Notice component for the frontend.
 *
 * @package wp-job-manager
 * @since $$next-version$$
 */

namespace WP_Job_Manager\UI;

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
class Notice {

	/**
	 * A success message.
	 *
	 * @param string|array $args A message, or an array of options accepted by Notice::render.
	 */
	public static function success( $args ) {
		$args = is_array( $args ) ? $args : [ 'message' => $args ];

		return self::render(
			array_merge(
				[
					'icon' => 'check',
				],
				$args
			)
		);
	}

	/**
	 * An error message.
	 *
	 * @param string|array $args A message, or an array of options accepted by Notice::render.
	 */
	public static function error( $args ) {
		$args = is_array( $args ) ? $args : [ 'message' => $args ];

		return self::render(
			array_merge(
				[
					'icon'    => 'alert',
					'classes' => array_merge( [ 'color-error' ], $args['classes'] ?? [] ),
				],
				$args
			)
		);
	}

	/**
	 * Notice element.
	 *
	 * @param array $options {
	 *    Options for the notice.
	 *
	 * @type string $id Unique ID of the notice.
	 * @type string $title Optional title.
	 * @type string $icon Notice icon. Should be a supported icon name, or safe SVG code.
	 * @type string $message Main notice message.
	 * @type string $details Additional HTML content for the notice. Displayed below the message.
	 * @type array  $buttons Action links styled as buttons.
	 * @type array  $links Action links.
	 * @type array  $classes Additional classes for the notice. Possible options:
	 *      color-error:     Error-style coloring.
	 *      color-success:   Green highlight.
	 *      color-info:      Blue highlight.
	 *      color-strong:    Stronger border opacity highlight.
	 *      type-hint:       Variation for a persistent hint.
	 *      message-icon:    Show the icon next to the message instead of the title.
	 *      alignwide:       Wide notice, with actions on the side.
	 * }
	 *
	 * @return string HTML.
	 */
	public static function render( $options ) {

		UI::ensure_styles();

		$options = wp_parse_args(
			$options,
			[
				'id'      => '',
				'title'   => '',
				'icon'    => '',
				'message' => '',
				'details' => '',
				'buttons' => [],
				'links'   => [],
				'classes' => [],
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

		$icon = self::get_icon_html( $options['icon'] );

		ob_start();

		$template_options = [
			'options'      => $options,
			'title'        => $options['title'],
			'classes'      => $options['classes'] ?? [],
			'message'      => $options['message'],
			'details'      => $options['details'],
			'icon_html'    => $icon,
			'buttons_html' => $buttons,
			'links_html'   => $links,
		];

		get_job_manager_template(
			'notice.php',
			$template_options
		);

		$notice_html = ob_get_clean();

		/**
		 * Filters notices. Return false to disable the notice.
		 *
		 * @since $$next-version$$
		 *
		 * @param string $notice_html Generated HTML for the notice.
		 * @param array  $options Notice template options.
		 */
		$notice_html = apply_filters( 'wpjm_notice', $notice_html, $template_options );

		if ( ! empty( $options['id'] ) ) {
			/**
			 * Filters an individual notice.Return false to disable the notice.
			 *
			 * @since $$next-version$$
			 *
			 * @param string $notice_html Generated HTML for the notice.
			 * @param array  $options Notice template options.
			 */
			$notice_html = apply_filters( 'wpjm_notice_' . $options['id'], $notice_html, $template_options );
		}

		return is_string( $notice_html ) ? $notice_html : '';
	}

	/**
	 * Generate HTML for a notice icon.
	 *
	 * @param string|null $icon Icon name or a safe SVG code.
	 *
	 * @return string Icon HTML.
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
	 * @param array  $args Button options.
	 * @param string $class Base classname.
	 *
	 * @return string Button HTML.
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
}
