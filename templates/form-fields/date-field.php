<?php
/**
 * Shows a datepicker field on job listing forms.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/form-fields/date-field.php.
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

wp_enqueue_script( 'wp-job-manager-datepicker' );
wp_enqueue_style( 'jquery-ui' );

?>
<?php
$field_date_value = isset( $field['value'] ) ? $field['value'] : '';
$field_time_value = '';
if ( strpos( $field_date_value, ' ' ) ) {
	$field_time_value = substr( strrchr( $field_date_value, ' ' ), 1, 5 );
	$field_date_value = substr( $field_date_value, 0, 10 );
}
?>
<input type="text" class="input-date job-manager-datepicker" name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?>"<?php if ( isset( $field['autocomplete'] ) && false === $field['autocomplete'] ) { echo ' autocomplete="off"'; } ?> id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo empty( $field['placeholder'] ) ? '' : esc_attr( $field['placeholder'] ); ?>" value="<?php echo esc_attr( $field_date_value ); ?>" <?php if ( ! empty( $field['required'] ) ) echo 'required'; ?> />
<?php if ( ! empty( $field['enable_time'] ) ) : ?>
<input type="time" class="input-time" name="<?php echo esc_attr( ( isset( $field['name'] ) ? $field['name'] : $key ) . '-time' ); ?>" value="<?php echo esc_attr( $field_time_value ); ?>" <?php if ( ! empty( $field['required'] ) ) echo 'required'; ?> />
<?php endif; ?>
<?php if ( ! empty( $field['description'] ) ) : ?><small class="description"><?php echo wp_kses_post( $field['description'] ); ?></small><?php endif; ?>
