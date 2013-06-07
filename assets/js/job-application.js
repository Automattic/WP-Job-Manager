jQuery(document).ready(function($) {
	// Slide toggle
	jQuery( '.application_details' ).hide();
	jQuery( '.application_button' ).click(function() {
		jQuery( '.application_details' ).slideToggle();
	});

	// De-code emails
	jQuery( '.job_application_email' ).each(function() {
		var text = jQuery(this).html();
		var href = jQuery(this).attr( 'href' );

		text = text.replace( /(\[|\()at(\]|\))/i, '@' );
		text = text.replace( /(\[|\()dot(\]|\))/ig, '.' );
		text = text.replace( / /g, '' );

		href = href.replace( /(\[|\()at(\]|\))/i, '@' );
		href = href.replace( /(\[|\()dot(\]|\))/ig, '.' );
		href = href.replace( / /g, '' );

		jQuery(this).html( text );
		jQuery(this).attr( 'href', href );
	});
});