<?php
/**
 * Shows the `file` form field on job listing forms.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/form-fields/file-field.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     WP Job Manager
 * @category    Template
 * @version     1.27.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$classes            = array( 'input-text' );
$allowed_mime_types = array_keys( ! empty( $field['allowed_mime_types'] ) ? $field['allowed_mime_types'] : get_allowed_mime_types() );
$field_name         = isset( $field['name'] ) ? $field['name'] : $key;
$field_name         .= ! empty( $field['multiple'] ) ? '[]' : '';

if ( ! empty( $field['ajax'] ) && job_manager_user_can_upload_file_via_ajax() ) {
	wp_enqueue_script( 'wp-job-manager-ajax-file-upload' );
	$classes[] = 'wp-job-manager-file-upload';
}
?>
<div class="job-manager-uploaded-files">
	<?php if ( ! empty( $field['value'] ) ) : ?>
		<?php if ( is_array( $field['value'] ) ) : ?>
			<?php foreach ( $field['value'] as $value ) : ?>
				<?php get_job_manager_template( 'form-fields/uploaded-file-html.php', array( 'key' => $key, 'name' => 'current_' . $field_name, 'value' => $value, 'field' => $field ) ); ?>
			<?php endforeach; ?>
		<?php elseif ( $value = $field['value'] ) : ?>
			<?php get_job_manager_template( 'form-fields/uploaded-file-html.php', array( 'key' => $key, 'name' => 'current_' . $field_name, 'value' => $value, 'field' => $field ) ); ?>
		<?php endif; ?>
	<?php endif; ?>
</div>

<input type="file" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-file_types="<?php echo esc_attr( implode( '|', $allowed_mime_types ) ); ?>" <?php if ( ! empty( $field['multiple'] ) ) echo 'multiple'; ?> name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?><?php if ( ! empty( $field['multiple'] ) ) echo '[]'; ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo empty( $field['placeholder'] ) ? '' : esc_attr( $field['placeholder'] ); ?>" />
<small class="description">
	<?php if ( ! empty( $field['description'] ) ) : ?>
		<?php echo $field['description']; ?>
	<?php else : ?>
		<?php printf( __( 'Maximum file size: %s.', 'wp-job-manager' ), size_format( wp_max_upload_size() ) ); ?>
	<?php endif; ?>
</small>
