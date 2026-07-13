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
		// Strip before decoding: a decoded `<` in `Sales <10k` reads as an unterminated tag and
		// strip_tags() would discard the rest of the value.
		$label = wp_specialchars_decode( wp_strip_all_tags( $field['label'] ), ENT_QUOTES );
		$value = wp_specialchars_decode( wp_strip_all_tags( $field['value'] ), ENT_QUOTES );

		echo $label . ': ' . $value;
		if ( ! empty( $field['url'] ) ) {
			// esc_url() would encode `&` as `&#038;`, breaking the link.
			echo ' (' . esc_url_raw( $field['url'] ) . ')';
		}
		echo "\n";
	}
}
