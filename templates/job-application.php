<?php if ( $apply = get_the_job_application_method() ) :
	wp_enqueue_script( 'wp-job-manager-job-application' );
	?>
	<div class="application">
		<input class="application_button" type="button" value="<?php _e( 'Apply for job', 'job_manager' ); ?>" />

		<div class="application_details">
			<?php
				switch ( $apply->type ) {
					case 'email' :

						echo '<p>' . sprintf( __( 'To apply for this job <strong>email your details to</strong> <a class="job_application_email" href="mailto:%1$s%2$s">%1$s</a>', 'job_manager' ), $apply->email, '?subject=' . rawurlencode( $apply->subject ) ) . '</p>';

						echo '<p>' . __( 'Apply using webmail: ', 'job_manager' );

						echo '<a href="' . 'https://mail.google.com/mail/?view=cm&fs=1&to=' . $apply->email . '&su=' . urlencode( $apply->subject ) .'" target="_blank" class="job_application_email">Gmail</a> / ';

						echo '<a href="' . 'http://webmail.aol.com/Mail/ComposeMessage.aspx?to=' . $apply->email . '&subject=' . urlencode( $apply->subject ) .'" target="_blank" class="job_application_email">AOL</a> / ';

						echo '<a href="' . 'http://compose.mail.yahoo.com/?to=' . $apply->email . '&subject=' . urlencode( $apply->subject ) .'" target="_blank" class="job_application_email">Yahoo</a> / ';

						echo '<a href="' . 'http://mail.live.com/mail/EditMessageLight.aspx?n=&to=' . $apply->email . '&subject=' . urlencode( $apply->subject ) .'" target="_blank" class="job_application_email">Outlook</a>';

						echo '</p>';

					break;
					case 'url' :
						echo '<p>' . sprintf( __( 'To apply for this job please visit the following URL: <a href="%1$s">%1$s &rarr;</a>', 'job_manager' ), $apply->url ) . '</p>';
					break;
				}
			?>
		</div>
	</div>
<?php endif; ?>