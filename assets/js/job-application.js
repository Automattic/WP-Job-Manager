jQuery(document).ready(function($) {
	// Slide toggle
	if ( ! $( 'body' ).hasClass( 'job-application-details-keep-open' ) ) {
		$( '.application_details' ).hide();
	}

	$( 'body' ).on( 'click', '.job_application .application_button', function() {
		var $details = $(this).siblings('.application_details').first();
		var $button = $(this);
		$details.slideToggle( 400, function() {
			if ( ! $(this).is(':visible') ) {
				// Only care if we toggled to be visible
				return;
			}

			// If max(33% height, 200px) of the application details aren't shown, scroll.
			var minimum_details_threshold = Math.max( Math.min( $details.outerHeight(), 200 ), $details.outerHeight() * .33 );
			var details_visible_threshold = $details.offset().top + minimum_details_threshold;
			var nice_buffer = 5;
			var top_viewport_buffer = nice_buffer;
			// We can't account for all theme headers with a fixed position on the top, but we can at least account for #wpadminbar and a fixed <header>
			if ( $( '#wpadminbar' ).length > 0 && 'fixed' === $( '#wpadminbar' ).css( 'position' ) ) {
				top_viewport_buffer += $( '#wpadminbar' ).outerHeight();
			}
			if ( $( 'header' ).length > 0 && 'fixed' === $( 'header' ).css( 'position' ) ) {
				top_viewport_buffer += $( 'header' ).outerHeight();
			}
			var bottom_of_screen = $(window).scrollTop() + window.innerHeight;
			var amount_hidden = $details.offset().top + $details.outerHeight() - bottom_of_screen;
			var window_height = window.innerHeight - top_viewport_buffer;

			if ( amount_hidden > 0 && $details.outerHeight() < ( window_height * .9 ) ) {
				// Application contents are shorter than the 90% of viewport, just scroll to show the bottom of details (with `nice_buffer` buffer)
				$('html, body').animate( { scrollTop: $(window).scrollTop() + amount_hidden + nice_buffer }, 400 );
			} else if( bottom_of_screen < details_visible_threshold ){
				// The application box is larger than the viewport AND our `minimum_details_threshold` is not visible.
				// Scroll to show top of application button, showing top of details
				$('html, body').animate( { scrollTop: $button.offset().top - top_viewport_buffer }, 600 );
			}
		});
	});
});
