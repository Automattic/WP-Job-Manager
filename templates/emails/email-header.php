<?php
/**
 * Header for email notifications.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/emails/email-header.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     1.31.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
	<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
</head>
<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">
<div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'?>">
	<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
		<tr>
			<td align="center" valign="top">
				<!-- Body -->
				<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_body">
					<tr>
						<td valign="top" id="body_content">
							<!-- Content -->
							<table border="0" cellpadding="20" cellspacing="0" width="100%">
								<tr>
									<td valign="top">
										<div id="body_content_inner">
