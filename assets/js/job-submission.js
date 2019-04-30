jQuery(document).ready(function($) {
	$( document.body ).on( 'click', '.job-manager-remove-uploaded-file', function() {
		$(this).closest( '.job-manager-uploaded-file' ).remove();
		return false;
	});
	$( document.body ).on( 'submit', '.job-manager-form:not(.prevent-spinner-behavior)', function( event ) {
		// Validate the category field if present
		var jobCategory = $( '#job_category' );
		if ( jobCategory.length && !jobCategory.val() ) {
			event.preventDefault();
			$(this).find( 'input[type=submit]' ).blur();

			var jobCategoryInput = $( '.select2-search__field' )[0];
			jobCategoryInput.setCustomValidity( 'Category is required' );
			jobCategoryInput.reportValidity();
			
			return;
		}

		// Validate the description field if present
		if ( typeof tinymce !== undefined || tinymce.get( 'job_description' ) != null ) {
			var jobDescription = tinymce.get('job_description').getContent();
			var editorTextArea = $( '#job_description' );
			if ( jobDescription.length === 0 ) {
				event.preventDefault();
				$(this).find( 'input[type=submit]' ).blur();

				/* Hack: The textarea must be displayed in order to show the
				 * validation prompt, so we set the height to 1 pixel
				 */
				editorTextArea.css( { 'height': 1, 'resize': 'none', 'padding': 0 } );
				editorTextArea.show();

				editorTextArea[0].setCustomValidity( 'Description is required' );
				editorTextArea[0].reportValidity();

				return;
			}
		}

		$(this).find( '.spinner' ).addClass( 'is-active' );
		$(this).find( 'input[type=submit]' ).addClass( 'disabled' ).on( 'click', function() { return false; } );
	});

	// Listen for changes to the category field to clear validity
	$( '#job_category' ).on( 'select2:select', function( event ) {
		var jobCategoryInput = $( '.select2-search__field' )[0];
		jobCategoryInput.setCustomValidity( '' );
		jobCategoryInput.reportValidity();
	});

	// Listen for changes to the description field to clear validity
	setTimeout( function() {
		if ( typeof tinymce === undefined || tinymce.get( 'job_description' ) == null ) {
			return;
		}

		tinymce.get( 'job_description' ).on( 'Change', function ( editor, event ) {
			var editorTextArea = $( '#job_description' );
			editorTextArea.hide();
			editorTextArea[0].setCustomValidity( '' );
			editorTextArea[0].reportValidity();
		});
	}, 1000);
});
