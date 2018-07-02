<?php
/**
 * Email content for showing job details.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/emails/plain/email-job-details.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     WP Job Manager
 * @category    Template
 * @version     1.31.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

echo "\n\n";

if ( ! empty( $fields ) ) {
	foreach ( $fields as $field ) {
		echo esc_html( wp_strip_all_tags( $field[ 'label' ] )  .': '. wp_strip_all_tags( $field[ 'value' ] ) );
		if ( ! empty( $field['url'] ) ) {
			echo ' (' . esc_url( $field['url'] ) . ')';
		}
		echo "\n";
	}
}
