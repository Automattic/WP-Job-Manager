<?php
/**
 * Setup page: Step 2 content.
 *
 * @package WP Job Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<h3><?php esc_html_e( 'Page Setup', 'wp-job-manager' ); ?></h3>

<p><?php esc_html_e( 'With WP Job Manager, employers and applicants can post, manage, and browse job listings right on your website. Tell us which of these common pages you\'d like your site to have and we\'ll create and configure them for you.', 'wp-job-manager' ); ?></p>
<p>
	<?php
	echo wp_kses_post(
		sprintf(
			// translators: %1$s is URL to WordPress core shortcode documentation. %2$s is URL to WPJM specific shortcode reference.
			__(
				'(These pages are created using <a href="%1$s" title="What is a shortcode?" class="help-page-link">shortcodes</a>,
								which we take care of in this step. If you\'d like to build these pages yourself or want to add one of these options to an existing
								page on your site, you can skip this step and take a look at <a href="%2$s" class="help-page-link">shortcode documentation</a> for detailed instructions.)', 'wp-job-manager'
			),
			'http://codex.wordpress.org/Shortcode',
			'https://wpjobmanager.com/document/shortcode-reference/'
		)
	);
	?>
</p>

<form action="<?php echo esc_url( add_query_arg( 'step', 3 ) ); ?>" method="post">
	<?php wp_nonce_field( 'step_3', 'setup_wizard' ); ?>
	<table class="wp-job-manager-shortcodes widefat">
		<thead>
		<tr>
			<th>&nbsp;</th>
			<th><?php esc_html_e( 'Page Title', 'wp-job-manager' ); ?></th>
			<th><?php esc_html_e( 'Page Description', 'wp-job-manager' ); ?></th>
			<th><?php esc_html_e( 'Content Shortcode', 'wp-job-manager' ); ?></th>
		</tr>
		</thead>
		<tbody>
		<tr>
			<td><input type="checkbox" checked="checked" name="wp-job-manager-create-page[submit_job_form]" /></td>
			<td><input type="text" value="<?php echo esc_attr( _x( 'Post a Job', 'Default page title (wizard)', 'wp-job-manager' ) ); ?>" name="wp-job-manager-page-title[submit_job_form]" /></td>
			<td>
				<p><?php esc_html_e( 'Creates a page that allows employers to post new jobs directly from a page on your website, instead of requiring them to log in to an admin area. If you\'d rather not allow this -- for example, if you want employers to use the admin dashboard only -- you can uncheck this setting.', 'wp-job-manager' ); ?></p>
			</td>
			<td><code>[submit_job_form]</code></td>
		</tr>
		<tr>
			<td><input type="checkbox" checked="checked" name="wp-job-manager-create-page[job_dashboard]" /></td>
			<td><input type="text" value="<?php echo esc_attr( _x( 'Job Dashboard', 'Default page title (wizard)', 'wp-job-manager' ) ); ?>" name="wp-job-manager-page-title[job_dashboard]" /></td>
			<td>
				<p><?php esc_html_e( 'Creates a page that allows employers to manage their job listings directly from a page on your website, instead of requiring them to log in to an admin area. If you want to manage all job listings from the admin dashboard only, you can uncheck this setting.', 'wp-job-manager' ); ?></p>
			</td>
			<td><code>[job_dashboard]</code></td>
		</tr>
		<tr>
			<td><input type="checkbox" checked="checked" name="wp-job-manager-create-page[jobs]" /></td>
			<td><input type="text" value="<?php echo esc_attr( _x( 'Jobs', 'Default page title (wizard)', 'wp-job-manager' ) ); ?>" name="wp-job-manager-page-title[jobs]" /></td>
			<td><?php esc_html_e( 'Creates a page where visitors can browse, search, and filter job listings.', 'wp-job-manager' ); ?></td>
			<td><code>[jobs]</code></td>
		</tr>
		</tbody>
		<tfoot>
		<tr>
			<th colspan="4">
				<input type="submit" class="button button-primary" value="Create selected pages" />
				<a href="<?php echo esc_url( add_query_arg( 'step', 3 ) ); ?>" class="button"><?php esc_html_e( 'Skip this step', 'wp-job-manager' ); ?></a>
			</th>
		</tr>
		</tfoot>
	</table>
</form>
