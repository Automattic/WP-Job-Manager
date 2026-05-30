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
<body>
<div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'?>" class="email-wrap">

	<!--[if mso]>
	<table cellpadding="0" cellspacing="0" border="0" style="padding:0px;margin:0px;width:100%;">
		<tr><td colspan="3" style="padding:0px;margin:0px;font-size:20px;height:20px;" height="20">&nbsp;</td></tr>
		<tr>
			<td style="padding:0px;margin:0px;">&nbsp;</td>
			<td style="padding:0px;margin:0px;" width="560">
	<![endif]-->

	<div class="content-wrap">
