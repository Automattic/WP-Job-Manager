/* global job_manager_stats */

import domReady from '@wordpress/dom-ready';
import { createHooks } from '@wordpress/hooks';

( function () {
	function debounce(func, delay) {
		let timeoutId;

		return function (...args) {
			clearTimeout(timeoutId);

			timeoutId = setTimeout(() => {
				func(...args);
			}, delay);
		};
	}

	function filterZeroes( list ) {
		return list.filter( function (i) { return i > 0; } );
	}
	function findIdInClassNames( node ) {
		const classes = node.classList;
		for ( let i = 0; i < classes.length; i++ ) {
			const className = classes[i];
			if ( 0 === className.indexOf( 'post-' ) ) {
				const maybeId = parseInt( className.substring(5), 10 );
				if ( ! isNaN( maybeId ) ) {
					return maybeId;
				}
			}
		}
		return 0;
	}

	function createStatsQueue() {
		const alreadySent = {};
		let queue = [];

		const logThem = debounce( function ( listingIds ) {
			const stats = listingIds.map( function ( id ) {
				alreadySent[id] = true;
				return { name: 'job_listing_impressions', post_id: id };
			});
			console.log( 'sending stats:', stats );
			// queue = [];
			return wpjmLogStats( stats ).finally( function () {
				queue = [];
			});
		}, 500 );

		return {
			queueListingImpressionStats: function ( listingIds ) {
				listingIds.forEach( function (listingId ) {
					if ( ! alreadySent[listingId] ) {
						queue.push(listingId);
					}
				} );
				logThem( queue );

			}
		};
	}

	function observeForVisibility( jobListingContainer, jobListingElement, visibleCallback, alreadyViewedListings ) {
		const options = {
			root: null,
			rootMargin: "0px",
			threshold: 1.0,
		};


		const observer = new IntersectionObserver(function ( entries ) {
			entries.forEach(function ( entry ) {
				if ( entry.isIntersecting && entry.intersectionRatio > 0.99 ) {
					const node = entry.target;
					if ( 1 === node.nodeType && node.classList.contains( 'job_listing' ) ) {
						const nodeId = findIdInClassNames( node );
						if ( nodeId > 0 && ! alreadyViewedListings[nodeId] ) {
							alreadyViewedListings[nodeId] = true;
							visibleCallback( node );
						}
					}
					node.classList.add( 'viewed' );
					observer.unobserve( node );
				}
			} );
		}, options );

		observer.observe( jobListingElement );
	}

	function waitForNextVisibleListing( listingVisibleCallback ) {
		const jobListingsContainer = document.querySelector( 'ul.job_listings' );
		const config = { childList: true };
		const alreadyViewed = {};

		const observer = new MutationObserver(function ( mutations ) {
			mutations.forEach(function ( mutation ) {
				mutation.addedNodes.forEach( function ( node ) {
					if ( 1 === node.nodeType && node.classList.contains( 'job_listing' ) && ! node.classList.contains( 'viewed' ) ) {
						observeForVisibility( jobListingsContainer, node, listingVisibleCallback, alreadyViewed );
					}
				} );
			} );
		} );

		observer.observe( jobListingsContainer, config );
	}

	function waitForSelector( selector ) {
		return new Promise(function ( resolve ) {
			if (document.querySelector(selector)) {
				return resolve(document.querySelector(selector));
			}

			const observer = new MutationObserver(function () {
				if (document.querySelector(selector)) {
					observer.disconnect();
					resolve(document.querySelector(selector));
				}
			});

			observer.observe(document.documentElement, {
				childList: true,
				subtree: true
			});
		});
	}

	function existsAndCallable( globalAsString ) {
		let root = window;
		const parts = globalAsString.split('.');
		for ( let i = 0; i < parts.length; i++ ) {
			const key = parts[i];
			if ( ! root[key] ) {
				return null;
			}
			root = root[key];
		}

		return typeof root === 'function' ? root : null;
	}

	const WPJMStats =  {
		init: function ( statsToRecord ) {
			const statsByTrigger = statsToRecord?.reduce( function ( accum, statToRecord ) {
				const triggerName = statToRecord.trigger || '';

				if ( triggerName.length < 1 ) {
					return accum;
				}

				if ( ! accum[triggerName] ) {
					accum[triggerName] = [];
				}

				accum[triggerName].push( statToRecord );

				return accum;
			}, {} );

			Object.keys( statsByTrigger ).forEach( function ( triggerName) {
				hookStatsForTrigger( statsByTrigger, triggerName );
			} );

			WPJMStats.initCallbacks.forEach( function ( initCallback ) {
				initCallback.call( null );
			} );

			WPJMStats.hooks.doAction( 'page-load' );
		},
		hooks: createHooks(),
		// New style of declaration, a stat that relies on calling a custom js func.
		initListingImpression: function () {
			const debouncedSender = createStatsQueue();
			waitForSelector( 'li.job_listing' ).then( function () {
				const allVisibleListings = document.querySelectorAll('li.job_listing');
				const initialListingIds = filterZeroes( [...allVisibleListings].map( function ( elem ) {
					return findIdInClassNames( elem );
				} ) );
				debouncedSender.queueListingImpressionStats( initialListingIds );

				waitForNextVisibleListing( function ( elem ) {
					const maybeId = findIdInClassNames( elem );
					maybeId > 0 && debouncedSender.queueListingImpressionStats( [ maybeId ] );
				} );

			} );
		},
		initCallbacks: []
	};

	window.WPJMStats = window.WPJMStats || WPJMStats;

	function updateDailyUnique( key ) {
		const date = new Date();
		const expiresAtTimestamp = date.getTime() + 24 * 60 * 60 * 1000;
		window.localStorage[key] = expiresAtTimestamp;
	}

	function getDailyUnique( name ) {
		if ( window.localStorage[name] ) {
			const date = new Date();
			const now  = date.getTime();
			const expiration = parseInt( window.localStorage[ name ], 10 );
			return expiration >= now;
		}
		return false;
	}

	function setUniques( uniquesToSet ) {
		uniquesToSet.forEach( function( uniqueKey ) {
			updateDailyUnique( uniqueKey );
		} );
	}

	function checkUniqueRecordedToday( statToRecord ) {
		const uniqueKey = statToRecord.unique_key || '';
		return checkUnique( statToRecord ) && true === getDailyUnique( uniqueKey );
	}

	function checkUnique( statToRecord ) {
		const uniqueKey = statToRecord.unique_key || '';
		const isUnique  = uniqueKey.length > 0;

		return isUnique;
	}

	window.wpjmLogStats = window.wpjmLogStats || function ( stats ) {
		const jobStatsSettings = window.job_manager_stats;
		const ajaxUrl          = jobStatsSettings.ajax_url;
		const ajaxNonce        = jobStatsSettings.ajax_nonce;

		const uniquesToSet     = [];
		const statsToRecord    = [];

		if ( stats.length < 1 ) {
			return Promise.resolve(); // Could also be an error.
		}

		stats.forEach( function ( statToRecord ) {
			if ( ! checkUniqueRecordedToday( statToRecord ) ) {
				uniquesToSet.push( statToRecord.unique_key );
				statsToRecord.push( statToRecord );
			} else if ( ! checkUnique( statToRecord ) ) {
				statsToRecord.push( statToRecord );
			}
		} );

		const postData = new URLSearchParams( {
			_ajax_nonce: ajaxNonce,
			post_id: jobStatsSettings.post_id || 0,
			action: 'job_manager_log_stat',
			stats: JSON.stringify( statsToRecord.map(function ( stat ) {
				const { name = '', group = '', post_id = 0 } = stat;
				return { name, group, post_id }; } ) )
		} );

		return fetch( ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: postData,
		} ).finally( function () {
			setUniques( uniquesToSet );
		} );
	};

	function hookStatsForTrigger( statsByTrigger, triggerName ) {
		const statsToRecord    = [];
		const stats            = statsByTrigger[triggerName] || [];
		const events           = {};

		stats.forEach( function ( statToRecord ) {
			statsToRecord.push( statToRecord );

			if ( statToRecord.element && statToRecord.event ) {
				const elemToAttach = document.querySelector( statToRecord.element );
				if ( elemToAttach && ! events[statToRecord.element] ) {
					elemToAttach.addEventListener( statToRecord.event, function ( e ) {
						if ( checkUniqueRecordedToday( statToRecord ) ) {
							return;
						}

						WPJMStats.hooks.doAction( triggerName );
					} );
					events[statToRecord.element] = true;
				}
			} else if ( null !== statToRecord.js_callback ) {
				const callable = existsAndCallable( statToRecord.js_callback );
				if ( callable ) {
					WPJMStats.initCallbacks.push( function () {
						callable.call( null, statToRecord );
					} );
				}
			}
		} );

		// Hook action to call logStats.
		WPJMStats.hooks.addAction( triggerName, 'wpjm-stats', function () {
			window.wpjmLogStats( statsToRecord );
		}, 10 );
	}

	domReady( function () {
		const jobStatsSettings = window.job_manager_stats;

		WPJMStats.init( jobStatsSettings.stats_to_log );

	} );
} )();
