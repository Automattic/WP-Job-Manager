/* global job_manager_admin */
jQuery(document).ready(function($) {
	// Tooltips
	$( '.tips, .help_tip' ).tipTip({
		'attribute' : 'data-tip',
		'fadeIn' : 50,
		'fadeOut' : 50,
		'delay' : 200
	});

	// Author
	$( 'p.form-field-author' ).on( 'click', 'a.change-author', function() {
		$(this).closest( 'p' ).find('.current-author').hide();
		$(this).closest( 'p' ).find('.change-author').show();
		return false;
	});

	// Datepicker
	$( 'input.job-manager-datepicker, input#_job_expires' ).datepicker({
		altFormat  : 'yy-mm-dd',
		dateFormat : job_manager_admin.date_format,
		minDate    : 0
	});

	$( 'input.job-manager-datepicker, input#_job_expires' ).each( function(){
		if ( $(this).val() ) {
			var date = new Date( $(this).val() );
			$(this).datepicker( 'setDate', date );
		}
	});

	// Uploading files
	var file_frame;
	var file_target_input;
	var file_target_wrapper;

	$(document).on('click', '.wp_job_manager_add_another_file_button', function( event ){
		event.preventDefault();

		var field_name        = $( this ).data( 'field_name' );
		var field_placeholder = $( this ).data( 'field_placeholder' );
		var button_text       = $( this ).data( 'uploader_button_text' );
		var button            = $( this ).data( 'uploader_button' );
		var view_button       = $( this ).data( 'view_button' );

		$( this ).before( '<span class="file_url"><input type="text" name="' + field_name + '[]" placeholder="' + field_placeholder + '" /><button class="button button-small wp_job_manager_upload_file_button" data-uploader_button_text="' + button_text + '">' + button + '</button><button class="button button-small wp_job_manager_view_file_button">' + view_button + '</button></span>' );
	} );

	$(document).on('click', '.wp_job_manager_view_file_button', function ( event ) {
		event.preventDefault();

		file_target_wrapper = $( this ).closest( '.file_url' );
		file_target_input = file_target_wrapper.find( 'input' );

		var attachment_url = file_target_input.val();

		if ( attachment_url.indexOf( '://' ) > - 1 ) {
			window.open( attachment_url, '_blank' );
		} else {
			file_target_input.addClass( 'file_no_url' );
			setTimeout( function () {
				file_target_input.removeClass( 'file_no_url' );
			}, 1000 );
		}

	});

	$(document).on('click', '.wp_job_manager_upload_file_button', function( event ){
		event.preventDefault();

		file_target_wrapper = $( this ).closest('.file_url');
		file_target_input   = file_target_wrapper.find('input');

		// If the media frame already exists, reopen it.
		if ( file_frame ) {
			file_frame.open();
			return;
		}

		// Create the media frame.
		file_frame = wp.media.frames.file_frame = wp.media({
			title: $( this ).data( 'uploader_title' ),
			button: {
				text: $( this ).data( 'uploader_button_text' )
			},
			multiple: false  // Set to true to allow multiple files to be selected
		});

		// When an image is selected, run a callback.
		file_frame.on( 'select', function() {
			// We set multiple to false so only get one image from the uploader
			var attachment = file_frame.state().get('selection').first().toJSON();

			$( file_target_input ).val( attachment.url );
		});

		// Finally, open the modal
		file_frame.open();
	});
});

jQuery(document).ready(function($) {
    var taxonomy = 'job_listing_type';
    $('#' + taxonomy + 'checklist li :radio, #' + taxonomy + 'checklist-pop :radio').live( 'click', function(){
        var t = $(this), c = t.is(':checked'), id = t.val();
        $('#' + taxonomy + 'checklist li :radio, #' + taxonomy + 'checklist-pop :radio').prop('checked',false);
        $('#in-' + taxonomy + '-' + id + ', #in-popular-' + taxonomy + '-' + id).prop( 'checked', c );
    });
});
