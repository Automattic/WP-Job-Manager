<?php
/**
 * Shows the `recaptcha` form field on job listing forms.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/form-fields/recaptcha-field.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     WP Job Manager
 * @category    Template
 * @version     1.30.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<fieldset class="fieldset-<?php echo esc_attr( $key ); ?>">
	<label for="<?php echo esc_attr( $key ); ?>"><?php echo $field['label']; ?></label>
	<div class="field <?php echo $field['required'] ? 'required-field' : ''; ?>">
		<div class="g-recaptcha" data-sitekey="<?php echo $field['site_key'] ?>"></div>
		<?php if ( ! empty( $field['description'] ) ) : ?><small class="description"><?php echo $field['description']; ?></small><?php endif; ?>
	</div>
</fieldset>
