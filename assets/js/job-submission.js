jQuery(document).ready(function($) {
	$( document.body ).on( 'click', '.job-manager-remove-uploaded-file', function() {
		$(this).closest( '.job-manager-uploaded-file' ).remove();
		return false;
	});
	$( document.body ).on( 'submit', '.job-manager-form:not(.prevent-spinner-behavior)', function( event ) {
		if ( specialFieldsAreInvalid() ) {
			event.preventDefault();
			return;
		}

		$(this).find( '.spinner' ).addClass( 'is-active' );
		$(this).find( 'input[type=submit]' ).addClass( 'disabled' ).on( 'click', function() { return false; } );
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
				editorTextArea.parents( '.required-field' ).length;
	}

	function descriptionFieldIsPresent() {
		return typeof tinymce !== undefined ||
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
		if ( typeof tinymce === undefined || tinymce.get( 'job_description' ) == null ) {
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
