<?php
/**
 * Bare page for the edit job form, loaded in a frame inside the job dashboard overlay.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/job-dashboard-edit-overlay.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     $$next-version$$
 */

use WP_Job_Manager\Job_Dashboard_Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Render the form first, so the assets it enqueues are printed in wp_head.
$content = Job_Dashboard_Shortcode::instance()->output_job_dashboard( [] );

// The dashboard reloads on close when the listing was saved.
$body_classes = [ 'jm-job-edit-overlay' ];
if ( did_action( 'job_manager_user_edit_job_listing' ) ) {
	$body_classes[] = 'jm-job-edit-saved';
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
	<style>
		body.jm-job-edit-overlay {
			margin: 0;
			padding: 64px 32px 32px;
		}
	</style>
</head>
<body <?php body_class( $body_classes ); ?>>
<?php wp_body_open(); ?>
<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the job dashboard and form templates.
echo $content;
?>
<?php wp_footer(); ?>
</body>
</html>
