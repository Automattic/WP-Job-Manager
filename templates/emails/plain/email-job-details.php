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
 * @version     1.31.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! empty( $fields ) ) {
	_e( 'Job listing details', 'wp-job-manager' );
	foreach ( $fields as $field ) {
		echo strip_tags( $field[ 'label' ] )  .': '. strip_tags( $field[ 'value' ] ) . "\n";
	}
}
