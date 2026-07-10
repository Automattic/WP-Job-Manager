<?php
/**
 * Email content for showing job details.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/emails/plain/email-job-details.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     1.31.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

echo "\n\n";

if ( ! empty( $fields ) ) {
	foreach ( $fields as $field ) {
		// This body is text/plain. HTML escaping here would reach the recipient literally, as `&amp;`.
		$label = wp_strip_all_tags( wp_specialchars_decode( $field['label'], ENT_QUOTES ) );
		$value = wp_strip_all_tags( wp_specialchars_decode( $field['value'], ENT_QUOTES ) );

		echo $label . ': ' . $value;
		if ( ! empty( $field['url'] ) ) {
			// esc_url() would encode `&` as `&#038;`, breaking the link.
			echo ' (' . esc_url_raw( $field['url'] ) . ')';
		}
		echo "\n";
	}
}
