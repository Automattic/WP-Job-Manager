<?php
/**
 * Email content for showing job details.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/emails/email-job-details.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     WP Job Manager
 * @category    Template
 * @version     1.31.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$text_align = is_rtl() ? 'right' : 'left';

if ( ! empty( $fields ) ) : ?>
	<div style="margin-bottom: 20px;" class="job-manager-email-job-details-container job-manager-email-container">
		<table border="0" cellpadding="10" cellspacing="0" width="600" class="job-manager-email-job-details">
			<?php foreach ( $fields as $field ) : ?>
			<tr>
				<td width="30%" style="text-align:<?php echo $text_align; ?>; vertical-align: middle; border: 1px solid #eee; word-wrap: break-word;">
					<?php echo wp_kses_post( $field['label'] ); ?>
				</td>
				<td class="td" style="text-align:<?php echo $text_align; ?>; vertical-align: middle; border: 1px solid #eee;">
					<?php echo wp_kses_post( $field['value'] ); ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>
	</div>
<?php endif; ?>
