<?php
/**
 * File containing the view for step 1 of the setup wizard.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h3><?php esc_html_e( 'Welcome to the Setup Wizard!', 'wp-job-manager' ); ?></h3>

<p><?php echo wp_kses_post( __( 'Thanks for installing <em>WP Job Manager</em>! Let\'s get your site ready to accept job listings.', 'wp-job-manager' ) ); ?></p>
<p><?php echo wp_kses_post( __( 'This setup wizard will walk you through the process of creating pages for job submissions, management, and listings.', 'wp-job-manager' ) ); ?></p>
<p>
	<?php
	// translators: Placeholder %s is the path to WPJM documentation site.
	echo wp_kses_post( sprintf( __( 'If you\'d prefer to skip this and set up your pages manually, our <a href="%s">documentation</a> will walk you through each step.', 'wp-job-manager' ), 'https://wpjobmanager.com/documentation/' ) );
	?>
</p>

<form method="post" action="<?php echo esc_url( add_query_arg( 'step', 2 ) ); ?>">
	<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'enable-usage-tracking' ) ); ?>" />

	<?php $this->maybe_output_opt_in_checkbox(); ?>

	<p class="submit">
		<input type="submit" value="<?php esc_html_e( 'Start setup', 'wp-job-manager' ); ?>" class="button button-primary" />
		<a href="<?php echo esc_url( add_query_arg( 'skip-job-manager-setup', 1, admin_url( 'index.php?page=job-manager-setup&step=3' ) ) ); ?>" class="button"><?php esc_html_e( 'Skip setup. I will set up the plugin manually.', 'wp-job-manager' ); ?></a>
	</p>
</form>
