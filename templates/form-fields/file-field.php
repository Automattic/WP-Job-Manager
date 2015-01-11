<?php if ( ! empty( $field['value'] ) ) : ?>
	<div class="job-manager-uploaded-files">
	<?php if ( is_array( $field['value'] ) ) : ?>
		<?php foreach ( $field['value'] as $value ) : ?>
			<div class="job-manager-uploaded-file">
				<?php if ( in_array( substr( strrchr( $value, '.' ), 1 ), array( 'jpg', 'gif', 'png', 'jpeg', 'jpe' ) ) ) : ?>
					<span class="job-manager-uploaded-file-preview"><img src="<?php echo $value; ?>" /></span>
				<?php endif; ?>
				<?php echo '<span class="job-manager-uploaded-file-name"><code>' . basename( $value ) . ' <a class="job-manager-remove-uploaded-file" href="#">[' . __( 'remove', 'wp-job-manager' ) . ']</a></code></span>'; ?>
				<input type="hidden" class="input-text" name="current_<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?>[]" value="<?php echo esc_attr( $value ); ?>" />
			</div>
		<?php endforeach; ?>
	<?php elseif ( $value = $field['value'] ) : ?>
		<div class="job-manager-uploaded-file">
			<?php if ( in_array( substr( strrchr( $value, '.' ), 1 ), array( 'jpg', 'gif', 'png', 'jpeg', 'jpe' ) ) ) : ?>
				<span class="job-manager-uploaded-file-preview"><img src="<?php echo $value; ?>" /></span>
			<?php endif; ?>
			<?php echo '<span class="job-manager-uploaded-file-name"><code>' . basename( $value ) . ' <a class="job-manager-remove-uploaded-file" href="#">[' . __( 'remove', 'wp-job-manager' ) . ']</a></code> ' . __( 'or', 'wp-job-manager' ) . '&hellip;</span>'; ?>
			<input type="hidden" class="input-text" name="current_<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?>" value="<?php echo esc_attr( $field['value'] ); ?>" />
		</div>
	<?php endif; ?>
	</div>
<?php endif; ?>

<input type="file" class="input-text" <?php if ( ! empty( $field['multiple'] ) ) echo 'multiple'; ?> name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?><?php if ( ! empty( $field['multiple'] ) ) echo '[]'; ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo empty( $field['placeholder'] ) ? '' : esc_attr( $field['placeholder'] ); ?>" />
<small class="description">
	<?php if ( ! empty( $field['description'] ) ) : ?>
		<?php echo $field['description']; ?>
	<?php else : ?>
		<?php printf( __( 'Maximum file size: %s.', 'wp-job-manager' ), size_format( wp_max_upload_size() ) ); ?>
	<?php endif; ?>
</small>