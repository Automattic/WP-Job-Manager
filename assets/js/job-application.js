jQuery(document).ready(function($) {
	// Slide toggle
	jQuery( '.application_details' ).hide();
	jQuery( '.application_button' ).click(function() {
		jQuery( '.application_details' ).slideToggle();
	});
});