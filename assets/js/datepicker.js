/* global job_manager_datepicker */
jQuery(document).ready( function() {
	var datePickerOptions = {
		altFormat  : 'yy-mm-dd',
	};

	if ( typeof job_manager_datepicker !== 'undefined' ) {
		datePickerOptions.dateFormat = job_manager_datepicker.date_format;
	}

	var initializeDatepicker = function ( targetInput ) {
		var $target = jQuery( targetInput );
		var $hidden_input = jQuery( '<input />', { type: 'hidden', name: $target.attr( 'name' ) } ).insertAfter( $target );

		$target.attr( 'name', $target.attr( 'name' ) + '-datepicker' );
		$target.on( 'keyup', function() {
			if ( '' === $target.val() ) {
				$hidden_input.val( '' );
			}
		} );
		$target.datepicker( jQuery.extend( {}, datePickerOptions, { altField: $hidden_input } ) );
		$target.datepicker("option", "dateFormat", $target.datepicker("option", "dateFormat") || "MM d, yy");
		if ( $target.val() ) {
			var dateParts = $target.val().split('-');
			if ( 3 === dateParts.length ) {
				var selectedDate = new Date(parseInt(dateParts[0], 10), (parseInt(dateParts[1], 10) - 1), parseInt(dateParts[2], 10));
				$target.datepicker('setDate', selectedDate);
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
