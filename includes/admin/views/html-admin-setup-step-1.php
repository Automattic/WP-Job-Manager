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
	echo wp_kses_post( sprintf( __( 'If you\'d prefer to skip this and set up your pages manually, our <a target="_blank" href="%s">documentation</a> will walk you through each step.', 'wp-job-manager' ), 'https://wpjobmanager.com/documentation/' ) );
	?>
</p>

<form method="post">
	<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'enable-usage-tracking' ) ); ?>" />

	<?php $this->maybe_output_opt_in_checkbox(); ?>

	<p class="submit">
		<input type="submit" name="start-setup" value="<?php esc_html_e( 'Start setup', 'wp-job-manager' ); ?>" class="button button-primary" formaction="<?php echo esc_url( add_query_arg( 'step', 2 ) ); ?>" />

		<input type="submit" name="skip-setup" value="<?php esc_html_e( 'Save and skip setup', 'wp-job-manager' ); ?>" class="button" formaction="<?php echo esc_url( add_query_arg( 'step', 3 ) ); ?>">

	</p>
</form>
