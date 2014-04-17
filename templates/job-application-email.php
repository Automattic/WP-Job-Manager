<p><?php printf( __( 'To apply for this job <strong>email your details to</strong> <a class="job_application_email" href="mailto:%1$s%2$s">%1$s</a>', 'wp-job-manager' ), $apply->email, '?subject=' . rawurlencode( $apply->subject ) ); ?></p>

<p>
	<?php _e( 'Apply using webmail: ', 'wp-job-manager' ); ?>

	<a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?php echo $apply->email; ?>&su=<?php echo urlencode( $apply->subject ); ?>" target="_blank" class="job_application_email">Gmail</a> / 
	
	<a href="http://webmail.aol.com/Mail/ComposeMessage.aspx?to=<?php echo $apply->email; ?>&subject=<?php echo urlencode( $apply->subject ); ?>" target="_blank" class="job_application_email">AOL</a> / 
	
	<a href="http://compose.mail.yahoo.com/?to=<?php echo $apply->email; ?>&subject=<?php echo urlencode( $apply->subject ); ?>" target="_blank" class="job_application_email">Yahoo</a> / 
	
	<a href="http://mail.live.com/mail/EditMessageLight.aspx?n=&to=<?php echo $apply->email; ?>&subject=<?php echo urlencode( $apply->subject ); ?>" target="_blank" class="job_application_email">Outlook</a>

</p>