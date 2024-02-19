/* global job_manager_datepicker */
jQuery(document).ready( function() {
	var dateFormatFunction = function(_dateValue) {
		var dateValue = _dateValue;
		var dateTokens = _dateValue.split("-");
		if((dateTokens)&&(dateTokens.length == 3)) {
			dateTokens[2] = "" + (parseInt(dateTokens[2], 10) + 1);

			// Need to ensure the length of the days is always 2 digits for consistent behaviour with the Date constructor.
			// E.G. "2024-02-4" gets converted to "February 4, 2024", but "2024-02-04" gets converted to "February 3, 2024", going back a day for some reason.
			dateTokens[2] = (dateTokens[2].length == 1) ? "0" + dateTokens[2] : dateTokens[2];

			// Using JQuery UI's datepicker functionality for consistency. However, we could alternatively use:
			//   dateValue = new Date(dateTokens.join("-").toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
			dateValue = jQuery.datepicker.formatDate("MM d, yy", new Date(dateTokens.join("-")));
		}
		return dateValue;
	}
	var $date_today = new Date();
	var datePickerOptions = {
		altFormat  : 'yy-mm-dd',
		minDate    : $date_today,
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

		// Fix for the hidden and displayed datepicker fields not holding the current values for _job_expires.
		$hidden_input.val($target[0].getAttribute("value"));
		$target.val(dateFormatFunction($target[0].getAttribute("value")));
	};

	jQuery( 'input.job-manager-datepicker, input#_job_expires' ).each( function() {
		initializeDatepicker( this );
	} );


	jQuery( document ).on( 'wpJobManagerFieldAdded', function ( e ) {
		initializeDatepicker( e.target );
	});
});
