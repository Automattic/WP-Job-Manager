<?php
/**
 * Email stylesheet.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/emails/email-styles.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     1.31.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$style_vars                      = [];
$style_vars['color_bg']          = '#FFF';
$style_vars['color_fg']          = '#000';
$style_vars['color_light']       = '#F6F7F7';
$style_vars['color_stroke']      = '#E6E6E6';
$style_vars['color_link']        = '#0453EB';
$style_vars['color_button']      = $style_vars['color_link'];
$style_vars['color_button_text'] = '#FFF';
$style_vars['font_family']       = '-apple-system, "SF Pro Text", BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';

/**
 * Change the style vars used in email generation stylesheet.
 *
 * @since 1.31.0
 *
 * @param array $style_vars Variables used in style generation.
 */
$style_vars = apply_filters( 'job_manager_email_style_vars', $style_vars );

/**
 * Inject styles before the core styles.
 *
 * @since 1.31.0
 *
 * @param array $style_vars Variables used in style generation.
 */
do_action( 'job_manager_email_style_before', $style_vars );

$color_bg          = esc_attr( $style_vars['color_bg'] );
$color_fg          = esc_attr( $style_vars['color_fg'] );
$color_light       = esc_attr( $style_vars['color_light'] );
$color_stroke      = esc_attr( $style_vars['color_stroke'] );
$color_link        = esc_attr( $style_vars['color_link'] );
$color_button      = esc_attr( $style_vars['color_button'] );
$color_button_text = esc_attr( $style_vars['color_button_text'] );
$font_family       = wp_strip_all_tags( $style_vars['font_family'] );

echo <<<CSS

body {
	padding: 0;
	margin: 0;
}

#wrapper {
	background-color: {$color_light};
	color: {$color_fg};
	margin: 0;
	padding: 0;
	font-size: initial;
	font-family: {$font_family};
}

.content-wrap {
	max-width: 600px;
	padding: 32px 12px;
	background: {$color_bg};
	border-radius: 2px;
	line-height: 150%;
	word-wrap: break-word;
	margin: 0 auto;
}

p {
	margin: 12px 0;
}

a {
	color: {$color_link};
	text-decoration: underline;
}

a:hover {
	color: inherit !important;
}

.button-single {
	margin: 24px 0;
	text-align: center;
	padding: 12px 24px;
	background: {$color_button};
	color: {$color_button_text};
	cursor: pointer;
	font-style: normal;
	font-weight: 600;
	line-height: 180%;
	text-decoration: unset;
	display: block;
	border-radius: 2px;
}

.button-single:hover {
	background: {$color_fg};
	color: {$color_bg};
}

.box {
	border: 1px solid {$color_stroke};
	padding: 24px;
	margin: 24px 0;
}

.footer {
	margin: 24px 0;
}

.small-separator {
	margin: 24px 0;
	width: 80px;
	height: 1px;
	background: {$color_light};
}

.actions {
	margin: 24px 0;
	text-align: center;
	padding: 18px 24px;
	background: {$color_light};
}

.action {
	color: {$color_link};
	text-decoration: underline;
}

.footer__content {
	margin: 24px 0;
	font-size: 87.5%;
}


.email-container {
	margin-bottom: 10px;
}

td.detail-label,
td.detail-value {
	vertical-align: middle;
	border: 1px solid {$color_light};
}

td.detail-label {
	word-wrap: break-word;
	width: 40%;
}


@media screen and (min-width: 600px) {
	.email-wrap {
		padding: 24px !important;
	}

	.content-wrap {
		padding: 48px 32px !important;
	}
}

@media screen and (max-width: 325px) {
	.actions .action {
		display: block !important;
		line-height: 32px;
	}

	.actions .action-separator {
		display: none !important;
	}
}

CSS;

/**
 * Inject styles after the core styles.
 *
 * @since 1.31.0
 *
 * @param array $style_vars Variables used in style generation.
 */
do_action( 'job_manager_email_style_after', $style_vars );
