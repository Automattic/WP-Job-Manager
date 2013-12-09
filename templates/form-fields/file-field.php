<?php if ( ! empty( $field['value'] ) ) : ?>
	<div class="job-manager-uploaded-image uploaded_image">
		<img src="<?php echo $field['value']; ?>" /> <?php echo '<code>' . basename( $field['value'] ) . ' <a class="job-manager-remove-uploaded-image" href="#">[' . __( 'remove', 'job_manager' ) . ']</a>' . '</code> ' . __( 'or', 'job_manager' ) . '&hellip;'; ?>
		<input type="hidden" class="input-text" name="current_<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?>" value="<?php echo isset( $field['value'] ) ? esc_attr( $field['value'] ) : ''; ?>" />
	</div>
<?php endif; ?>

<input type="file" class="input-text" name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" />
<small class="description">
	<?php printf( __( 'Max. file size: %s. Allowed images: jpg, gif, png.', 'job_manager' ), size_format( wp_max_upload_size() ) ); ?>
</small>