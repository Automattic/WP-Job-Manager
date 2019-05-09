/* global job_manager_ajax_filters */
jQuery( document ).ready( function ( $ ) {
	var supports_html5_sessionStorage = false;
	if ( window.localStorage && typeof window.localStorage.setItem === 'function' ) {
		supports_html5_sessionStorage = true;
	}

	var session_storage_prefix = 'job_listing_';

	function job_manager_store_state( $target, state ) {
		if ( ! supports_html5_sessionStorage ) {
			return false;
		}

		if ( typeof state === 'undefined' ) {
			state = {};
		}

		var $form = $target.find( '.job_filters' );
		var index = $( 'div.job_listings' ).index( $target );

		state.form  = $form.serialize();

		var session_storage_key = session_storage_prefix + index;

		try {
			window.sessionStorage.setItem( session_storage_key, JSON.stringify( state ) );
		} catch(e) {}

		return false;
	}

	function job_manager_get_state( $target ) {
		if ( ! supports_html5_sessionStorage ) {
			return false;
		}
		var index               = $( 'div.job_listings' ).index( $target );
		var session_storage_key = session_storage_prefix + index;

		try {
			var state = window.sessionStorage.getItem( session_storage_key );
			if ( state ) {
				return JSON.parse( state );
			}
		} catch(e) {}

		return false;
	}

	function job_manager_persist_results( $target ) {
		if ( ! supports_html5_sessionStorage || ! $target ) {
			return false;
		}

		var state = job_manager_get_state( $target );
		if ( ! state ) {
			return false;
		}

		state.persist_results = true;

		return job_manager_store_state( $target, state );
	}

	function job_manager_save_results( $target, results ) {
		if ( ! supports_html5_sessionStorage ) {
			return false;
		}
		var state = job_manager_get_state( $target );
		if ( ! state ) {
			state = {};
		}

		var $results = $target.find( '.job_listings' );

		// Cache all loaded $results.
		results.html = $results.html();

		state.results = results;

		return job_manager_store_state( $target, state );
	}

	function job_manager_clear_state( $target ) {
		if ( ! supports_html5_sessionStorage ) {
			return false;
		}

		var index               = $( 'div.job_listings' ).index( $target );
		var session_storage_key = session_storage_prefix + index;

		try {
			window.sessionStorage.removeItem( session_storage_key );
		} catch(e) {
			return false;
		}

		return true;
	}

	function job_manager_clear_results( $target ) {
		if ( ! supports_html5_sessionStorage ) {
			return false;
		}
		var state = job_manager_get_state( $target );
		if ( ! state ) {
			state = {};
		}

		state.results = null;

		return job_manager_store_state( $target, state );
	}

	function job_manager_handle_result( $target, result, append ) {
		var $results = $target.find( '.job_listings' );
		var $showing = $target.find( '.showing_jobs' );

		if ( typeof append !== 'boolean' ) {
			append = false;
		}

		if ( typeof result.showing === 'string' && result.showing ) {
			var $showing_el = jQuery('<span>').html(result.showing);
			$showing.show().html( '').html( result.showing_links ).prepend( $showing_el );
		} else {
			$showing.hide();
		}

		if ( result.showing_all ) {
			$showing.addClass( 'wp-job-manager-showing-all' );
		} else {
			$showing.removeClass( 'wp-job-manager-showing-all' );
		}

		if ( result.html ) {
			if ( append ) {
				$results.append( result.html );
			} else {
				$results.html( result.html );
			}
		}

		if ( true === $target.data( 'show_pagination' ) ) {
			$target.find('.job-manager-pagination').remove();

			if ( result.pagination ) {
				$target.append( result.pagination );
			}
		} else {
			if ( ! result.found_jobs || result.max_num_pages <= result.data.page ) {
				$( '.load_more_jobs:not(.load_previous)', $target ).hide();
			} else {
				$( '.load_more_jobs', $target ).show();
			}
			$( '.load_more_jobs', $target ).removeClass( 'loading' ).data( 'page', result.data.page );
			$( 'li.job_listing', $results ).css( 'visibility', 'visible' );
		}

		return true;
	}

	var xhr = [];
	$( 'div.job_listings' )
		.on( 'click', 'li.job_listing a', function() {
			var $target = $(this).closest( 'div.job_listings' );
			job_manager_persist_results( $target );
		} )
		.on( 'click', '.job-manager-pagination a', function() {
			var $target = $( this ).closest( 'div.job_listings' );
			var page    = $( this ).data( 'page' );

			$target.triggerHandler( 'update_results', [ page, false ] );

			$( 'body, html' ).animate({
				scrollTop: $target.offset().top
			}, 600 );

			return false;
		} )
		.on( 'update_results', function ( event, page, append ) {
		var data        = '';
		var $target     = $( this );
		var $form       = $target.find( '.job_filters' );
		var $results    = $target.find( '.job_listings' );
		var per_page    = $target.data( 'per_page' );
		var orderby     = $target.data( 'orderby' );
		var order       = $target.data( 'order' );
		var featured    = $target.data( 'featured' );
		var filled      = $target.data( 'filled' );
		var job_types   = $target.data( 'job_types' );
		var post_status = $target.data( 'post_status' );
		var index       = $( 'div.job_listings' ).index(this);
		var categories, keywords, location;

		if ( index < 0 ) {
			return;
		}

		job_manager_clear_state( $target );

		if ( xhr[index] ) {
			xhr[index].abort();
		}

		$results.addClass( 'loading' );
		$target.find( '.load_more_jobs' ).data( 'page', page );

		if ( true === $target.data( 'show_filters' ) ) {
			var filter_job_type = [];

			$( ':input[name="filter_job_type[]"]:checked, :input[name="filter_job_type[]"][type="hidden"], :input[name="filter_job_type"]', $form ).each( function () {
				filter_job_type.push( $( this ).val() );
			} );

			categories = $form.find( ':input[name^="search_categories"]' ).map( function () {
				return $( this ).val();
			} ).get();

			keywords       = '';
			location       = '';
			var $keywords  = $form.find( ':input[name="search_keywords"]' );
			var $location  = $form.find( ':input[name="search_location"]' );

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
				filter_post_status: post_status,
				per_page: per_page,
				orderby: orderby,
				order: order,
				page: page,
				featured: featured,
				filled: filled,
				show_pagination: $target.data( 'show_pagination' ),
				$form_data: $form.serialize()
			};

		} else {

			categories = $target.data( 'categories' );
			keywords   = $target.data( 'keywords' );
			location   = $target.data( 'location' );

			if ( categories ) {
				if ( typeof categories !== 'string' ) {
					categories = String( categories );
				}
				categories = categories.split( ',' );
			}

			data = {
				lang: job_manager_ajax_filters.lang,
				search_categories: categories,
				search_keywords: keywords,
				search_location: location,
				filter_post_status: post_status,
				filter_job_type: job_types,
				per_page: per_page,
				orderby: orderby,
				order: order,
				page: page,
				featured: featured,
				filled: filled,
				show_pagination: $target.data( 'show_pagination' )
			};

		}

		xhr[index] = $.ajax( {
			type: 'POST',
			url: job_manager_ajax_filters.ajax_url.toString().replace( '%%endpoint%%', 'get_listings' ),
			data: data,
			success: function ( result ) {
				if ( result ) {
					try {
						result.data            = data;
						result.persist_results = false;

						job_manager_handle_result( $target, result, append );

						$results.removeClass( 'loading' );

						$target.triggerHandler( 'updated_results', result );
						job_manager_save_results( $target, result );

					} catch ( err ) {
						if ( window.console ) {
							window.console.log( err );
						}
					}
				}
			},
			error: function ( jqXHR, textStatus, error ) {
				if ( window.console && 'abort' !== textStatus ) {
					window.console.log( textStatus + ': ' + error );
				}
			},
			statusCode: {
				404: function() {
					if ( window.console ) {
						window.console.log( 'Error 404: Ajax Endpoint cannot be reached. Go to Settings > Permalinks and save to resolve.' );
					}
				}
			}
		} );
	} );

	$( '#search_keywords, #search_location, .job_types :input, #search_categories, .job-manager-filter' )
		.change( function() {
			var $target = $( this ).closest( 'div.job_listings' );
			$target.triggerHandler( 'update_results', [ 1, false ] );
			job_manager_store_state( $target );
		} )
		.on( 'keyup', function(e) {
			if ( e.which === 13 ) {
				$( this ).trigger( 'change' );
			}
		} );

	$( '.job_filters' ).on( 'click', '.reset', function () {
		var $target = $( this ).closest( 'div.job_listings' );
		var $form   = $( this ).closest( 'form' );

		$form.find( ':input[name="search_keywords"], :input[name="search_location"], .job-manager-filter' ).not(':input[type="hidden"]').val( '' ).trigger( 'change.select2' );
		$form.find( ':input[name^="search_categories"]' ).not(':input[type="hidden"]').val( '' ).trigger( 'change.select2' );
		$( ':input[name="filter_job_type[]"]', $form ).not(':input[type="hidden"]').attr( 'checked', 'checked' );

		$target.triggerHandler( 'reset' );
		$target.triggerHandler( 'update_results', [ 1, false ] );
		job_manager_store_state( $target );

		return false;
	} ).on( 'submit', function() {
		return false;
	} );

	$( document.body ).on( 'click', '.load_more_jobs', function() {
		var $target           = $( this ).closest( 'div.job_listings' );
		var page             = parseInt( ( $( this ).data( 'page' ) || 1 ), 10 );

		$(this).addClass( 'loading' );

		page = page + 1;
		$( this ).data( 'page', page );

		$target.triggerHandler( 'update_results', [ page, true ] );
		return false;
	} );

	if ( $.isFunction( $.fn.select2 ) && typeof job_manager_select2_args !== 'undefined' ) {
		var select2_args = job_manager_select2_args;
		select2_args['allowClear']              = true;
		select2_args['minimum$resultsForSearch'] = 10;

		$( 'select[name^="search_categories"]:visible' ).select2( select2_args );
	}

	// Initial job and $form population
	$(window).on( 'load', function() {
		$( '.job_filters' ).each( function() {
			var $target         = $( this ).closest( 'div.job_listings' );
			var $form           = $target.find( '.job_filters' );
			var $results_loaded = false;
			var state           = job_manager_get_state( $target );

			if ( state.results ) {
				$results_loaded = job_manager_handle_result( $target, state.results );
				job_manager_clear_results( $target );
			}

			$form.find( 'input[type=checkbox]' ).prop( 'checked', false );
			$form.deserialize( state.form );
			$form.find( ':input[name^="search_categories"]' ).not(':input[type="hidden"]').trigger( 'change.select2' );

			if ( ! $results_loaded ) {
				$target.triggerHandler( 'update_results', [ 1, false ] );
			}
		});
	} );

	$(window).on( 'unload', function() {
		$( 'div.job_listings' ).each( function() {
			var state = job_manager_get_state( $(this) );
			if ( state && ! state.persist_results ) {
				job_manager_clear_results( $(this) );
			}
		} );

		return true;
	} );
} );
