jQuery( document ).ready( function ( $ ) {

	if ( window.history && window.history.pushState ) {
		$supports_html5_history = true;
	} else {
		$supports_html5_history = false;
	}

	var xhr = [];

	$( '.job_listings' ).on( 'update_results', function ( event, page, append ) {
		var data         = '';
		var target       = $( this );
		var form         = target.find( '.job_filters' );
		var showing      = target.find( '.showing_jobs' );
		var results      = target.find( '.job_listings' );
		var per_page     = target.data( 'per_page' );
		var orderby      = target.data( 'orderby' );
		var order        = target.data( 'order' );
		var featured     = target.data( 'featured' );
		var filled       = target.data( 'filled' );
		var index        = $( 'div.job_listings' ).index(this);
		var current_page = target.data( 'current_page' );

		if ( xhr[index] ) {
			xhr[index].abort();
		}

		if ( page > current_page ) {
			current_page = page;
			target.data( 'current_page', current_page );
		}

		if ( ! append ) {
			$( results ).addClass( 'loading' );
			$( 'li.job_listing, li.no_job_listings_found', results ).css( 'visibility', 'hidden' );

			// Not appending. If page > 1, we should show a load previous button so the user can get to earlier-page listings if needed
			if ( page > 1 && true != target.data( 'show_pagination' ) ) {
				$( results ).before( '<a class="load_more_jobs load_previous" href="#"><strong>' + job_manager_ajax_filters.i18n_load_prev_listings + '</strong></a>' );
				target.find( '.load_more_jobs' ).data( 'page', page );
			}
		}

		if ( true == target.data( 'show_filters' ) ) {

			var filter_job_type = [];

			$( ':input[name="filter_job_type[]"]:checked, :input[name="filter_job_type[]"][type="hidden"], :input[name="filter_job_type"]', form ).each( function () {
				filter_job_type.push( $( this ).val() );
			} );

			var categories = form.find( ':input[name^=search_categories], :input[name^=search_categories]' ).map( function () {
			return $( this ).val();
			} ).get();
			var keywords   = '';
			var location   = '';
			var $keywords  = form.find( ':input[name=search_keywords]' );
			var $location  = form.find( ':input[name=search_location]' );

			// Workaround placeholder scripts
			if ( $keywords.val() !== $keywords.attr( 'placeholder' ) ) {
				keywords = $keywords.val();
			}

			if ( $location.val() !== $location.attr( 'placeholder' ) ) {
				location = $location.val();
			}

			data = {
				action: 'job_manager_get_listings',
				search_keywords: keywords,
				search_location: location,
				search_categories: categories,
				filter_job_type: filter_job_type,
				per_page: per_page,
				orderby: orderby,
				order: order,
				page: page,
				featured: featured,
				filled: filled,
				show_pagination: target.data( 'show_pagination' ),
				form_data: form.serialize()
			};

		} else {

			var categories = target.data( 'categories' );
			var keywords   = target.data( 'keywords' );
			var location   = target.data( 'location' );

			if ( categories ) {
				categories = categories.split( ',' );
			}

			data = {
				action: 'job_manager_get_listings',
				search_categories: categories,
				search_keywords: keywords,
				search_location: location,
				per_page: per_page,
				orderby: orderby,
				order: order,
				page: page,
				featured: featured,
				filled: filled,
				show_pagination: target.data( 'show_pagination' ),
			};

		}

		xhr[index] = $.ajax( {
			type: 'POST',
			url: job_manager_ajax_filters.ajax_url,
			data: data,
			success: function ( response ) {
				if ( response ) {
					try {

						// Get the valid JSON only from the returned string
						if ( response.indexOf( "<!--WPJM-->" ) >= 0 ) {
							response = response.split( "<!--WPJM-->" )[ 1 ]; // Strip off before WPJM
						}

						if ( response.indexOf( "<!--WPJM_END-->" ) >= 0 ) {
							response = response.split( "<!--WPJM_END-->" )[ 0 ]; // Strip off anything after WPJM_END
						}

						var result = $.parseJSON( response );

						if ( result.showing ) {
							$( showing ).show().html( '<span>' + result.showing + '</span>' + result.showing_links );
						} else {
							$( showing ).hide();
						}

						if ( result.showing_all ) {
							$( showing ).addClass( 'wp-job-manager-showing-all' );
						} else {
							$( showing ).removeClass( 'wp-job-manager-showing-all' );
						}

						if ( result.html ) {
							if ( append === 'prepend' ) {
								$( results ).prepend( result.html );
							} else if ( append ) {
								$( results ).append( result.html );
							} else {
								$( results ).html( result.html );
							}
						}

						if ( true == target.data( 'show_pagination' ) ) {
							target.find('.job-manager-pagination').remove();

							if ( result.pagination ) {
								target.append( result.pagination );
							}
						} else {
							if ( ! result.found_jobs || result.max_num_pages == page ) {
								$( '.load_more_jobs:not(.load_previous)', target ).hide();
							} else if ( page > current_page ) {
								$( '.load_more_jobs', target ).show();
							}
							$( '.load_more_jobs', target ).removeClass( 'loading' );
							$( 'li.job_listing', results ).css( 'visibility', 'visible' );
						}

						$( results ).removeClass( 'loading' );

						target.triggerHandler( 'updated_results', result );

					} catch ( err ) {
						//console.log( err );
					}
				}
			}
		} );
	} );

	$( '#search_keywords, #search_location, .job_types :input, #search_categories' ).change( function() {
		var target   = $( this ).closest( 'div.job_listings' );
		target.triggerHandler( 'update_results', [ 1, false ] );
	} )

	.on( "keyup", function(e) {
	    if ( e.which === 13 ) {
	        $( this ).trigger( 'change' );
	    }
	} );

	$( '.job_filters' ).on( 'click', '.reset', function () {
		var target = $( this ).closest( 'div.job_listings' );
		var form = $( this ).closest( 'form' );

		form.find( ':input[name="search_keywords"]' ).not(':input[type="hidden"]').val( '' );
		form.find( ':input[name="search_location"]' ).not(':input[type="hidden"]').val( '' );
		form.find( ':input[name^="search_categories"]' ).not(':input[type="hidden"]').val( 0 ).trigger( 'chosen:updated' );
		$( ':input[name="filter_job_type[]"]', form ).not(':input[type="hidden"]').attr( 'checked', 'checked' );

		target.triggerHandler( 'reset' );
		target.triggerHandler( 'update_results', [ 1, false ] );

		return false;
	} );

	$( 'body' ).on( 'click', '.load_more_jobs', function() {
		var target = $( this ).closest( 'div.job_listings' );
		var page   = parseInt( $( this ).data( 'page' ) || 1 );
		var append = 'append';

		$(this).addClass( 'loading' );

		if ( $(this).is('.load_previous') ) {
			page   = page - 1;
			append = 'prepend';
			if ( page === 1 ) {
				$(this).remove();
			} else {
				$( this ).data( 'page', page );
			}
		} else {
			page = page + 1;
			$( this ).data( 'page', page );

			if ( $supports_html5_history ) {
				history.replaceState( { id: 'job_manager_page' }, '', $.param.fragment( document.URL, 'p=' + page ) );
			}
		}

		target.triggerHandler( 'update_results', [ page, append ] );
		return false;
	} );

	$( 'div.job_listings' ).on( 'click', '.job-manager-pagination a', function() {
		var target = $( this ).closest( 'div.job_listings' );
		var page   = $( this ).data( 'page' );

		if ( $supports_html5_history ) {
			history.replaceState( { id: 'job_manager_page' }, '', $.param.fragment( document.URL, 'p=' + page ) );
		}

		target.triggerHandler( 'update_results', [ page, false ] );

		return false;
	} );

	if ( $.isFunction( $.fn.chosen ) ) {
		if ( job_manager_ajax_filters.is_rtl == 1 ) {
			$( 'select[name^="search_categories"]' ).addClass( 'chosen-rtl' );
		}
		$( 'select[name^="search_categories"]' ).chosen({ search_contains: true });
	}

	// If we are have a fragment after page load, the back button may have been used.
	// Lets put the user on the page they were on, and show at most 20 listings before this page.
	// We do not want to load the full results up to that page because of performance.
	var inital_page = 1;

	if ( $supports_html5_history ) {
		var fragments = $.deparam.fragment();

		if ( fragments.p ) {
			inital_page = fragments.p;
		}
	}

	// Inital job population
	$( '.job_filters' ).each( function() {
		var target = $( this ).closest( 'div.job_listings' );
		target.triggerHandler( 'update_results', [ inital_page, false ] );
	});
} );