jQuery(document).ready(function($) {
	// Slide toggle
	$( '.application_details' ).hide();
	$( '.application_button' ).click(function() {
		$( '.application_details' ).slideToggle();
	});
});