<?php
/**
 * Shows `checkbox` form fields in a list from a list on job listing forms.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/form-fields/term-checklist-field.php.
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
?>
<ul class="job-manager-term-checklist job-manager-term-checklist-<?php echo esc_attr( $key ); ?>">
<?php
	require_once( ABSPATH . '/wp-admin/includes/template.php' );

	if ( empty( $field['default'] ) ) {
		$field['default'] = '';
	}

	$args = [
		'descendants_and_self'  => 0,
		'selected_cats'         => isset( $field['value'] ) ? $field['value'] : ( is_array( $field['default'] ) ? $field['default'] : [ $field['default'] ] ),
		'popular_cats'          => false,
		'taxonomy'              => $field['taxonomy'],
		'checked_ontop'         => false
	];

	// $field['post_id'] needs to be passed via the args so we can get the existing terms.
	ob_start();
	wp_terms_checklist( 0, $args );
	$checklist = ob_get_clean();
	echo str_replace( "disabled='disabled'", '', $checklist );
?>
</ul>
<?php if ( ! empty( $field['description'] ) ) : ?><small class="description"><?php echo wp_kses_post( $field['description'] ); ?></small><?php endif; ?>
