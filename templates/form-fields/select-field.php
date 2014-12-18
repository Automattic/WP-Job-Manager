<select name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?>" id="<?php echo esc_attr( $key ); ?>" <?php if ( ! empty( $field['required'] ) ) echo 'required'; ?>>
	<?php foreach ( $field['options'] as $key => $value ) : ?>
		<option value="<?php echo esc_attr( $key ); ?>" <?php if ( isset( $field['value'] ) || isset( $field['default'] ) ) selected( isset( $field['value'] ) ? $field['value'] : $field['default'], $key ); ?>><?php echo esc_html( $value ); ?></option>
	<?php endforeach; ?>
</select>
<?php if ( ! empty( $field['description'] ) ) : ?><small class="description"><?php echo $field['description']; ?></small><?php endif; ?>
