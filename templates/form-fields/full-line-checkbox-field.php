<?php
/**
 * Shows a full line checkbox field.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/form-fields/full-line-checkbox-field.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     1.35.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$allowed_label_html = [
	'a' => [
		'href'   => [],
		'target' => [],
	],
];
?>
<fieldset class="fieldset-<?php echo esc_attr( $key ); ?> ">
	<div class="field full-line-checkbox-field <?php echo $field['required'] ? 'required-field' : ''; ?>">
		<input type="checkbox" class="input-checkbox" name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?>" id="<?php echo esc_attr( $key ); ?>" <?php checked( ! empty( $field['value'] ), true ); ?> value="1" <?php if ( isset( $field['checked'] ) && true === $field['checked'] ) echo 'checked'; ?> <?php if ( ! empty( $field['required'] ) ) echo 'required'; ?> />
		<label for="<?php echo esc_attr( $key ); ?>"><?php echo wp_kses( $field['label'], $allowed_label_html ); ?></label>
		<?php if ( ! empty( $field['description'] ) ) : ?><small class="description"><?php echo wp_kses_post( $field['description'] ); ?></small><?php endif; ?>
	</div>
</fieldset>
