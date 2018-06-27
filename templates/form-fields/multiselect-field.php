<?php
/**
 * Shows the `select` (multiple) form field on job listing forms.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/form-fields/checkbox-field.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     WP Job Manager
 * @category    Template
 * @version     1.31.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

wp_enqueue_script( 'wp-job-manager-multiselect' );
?>
<select multiple="multiple" name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?>[]" id="<?php echo esc_attr( $key ); ?>" class="job-manager-multiselect" <?php if ( ! empty( $field['required'] ) ) echo 'required'; ?> data-no_results_text="<?php esc_attr_e( 'No results match', 'wp-job-manager' ); ?>" data-multiple_text="<?php esc_attr_e( 'Select Some Options', 'wp-job-manager' ); ?>">
	<?php foreach ( $field['options'] as $key => $value ) : ?>
		<option value="<?php echo esc_attr( $key ); ?>" <?php if ( ! empty( $field['value'] ) && is_array( $field['value'] ) ) selected( in_array( $key, $field['value'] ), true ); ?>><?php echo esc_html( $value ); ?></option>
	<?php endforeach; ?>
</select>
<?php if ( ! empty( $field['description'] ) ) : ?><small class="description"><?php echo wp_kses_post( $field['description'] ); ?></small><?php endif; ?>
