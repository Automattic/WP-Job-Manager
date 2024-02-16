/* global job_manager_datepicker */
jQuery(document).ready( function() {
	var date_format_function = function(_dateValue) {
		var dateValue = _dateValue;
		var date_tokens = _dateValue.split("-");
		if((date_tokens)&&(date_tokens.length == 3)) {
			date_tokens[2] = "" + (parseInt(date_tokens[2], 10) + 1);
			dateValue = new Date(date_tokens.join("-")).toLocaleDateString("en-us",{ year: 'numeric', month: 'long', day: 'numeric' });}
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
	};

	jQuery( 'input.job-manager-datepicker, input#_job_expires' ).each( function() {
		initializeDatepicker( this );
	} );


	jQuery( document ).on( 'wpJobManagerFieldAdded', function ( e ) {
		initializeDatepicker( e.target );
	});

	// Fix for the hidden and displayed datepicker fields not holding the current values for _job_expires.
	jQuery("[name='_job_expires']").val(jQuery("input[name='_job_expires-datepicker']")[0].getAttribute("value"));
	jQuery("input[name='_job_expires-datepicker']").val(date_format_function(jQuery("input[name='_job_expires-datepicker']")[0].getAttribute("value")));
});
