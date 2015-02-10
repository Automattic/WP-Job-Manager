<?php
$field_name = isset( $field['name'] ) ? $field['name'] : $key;
$field_name .= ! empty( $field['multiple'] ) ? '[]' : '';

if ( ! empty( $field['value'] ) ) : ?>
	<div class="job-manager-uploaded-files">
	<?php if ( is_array( $field['value'] ) ) : ?>
		<?php foreach ( $field['value'] as $value ) : ?>
			<?php get_job_manager_template( 'form-fields/uploaded-file-html.php', array( 'key' => $key, 'name' => 'current_' . $field_name, 'value' => $value, 'field' => $field ) ); ?>
		<?php endforeach; ?>
	<?php elseif ( $value = $field['value'] ) : ?>
		<?php get_job_manager_template( 'form-fields/uploaded-file-html.php', array( 'key' => $key, 'name' => 'current_' . $field_name, 'value' => $value, 'field' => $field ) ); ?>
	<?php endif; ?>
	</div>
<?php endif; ?>

<input type="file" class="input-text" <?php if ( ! empty( $field['multiple'] ) ) echo 'multiple'; ?> name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?><?php if ( ! empty( $field['multiple'] ) ) echo '[]'; ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo empty( $field['placeholder'] ) ? '' : esc_attr( $field['placeholder'] ); ?>" <?php if ( ! empty( $field['required'] ) && empty( $field['value'] ) ) echo 'required'; ?> />
<small class="description">
	<?php if ( ! empty( $field['description'] ) ) : ?>
		<?php echo $field['description']; ?>
	<?php else : ?>
		<?php printf( __( 'Maximum file size: %s.', 'wp-job-manager' ), size_format( wp_max_upload_size() ) ); ?>
	<?php endif; ?>
</small>