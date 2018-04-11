<?php
/**
 * Email stylesheet.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/emails/email-styles.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     WP Job Manager
 * @category    Template
 * @version     1.31.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$color_bg       = apply_filters( 'job_manager_email_style_background_color', '#fff');
$color_fg       = apply_filters( 'job_manager_email_style_foreground_color', '#000');
$color_link     = apply_filters( 'job_manager_email_style_link_color', '#036fa9');
$font_family    = apply_filters( 'job_manager_email_style_font_family', '"Helvetica Neue", Helvetica, Roboto, Arial, sans-serif' );
?>

#wrapper {
	background-color: <?php echo esc_attr( $color_bg ); ?>;
	color: <?php echo esc_attr( $color_fg ); ?>;
	margin: 0;
	padding: 70px 0 70px 0;
	-webkit-text-size-adjust: none !important;
	width: 100%;
	font-family: <?php echo esc_attr( $font_family ); ?>;
}

a {
	color: <?php echo esc_attr( $color_link ); ?>;
	font-weight: normal;
	text-decoration: underline;
}
