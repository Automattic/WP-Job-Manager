jQuery( document ).ready( function ( $ ) {

	var xhr = [];

	//Update Results Listener
	$( '.job_listings' ).on( 'update_results', function ( event, page, append, loading_previous ) {
		console.log('update_results starting');
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


		// If it can't find a div with class job_listings in the element which triggered an update_results return without result
		if ( index < 0 ) {
			return;
		}

		// If there is already an ajax call in the xhr array then abort it 
		if ( xhr[index] ) {
			xhr[index].abort();
		}

		// Manage the local HTML5 history
		job_manager_store_state( target.closest( 'div.job_listings' ), page, false);

		// If append parameter is passed as false then ... remove previous results before appending new results.
		if ( ! append ) {
			$( results ).addClass( 'loading' );
			$( 'li.job_listing, li.no_job_listings_found', results ).css( 'visibility', 'hidden' );

			// Not appending. If page > 1, we should show a load previous button so the user can get to earlier-page listings if needed
			if ( page > 1 && true != target.data( 'show_pagination' ) ) {
				$( results ).before( '<a class="load_more_jobs load_previous" href="#"><strong>' + job_manager_ajax_filters.i18n_load_prev_listings + '</strong></a>' );
			} else {
				target.find( '.load_previous' ).remove();
			}

			target.find( '.load_more_jobs' ).data( 'page', page );
		}

		if ( true == target.data( 'show_filters' ) ) {

			var filter_job_type = [];

			$( ':input[name="filter_job_type[]"]:checked, :input[name="filter_job_type[]"][type="hidden"], :input[name="filter_job_type"]', form ).each( function () {
				filter_job_type.push( $( this ).val() );
			} );

			var categories = form.find( ':input[name^="search_categories"]' ).map( function () {
			return $( this ).val();
			} ).get();
			var keywords   = '';
			var location   = '';
			var $keywords  = form.find( ':input[name="search_keywords"]' );
			var $location  = form.find( ':input[name="search_location"]' );

			// Workaround placeholder scripts
			if ( $keywords.val() !== $keywords.attr( 'placeholder' ) ) {
				keywords = $keywords.val();
			}

			if ( $location.val() !== $location.attr( 'placeholder' ) ) {
				location = $location.val();
			}

			data = {
				lang: job_manager_ajax_filters.lang,
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
				lang: job_manager_ajax_filters.lang,
				search_categories: categories,
				search_keywords: keywords,
				search_location: location,
				per_page: per_page,
				orderby: orderby,
				order: order,
				page: page,
				featured: featured,
				filled: filled,
				show_pagination: target.data( 'show_pagination' )
			};

		}

		xhr[index] = $.ajax( {
			type: 'POST',
			url: job_manager_ajax_filters.ajax_url.toString().replace( "%%endpoint%%", "get_listings" ),
			data: data,
			success: function ( result ) {
				if ( result ) {
					try {
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
							if ( append && loading_previous ) {
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
							if ( ! result.found_jobs || result.max_num_pages <= page ) {
								$( '.load_more_jobs:not(.load_previous)', target ).hide();
							} else if ( ! loading_previous ) {
								$( '.load_more_jobs', target ).show();
							}
							$( '.load_more_jobs', target ).removeClass( 'loading' );
							$( 'li.job_listing', results ).css( 'visibility', 'visible' );
						}

						$( results ).removeClass( 'loading' );

						target.triggerHandler( 'updated_results', result );

					} catch ( err ) {
						if ( window.console ) {
							console.log( err );
						}
					}
				}
			},
			error: function ( jqXHR, textStatus, error ) {
				if ( window.console && 'abort' !== textStatus ) {
					console.log( textStatus + ': ' + error );
				}
			},
			statusCode: {
				404: function() {
					if ( window.console ) {
						console.log( "Error 404: Ajax Endpoint cannot be reached. Go to Settings > Permalinks and save to resolve." );
					}
				}
			}
		} );
		console.log(xhr);
		console.log('update_results ending');
	} );

	//end of the initial update_results listener

	// Changed this to just listen for enter button instead of listening for change event which was calling the loop twice.
	$( '#search_keywords, #search_location, .job_types :input, #search_categories, .job-manager-filter' ).on( "keyup", function(e) {
		if ( e.which === 13 ) {
			var target = $( this ).closest( 'div.job_listings' );
			target.triggerHandler( 'update_results', [ 1, false ] );
		}
	} );

	$( '.job_filters' ).on( 'click', '.reset', function () {
		console.log('job_filters click');
		var target = $( this ).closest( 'div.job_listings' );
		var form = $( this ).closest( 'form' );

		form.find( ':input[name="search_keywords"], :input[name="search_location"], .job-manager-filter' ).not(':input[type="hidden"]').val( '' ).trigger( 'chosen:updated' );
		form.find( ':input[name^="search_categories"]' ).not(':input[type="hidden"]').val( 0 ).trigger( 'chosen:updated' );
		$( ':input[name="filter_job_type[]"]', form ).not(':input[type="hidden"]').attr( 'checked', 'checked' );

		target.triggerHandler( 'reset' );
		target.triggerHandler( 'update_results', [ 1, false ] );
		return false;
	} );

	$( document.body ).on( 'click', '.load_more_jobs', function() {
		var target           = $( this ).closest( 'div.job_listings' );
		var page             = parseInt( $( this ).data( 'page' ) || 1 );
		var loading_previous = false;

		$(this).addClass( 'loading' );

		if ( $(this).is('.load_previous') ) {
			page             = page - 1;
			loading_previous = true;
			if ( page === 1 ) {
				$(this).remove();
			} else {
				$( this ).data( 'page', page );
			}
		} else {
			page = page + 1;
			$( this ).data( 'page', page );
		}

		target.triggerHandler( 'update_results', [ page, true, loading_previous ] );
		return false;
	} );

	$( 'div.job_listings' ).on( 'click', '.job-manager-pagination a', function() {
		var target = $( this ).closest( 'div.job_listings' );
		var page   = $( this ).data( 'page' );
		target.triggerHandler( 'update_results', [ page, false ] );

		$( "body, html" ).animate({
            scrollTop: target.offset().top
        }, 600 );

		return false;
	} );

	// if there is a search button listen for a click
	$( 'div.job_listings' ).on( 'click', '[type="submit"]', function() {
		console.log('submit button clicked');
		var target = $( this ).closest( 'div.job_listings' );
		target.triggerHandler( 'update_results', [ 1, false ] );
		return false;
	} );

	if ( $.isFunction( $.fn.chosen ) ) {
		if ( job_manager_ajax_filters.is_rtl == 1 ) {
			$( 'select[name^="search_categories"]' ).addClass( 'chosen-rtl' );
		}
		$( 'select[name^="search_categories"]' ).chosen({ search_contains: true });
	}

	if ( window.history && window.history.pushState ) {
		$supports_html5_history = true;
	} else {
		$supports_html5_history = false;
	}

	function job_manager_store_state( target, page ) {
		// changed this to ? because this is the standard query string identifier in php
		// and changed to window.location for better cross browser support
		// and moved into the function so it rechecks everytime the function is called
		var location = window.location.href.split('?')[0];
		var query = window.location.href.split('?')[1];
		query = query.split('&');

		if ( $supports_html5_history ) {
			var form  = target.find( '.job_filters' );
			var data  = $( form ).serialize();
			var index = $( 'div.job_listings' ).index( target );
			var keyword = form.find('#search_keywords').val() ? 'search_keywords='+encodeURIComponent(form.find('#search_keywords').val()): '';
			var geo = form.find('#search_location').val() ? 'search_location='+encodeURIComponent(form.find('#search_location').val()): '';
			var current_page = 'current_page='+page;
			var newURL = location+((keyword||geo||current_page)?'?':'')+keyword+((keyword&&geo)?'&':'')+geo+(((keyword||geo)&&current_page)?'&':'')+current_page;
			// Get the query values from the query portion of the url
			var queryValues = function () { 
				var values = [];
				for (var i = 0; i < query.length; i++) {
					value = decodeURIComponent(query[i].split('=')[1]);
					pl = /\+/g;  // Regex for replacing addition symbol with a space
					value = value.replace(pl, " ");
					values.push(value);
				}
				return values; 
			}();
			// Check if any of the query values are empty and if so replaceState e.g. empty form used in GET request
			var empty = false;
			emptyCheck: 
				for (var i = 0; i < queryValues.length; i++) {
					if (queryValues[i] === '') {
						empty = true;
						break emptyCheck;
					}
				}
			if ( empty ) {
				window.history.replaceState( { id: 'job_manager_state', page: page, data: data, index: index }, '', newURL );
				console.log('empty');
			}
			// otherwise check if same as last search and if not then store new state
			else if ( document.location.href !== newURL ) {
				window.history.pushState( { id: 'job_manager_state', page: page, data: data, index: index }, '', newURL );
				console.log('different')
			} else {
				console.log('The current search was the same as the last')
				return;
			}
			//*/
		}
	}

	function populate_forms () {
		$( '.job_filters' ).each( function() {
			var target      = $( this ).closest( 'div.job_listings' );
			var form        = target.find( '.job_filters' );
			var inital_page = 1;
			var index       = $( 'div.job_listings' ).index( target );

			target.triggerHandler( 'update_results', [ inital_page, false ] );

	   		if ( window.history.state && window.location.hash ) {
	   			var state = window.history.state;
	   			if ( state.id && 'job_manager_state' === state.id && index == state.index ) {
					inital_page = state.page;
					form.deserialize( state.data );
					form.find( ':input[name^="search_categories"]' ).not(':input[type="hidden"]').trigger( 'chosen:updated' );
				}
	   		}

			
	   	});
	}

	//On back button trigger update with artificial history
	if ( $supports_html5_history) {
		$(window).on( "popstate", function( event ) {
			window.history.back();
			console.log('popstate');
			populate_forms();
		});
	}
	//*/


	// Inital job and form population
	$(window).on( "load", function( event ) {
		console.log('window load');
		populate_forms();
	});
} );
