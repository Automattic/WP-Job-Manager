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

		$form.addClass( 'disable-validation' );
		setTimeout( function() {
			$form.removeClass( 'disable-validation' );
		}, 1000 );

		$form.find( 'div.draft-required input[required]').each( function() {
			$(this).get( 0 ).reportValidity();
			if ( $(this).is( ':invalid' ) ) {
				is_valid = false;

				return false;
			}
		} );

		return is_valid;
	});

	$( document.body ).on( 'submit', '.job-manager-form', function( event ) {
		if ( ! $(this).hasClass( 'disable-validation' ) && specialFieldsAreInvalid() ) {
			event.preventDefault();
			return;
		}

		if ( ! $(this).hasClass( 'prevent-spinner-behavior' ) ) {
			$(this).find( '.spinner' ).addClass( 'is-active' );
			$(this).find( 'input[type=submit]' ).addClass( 'disabled' ).on( 'click', function() { return false; } );
		}
	});

	/* Performs validation for required fields that don't support HTML 5 validation.
	 * Returns true if any field was found to be invalid.
	 */
	function specialFieldsAreInvalid() {
		// Validate the job category field if present
		if ( jobCategoryFieldIsInvalid() ) {
			$(this).find( 'input[type=submit]' ).blur();

			var jobCategoryInput = $( '.select2-search__field' )[0];
			jobCategoryInput.setCustomValidity( job_manager_job_submission.i18n_required_field );
			jobCategoryInput.reportValidity();

			return true;
		}

		// Validate the description field if present and required
		if ( descriptionFieldIsInvalid() ) {
			$(this).find( 'input[type=submit]' ).blur();

			/* Hack: The textarea must be displayed in order to show the
			   validation prompt, so we set the height to 1 pixel */
			var editorTextArea = $( '#job_description' );
			editorTextArea.css( { 'height': 1, 'resize': 'none', 'padding': 0 } );
			editorTextArea.show();

			editorTextArea[0].setCustomValidity( job_manager_job_submission.i18n_required_field );
			editorTextArea[0].reportValidity();

			return true;
		}

		return false;
	}

	// Returns true if required job category field is empty and a select2 dropdown exists
	function jobCategoryFieldIsInvalid() {
		var jobCategory = $( '#job_category' );
		return jobCategory.length &&
				!jobCategory.val() &&
				jobCategory.parent().hasClass( 'required-field' ) &&
				jobCategory.next().hasClass('select2');
	}

	function descriptionFieldIsInvalid() {
		if ( !descriptionFieldIsPresent() ) {
			return false;
		}

		var jobDescription = tinymce.get('job_description').getContent();
		var editorTextArea = $( '#job_description' );

		return jobDescription.length === 0 &&
				editorTextArea.parents( '.required-field' ).length &&
				editorTextArea.parents( '.required-field' ).is(':visible');
	}

	function descriptionFieldIsPresent() {
		return typeof tinymce !== "undefined" &&
				tinymce.get( 'job_description' ) != null;
	}

	// Listen for changes to the category field to clear validity
	$( '#job_category' ).on( 'select2:select', function() {
		var jobCategoryInput = $( '.select2-search__field' )[0];
		jobCategoryInput.setCustomValidity( '' );
		jobCategoryInput.reportValidity();
	});

	// Listen for changes to the description field to clear validity
	setTimeout( function() {
		if ( !descriptionFieldIsPresent() ) {
			return;
		}

		tinymce.get( 'job_description' ).on( 'Change', function () {
			var editorTextArea = $( '#job_description' );
			editorTextArea.hide();
			editorTextArea[0].setCustomValidity( '' );
			editorTextArea[0].reportValidity();
		});
	}, 1000); // 1 second delay to wait for tinymce to load
});
