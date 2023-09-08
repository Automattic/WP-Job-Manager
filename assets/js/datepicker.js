/* global job_manager_datepicker */
jQuery(document).on( 'ready wpJobManagerFieldAdded', function() {
	var datePickerOptions = {
		altFormat  : 'yy-mm-dd',
	};

	if ( typeof job_manager_datepicker !== 'undefined' ) {
		datePickerOptions.dateFormat = job_manager_datepicker.date_format;
	}

	jQuery( 'input.job-manager-datepicker, input#_job_expires' ).each( function(){
		var $hidden_input = jQuery( '<input />', { type: 'hidden', name: jQuery(this).attr( 'name' ) } ).insertAfter( jQuery( this ) );
		jQuery(this).attr( 'name', jQuery(this).attr( 'name' ) + '-datepicker' );
		jQuery(this).keyup( function() {
			if ( '' === jQuery(this).val() ) {
				$hidden_input.val( '' );
			}
		} );
		jQuery(this).datepicker( jQuery.extend( {}, datePickerOptions, { altField: $hidden_input } ) );
		if ( jQuery(this).val() ) {
			var dateParts = jQuery(this).val().split("-");
			if ( 3 === dateParts.length ) {
				var selectedDate = new Date(parseInt(dateParts[0], 10), (parseInt(dateParts[1], 10) - 1), parseInt(dateParts[2], 10));
				jQuery(this).datepicker('setDate', selectedDate);
			}
		}
	});
});
