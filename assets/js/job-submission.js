jQuery(document).ready(function($) {
	$( document.body ).on( 'click', '.job-manager-remove-uploaded-file', function() {
		var $inputField = $(this).closest( '.fieldset-type-file' ).find( 'input[type=file][multiple][data-file_limit]' );

		$(this).closest( '.job-manager-uploaded-file' ).remove();
		$inputField.trigger( 'update_status' );

		return false;
	});

	$( document.body ).on( 'update_status', '.fieldset-type-file input[type=file][multiple][data-file_limit]', function(){
		var fileLimit     = parseInt( $(this).data( 'file_limit' ), 10 );
		var currentFiles  = parseInt( $(this).siblings( '.job-manager-uploaded-files' ).first().children( '.job-manager-uploaded-file' ).length, 10);
		var fileLimitLeft = fileLimit - currentFiles;

		if ( fileLimitLeft > 0 ) {
			$(this).prop( 'disabled', false );
		} else {
			$(this).prop( 'disabled', true );
		}

		$(this).data( 'file_limit_left', fileLimitLeft );
	} );

	$( document.body ).on( 'change', '.fieldset-type-file input[type=file][multiple][data-file_limit]', function(){
		var fileLimit     = parseInt( $(this).data( 'file_limit' ), 10 );
		var currentFiles  = parseInt( $(this).siblings( '.job-manager-uploaded-files' ).first().children( '.job-manager-uploaded-file' ).length, 10);
		var fileLimitLeft = fileLimit - currentFiles;
		var rawElement    = $(this).get(0);

		if ( typeof rawElement.files !== 'undefined' ) {
			var filesUploaded = parseInt( rawElement.files.length, 10 );
			if ( fileLimit && filesUploaded > fileLimitLeft ) {
				var message = job_manager_job_submission.i18n_over_upload_limit;
				if ($(this).data( 'file_limit_message' ) && typeof $(this).data( 'file_limit_message' ) === 'string' ) {
					message = $(this).data( 'file_limit_message' );
				}

				message = message.replace( '%d', fileLimit );
				rawElement.setCustomValidity( message );
			} else {
				rawElement.setCustomValidity( '' );
			}

			rawElement.reportValidity();
		}

		return true;
	} );

	$( '.fieldset-type-file input[type=file][multiple][data-file_limit]' ).trigger( 'update_status' );


	$( document.body ).on( 'click', '#submit-job-form .button.save_draft', function() {
		var $form    = $(this).closest( '#submit-job-form' );
		var is_valid = true;

		$form.find( 'div.draft-required input[required]').each( function() {
			$(this).get( 0 ).reportValidity();
			if ( $(this).is( ':invalid' ) ) {
				is_valid = false;
			}
		} );

		return is_valid;
	});

	$( document.body ).on( 'submit', '.job-manager-form:not(.prevent-spinner-behavior)', function() {
		$(this).find( '.spinner' ).addClass( 'is-active' );
		$(this).find( 'input[type=submit]' ).addClass( 'disabled' ).on( 'click', function() { return false; } );
	});
});
