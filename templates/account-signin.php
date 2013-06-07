<?php if ( is_user_logged_in() ) : ?>

	<fieldset>
		<label><?php _e( 'Your account', 'job_manager' ); ?></label>
		<div class="field account-sign-in">
			<?php
				$user = wp_get_current_user();
				printf( __( 'You are currently signed in as <strong>%s</strong>.', 'job_manager' ), $user->user_login );
			?>

			<a class="button" href="<?php echo apply_filters( 'submit_job_form_logout_url', wp_logout_url( get_permalink() ) ); ?>"><?php _e( 'Sign out', 'job_manager' ); ?></a>
		</div>
	</fieldset>

<?php elseif ( get_option( 'job_manager_submission_enable_registration' ) == 1 ) :

	$account_required = get_option( 'job_manager_submission_requires_account' ) == 1 ? true : false;
	?>
	<fieldset>
		<label><?php _e( 'Have an account?', 'job_manager' ); ?></label>
		<div class="field account-sign-in">
			<a class="button" href="<?php echo apply_filters( 'submit_job_form_login_url', wp_login_url( get_permalink() ) ); ?>"><?php _e( 'Sign in', 'job_manager' ); ?></a>

			<?php printf( __( 'If you don&lsquo;t have an account you can %screate one below by entering your email address. A password will be automatically emailed to you.', 'job_manager' ), $account_required ? '' : __( 'optionally', 'job_manager' ) . ' ' ); ?>
		</div>
	</fieldset>
	<fieldset>
		<label><?php _e( 'Your email', 'job_manager' ); ?> <?php if ( ! $account_required ) echo '<small>' . __( '(optional)', 'job_manager' ) . '</small>'; ?></label>
		<div class="field">
			<input type="email" class="input-text" name="create_account_email" id="account_email" placeholder="you@yourdomain.com" value="<?php if ( ! empty( $_POST['create_account_email'] ) ) echo sanitize_text_field( stripslashes( $_POST['create_account_email'] ) ); ?>" />
		</div>
	</fieldset>

<?php else :

	$account_required = get_option( 'job_manager_submission_requires_account' ) == 1 ? true : false;
	?>
	<fieldset>
		<label><?php _e( 'Have an account?', 'job_manager' ); ?></label>
		<div class="field account-sign-in">
			<a class="button" href="<?php echo apply_filters( 'submit_job_form_login_url', wp_login_url( get_permalink() ) ); ?>"><?php _e( 'Sign in', 'job_manager' ); ?></a>

			<?php if ( $account_required ) : ?>
				<?php _e( 'You must sign in to create a new job listing.', 'job_manager' ); ?>
			<?php endif; ?>
		</div>
	</fieldset>

<?php endif; ?>
