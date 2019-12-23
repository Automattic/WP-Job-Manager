<?php
/**
 * Job listings bulk edit - shows input field for bulk edit in admin dashboard.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

	<fieldset class="inline-edit-col-left">
			<label>
				<span class="title"><?php esc_html_e( 'Job type', 'wp-job-manager' ); ?></span>
				<span class="input-text-wrap">
					<label style="display:inline;">
						<input type="radio" name="job_listing_type" value="freelance" /> Freelance
					</label></br>
					<label style="display:inline;">
						<input type="radio" name="job_listing_type" value="full-time" /> Full Time
					</label></br>
					<label style="display:inline;">
						<input type="radio" name="job_listing_type" value="internship" /> Internship
					</label></br>
					<label style="display:inline;">
						<input type="radio" name="job_listing_type" value="part-time" /> Part Time
					</label></br>
					<label style="display:inline;">
						<input type="radio" name="job_listing_type" value="temporary" /> Temporary
					</label>
				</span>
			</label>
	</fieldset>
