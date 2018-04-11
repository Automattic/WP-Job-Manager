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
	<div class="job-manager-email-job-details-container email-container">
		<table border="0" cellpadding="10" cellspacing="0" width="100%" class="job-manager-email-job-details details">
			<?php foreach ( $fields as $field ) : ?>
			<tr>
				<td class="detail-label" style="text-align:<?php echo $text_align; ?>;">
					<?php echo wp_kses_post( $field['label'] ); ?>
				</td>
				<td class="detail-value" style="text-align:<?php echo $text_align; ?>;">
					<?php echo wp_kses_post( $field['value'] ); ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>
	</div>
<?php endif; ?>
