<?php
/**
 * Setup page: Opt into usage tracking option.
 *
 * @package WP Job Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<p>
	<label>
		<input
			type="checkbox"
			name="job_manager_usage_tracking_enabled"
			value="1" />
		<?php
		echo wp_kses(
			$this->opt_in_text(),
			$usage_tracking->opt_in_dialog_text_allowed_html()
		);
		?>
	</label>
</p>
