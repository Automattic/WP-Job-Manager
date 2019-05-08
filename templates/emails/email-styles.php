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

$style_vars                   = array();
$style_vars['color_bg']       = '#fff';
$style_vars['color_fg']       = '#000';
$style_vars['color_light']    = '#eee';
$style_vars['color_link']     = '#036fa9';
$style_vars['font_family']    = '"Helvetica Neue", Helvetica, Roboto, Arial, sans-serif';

/**
 * Change the style vars used in email generation stylesheet.
 *
 * @since 1.31.0
 *
 * @param array $style_vars  Variables used in style generation.
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
?>

#wrapper {
	background-color: <?php echo esc_attr( $style_vars['color_bg'] ); ?>;
	color: <?php echo esc_attr( $style_vars['color_fg'] ); ?>;
	margin: 0;
	padding: 70px 0 70px 0;
	-webkit-text-size-adjust: none !important;
	width: 100%;
	font-family: <?php echo esc_attr( $style_vars['font_family'] ); ?>;
}

a {
	color: <?php echo esc_attr( $style_vars['color_link'] ); ?>;
	font-weight: normal;
	text-decoration: underline;
}

.email-container {
	margin-bottom: 10px;
}

td.detail-label,
td.detail-value {
	vertical-align: middle;
	border: 1px solid  <?php echo esc_attr( $style_vars['color_light'] ); ?>;
}

td.detail-label {
	word-wrap: break-word;
	width: 40%;
}

<?php
/**
 * Inject styles after the core styles.
 *
 * @since 1.31.0
 *
 * @param array $style_vars Variables used in style generation.
 */
do_action( 'job_manager_email_style_after', $style_vars );
