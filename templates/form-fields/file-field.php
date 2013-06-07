<?php if ( ! empty( $field['value'] ) ) : ?>
	<div class="uploaded_image">
		<img src="<?php echo $field['value']; ?>" /> <?php echo '<code>' . basename( $field['value'] ) . '</code> ' . __( 'or', 'job_manager' ) . '&hellip;'; ?>
		<input type="hidden" class="input-text" name="<?php echo esc_attr( $key ); ?>" value="<?php echo isset( $field['value'] ) ? esc_attr( $field['value'] ) : ''; ?>" />
	</div>
<?php endif; ?>

<input type="file" class="input-text" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" />
<small class="description">
	<?php printf( __( 'Max. file size: %s. Allowed images: jpg, gif, png.', 'job_manager' ), size_format( wp_max_upload_size() ) ); ?>
</small>