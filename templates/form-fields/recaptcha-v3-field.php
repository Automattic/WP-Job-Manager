<?php
/**
 * Shows the `recaptcha` form field on job listing forms.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/form-fields/recaptcha-v3-field.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     $$next-version$$
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
$site_key = get_option( 'job_manager_recaptcha_site_key' );
?>
<script>
	function onClick(e) {
		e.preventDefault();
		grecaptcha.ready(function() {
			grecaptcha.execute( decodeURIComponent( '<?php echo rawurlencode( (string) $site_key ) ?>' ), { action: 'submit' } ).then( function( token ) {
				document.getElementById( 'g-recaptcha-response' ).value = token;
				document.getElementById( 'submit-job-form' ).submit();
			});
		});
	}
</script>

<input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
<input type="hidden" name="submit_job" value="true">

