/* global job_manager_ajax_file_upload */
jQuery(function($) {
	$('.wp-job-manager-file-upload').each(function(){
		$(this).fileupload({
			dataType: 'json',
			dropZone: $(this),
			url: job_manager_ajax_file_upload.ajax_url.toString().replace( '%%endpoint%%', 'upload_file' ),
			maxNumberOfFiles: 1,
			formData: {
				script: true
			},
			add: function (e, data) {
				var $file_field     = $( this );
				var $form           = $file_field.closest( 'form' );
				var $uploaded_files = $file_field.parent().find('.job-manager-uploaded-files');
				var uploadErrors    = [];

				// Validate type
				var allowed_types = $(this).data('file_types');

				if ( allowed_types ) {
					var acceptFileTypes = new RegExp( '(\.|\/)(' + allowed_types + ')$', 'i' );

					if ( data.originalFiles[0].name.length && ! acceptFileTypes.test( data.originalFiles[0].name ) ) {
						uploadErrors.push( job_manager_ajax_file_upload.i18n_invalid_file_type + ' ' + allowed_types );
					}
				}

				if ( uploadErrors.length > 0 ) {
					window.alert( uploadErrors.join( '\n' ) );
				} else {
					$form.find(':input[type="submit"]').attr( 'disabled', 'disabled' );
					data.context = $('<progress value="" max="100"></progress>').appendTo( $uploaded_files );
					data.submit();
				}
			},
			progress: function (e, data) {
				var progress        = parseInt(data.loaded / data.total * 100, 10);
				data.context.val( progress );
			},
			fail: function (e, data) {
				var $file_field     = $( this );
				var $form           = $file_field.closest( 'form' );

				if ( data.errorThrown ) {
					window.alert( data.errorThrown );
				}

				data.context.remove();

				$form.find(':input[type="submit"]').removeAttr( 'disabled' );
			},
			done: function (e, data) {
				var $file_field     = $( this );
				var $form           = $file_field.closest( 'form' );
				var $uploaded_files = $file_field.parent().find('.job-manager-uploaded-files');
				var multiple        = $file_field.attr( 'multiple' ) ? 1 : 0;
				var image_types     = [ 'jpg', 'gif', 'png', 'jpeg', 'jpe' ];

				data.context.remove();

				// Handle JSON errors when success is false
				if( typeof data.result.success !== 'undefined' && ! data.result.success ){
					window.alert( data.result.data );
				}

				$.each(data.result.files, function(index, file) {
					if ( file.error ) {
						window.alert( file.error );
					} else {
						var html;
						if ( $.inArray( file.extension, image_types ) >= 0 ) {
							html = $.parseHTML( job_manager_ajax_file_upload.js_field_html_img );
							$( html ).find('.job-manager-uploaded-file-preview img').attr( 'src', file.url );
						} else {
							html = $.parseHTML( job_manager_ajax_file_upload.js_field_html );
							$( html ).find('.job-manager-uploaded-file-name code').text( file.name );
						}

						$( html ).find('.input-text').val( file.url );
						$( html ).find('.input-text').attr( 'name', 'current_' + $file_field.attr( 'name' ) );

						if ( multiple ) {
							$uploaded_files.append( html );
						} else {
							$uploaded_files.html( html );
						}
					}
				});

				$form.find(':input[type="submit"]').removeAttr( 'disabled' );
			}
		});
	});
});
