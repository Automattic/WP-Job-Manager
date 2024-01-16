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
class UI_Elements {

	/**
	 * Generate HTML for a notice icon.
	 *
	 * @param string|null $icon Icon name or a safe SVG code.
	 *
	 * @return string Icon HTML.
	 */
	public static function icon( $icon ) {

		if ( empty( $icon ) ) {
			return '';
		}

		$html = '';

		$is_classname = preg_match( '/^[\w-]+$/i', $icon );

		$html = '<div class="jm-notice__icon"';

		if ( $is_classname ) {
			$html .= ' data-icon="' . esc_attr( $icon ) . '"';
		}

		$html .= '>';

		if ( ! $is_classname ) {
			$html .= self::esc_svg( $icon );
		}

		$html .= '</div>';

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
	public static function button( $args, $class ) {

		if ( empty( $args ) ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			[
				'label'   => '',
				'url'     => '',
				'onclick' => '',
			]
		);

		if ( empty( $args['url'] ) ) {
			$args['url'] = '#';
		}

		$button = new \WP_HTML_Tag_Processor( '<a><span>' . esc_html( $args['label'] ) . '</span></a>' );
		$button->next_tag();

		$button->add_class( $class );
		$button->set_attribute( 'href', $args['url'] );

		if ( ! empty( $args['onclick'] ) ) {
			$button->set_attribute( 'onclick', $args['onclick'] );
			$button->set_attribute( 'role', 'button' );
		}

		return $button->get_updated_html();

	}

	/**
	 * Generate HTML for a row of actions. Can contain button and/or link actions.
	 *
	 * Unless set otherwise, the first button will be styled as primary, and the rest as outline.
	 * Links will match button dimensions if buttons are also present.
	 *
	 * @param array $buttons Button definitions.
	 * @param array $links Link definitions.
	 *
	 * @return string Actions HTML.
	 */
	public static function actions( array $buttons = [], array $links = [] ) {

		$actions_html = [];

		if ( ! empty( $buttons ) ) {
			foreach ( $buttons as $index => $button ) {
				$primary        = $button['primary'] ?? ( 0 === $index );
				$class          = $primary ? 'jm-ui-button' : 'jm-ui-button--outline';
				$actions_html[] = self::button( $button, $class );
			}
		}

		if ( ! empty( $links ) ) {
			foreach ( $links as $link ) {
				$class          = $buttons ? 'jm-ui-button--link' : 'jm-ui-link';
				$actions_html[] = self::button( $link, $class );
			}
		}

		if ( empty( $actions_html ) ) {
			return '';
		}

		return '<div class="jm-ui-actions">' . implode( '', $actions_html ) . '</div>';

	}

	/**
	 * Escape SVG code.
	 *
	 * TODO Extend rules to support more SVG tags and attributes.
	 *
	 * @param string $code SVG code.
	 *
	 * @return string Sanitized SVG code.
	 */
	private static function esc_svg( $code ) {
		$kses_defaults = wp_kses_allowed_html( 'post' );
		$svg_args      = [
			'svg'  => [
				'class'           => true,
				'aria-hidden'     => true,
				'aria-labelledby' => true,
				'role'            => true,
				'xmlns'           => true,
				'width'           => true,
				'height'          => true,
				'viewbox'         => true,
				'fill'            => true,
			],
			'path' => [
				'd'            => true,
				'fill'         => true,
				'fill-rule'    => true,
				'stroke-width' => true,
				'stroke'       => true,
				'clip-rule'    => true,
			],
		];

		$allowed_tags = array_merge( $kses_defaults, $svg_args );

		return wp_kses( $code, $allowed_tags );
	}
}
