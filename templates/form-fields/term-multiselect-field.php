<?php
/**
 * Shows term `select` (multiple) form field on job listing forms.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/form-fields/term-multiselect-field.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     WP Job Manager
 * @category    Template
 * @version     1.22.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Get selected value
if ( isset( $field['value'] ) ) {
	$selected = $field['value'];
} elseif (  ! empty( $field['default'] ) && is_int( $field['default'] ) ) {
	$selected = $field['default'];
} elseif ( ! empty( $field['default'] ) && ( $term = get_term_by( 'slug', $field['default'], $field['taxonomy'] ) ) ) {
	$selected = $term->term_id;
} else {
	$selected = '';
}

wp_enqueue_script( 'wp-job-manager-term-multiselect' );

$args = array(
	'taxonomy'     => $field['taxonomy'],
	'hierarchical' => 1,
	'name'         => isset( $field['name'] ) ? $field['name'] : $key,
	'orderby'      => 'name',
	'selected'     => $selected,
	'hide_empty'   => false
);

if ( isset( $field['placeholder'] ) && ! empty( $field['placeholder'] ) ) $args['placeholder'] = $field['placeholder'];

job_manager_dropdown_categories( apply_filters( 'job_manager_term_multiselect_field_args', $args ) );

if ( ! empty( $field['description'] ) ) : ?><small class="description"><?php echo $field['description']; ?></small><?php endif; ?>
