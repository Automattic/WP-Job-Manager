<?php
/**
 * Shows the `Terms & Condition` form field on job listing forms.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/form-fields/terms-field.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 *
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>
<fieldset class="fieldset-<?php echo esc_attr($key); ?>">
	<label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($field['label']); ?></label>
	<div class="field <?php echo $field['required'] ? 'required-field' : ''; ?>">
    <input type="checkbox" class="input-checkbox" name="<?php echo esc_attr(isset($field['name']) ? $field['name'] : $key); ?>" id="<?php echo esc_attr($key); ?>" <?php checked(! empty($field['value']), true); ?> value="1" <?php if (! empty($field['required'])) {
    echo 'required';
} ?> />
<?php if (! empty($field['description'])) : ?><?php echo wp_kses_post($field['description']); ?><?php endif; ?>
	</div>
</fieldset>
