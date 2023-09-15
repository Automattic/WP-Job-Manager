/* global job_manager_datepicker */
jQuery(document).on( 'ready', function() {
	var datePickerOptions = {
		altFormat  : 'yy-mm-dd',
	};

	if ( typeof job_manager_datepicker !== 'undefined' ) {
		datePickerOptions.dateFormat = job_manager_datepicker.date_format;
	}

	var initializeDatepicker = function ( targetInput ) {
		var $hidden_input = jQuery( '<input />', { type: 'hidden', name: jQuery(targetInput).attr( 'name' ) } ).insertAfter( jQuery( targetInput ) );
		jQuery(targetInput).attr( 'name', jQuery(targetInput).attr( 'name' ) + '-datepicker' );
		jQuery(targetInput).keyup( function() {
			if ( '' === jQuery(targetInput).val() ) {
				$hidden_input.val( '' );
			}
		} );
		jQuery(targetInput).datepicker( jQuery.extend( {}, datePickerOptions, { altField: $hidden_input } ) );
		if ( jQuery(targetInput).val() ) {
			var dateParts = jQuery(targetInput).val().split('-');
			if ( 3 === dateParts.length ) {
				var selectedDate = new Date(parseInt(dateParts[0], 10), (parseInt(dateParts[1], 10) - 1), parseInt(dateParts[2], 10));
				jQuery(targetInput).datepicker('setDate', selectedDate);
			}
		}
	};

	jQuery( 'input.job-manager-datepicker, input#_job_expires' ).each( function() {
		initializeDatepicker( this );
	} );


	jQuery( document ).on( 'wpJobManagerFieldAdded', function ( e ) {
		initializeDatepicker( e.target );
	});
});
