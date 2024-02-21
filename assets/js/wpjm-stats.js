/* global job_manager_stats */

import domReady from '@wordpress/dom-ready';
import { createHooks } from '@wordpress/hooks';
import {
	debounce,
	filterZeroes,
	findIdInClassNames,
} from './stats/utils';
import {
	setUniques,
	checkUniqueRecordedToday,
	checkUnique,
} from './stats/unique';
import {
	waitForSelector,
} from './stats/observers';

function createStatsQueue() {
	const alreadySent = {};
	let queue = [];

	const logThem = debounce( function ( listingIds ) {
		const stats = listingIds.map( function ( id ) {
			alreadySent[id] = true;
			return { name: 'job_listing_impressions', post_id: id };
		});
		// queue = [];
		return wpjmLogStats( stats ).finally( function () {
			queue = [];
		});
	}, 1000 );

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
			WPJMStats.hookStatsForTrigger( statsByTrigger, triggerName );
		} );

		WPJMStats.initCallbacks.forEach( function ( initCallback ) {
			initCallback.call( null );
		} );

		WPJMStats.hooks.doAction( 'page-load' );
	},

	hookStatsForTrigger: function ( statsByTrigger, triggerName ) {
		console.log( 'hookStatsForTrigger' );
		const statsToRecord    = [];
		const stats            = statsByTrigger[triggerName] || [];
		const events           = {};
		const statsByType      = {};

		stats.forEach( function ( statToRecord ) {
			if ( ! statsByType[statToRecord.type] ) {
				statsByType[statToRecord.type] = [];
			}

			statsByType[statToRecord.type].push( statToRecord );
			statsToRecord.push( statToRecord );
		} );

		// Hook action to call logStats.
		WPJMStats.hooks.addAction( triggerName, 'wpjm-stats', function () {
			window.wpjmLogStats( statsToRecord );
		}, 10 );

		Object.keys( statsByType ).forEach( function ( type ) {
			console.log( 'stats by type', type );
			WPJMStats.types[type] && WPJMStats.types[type]( statsByType[type] );
		} );
	},

	hooks: createHooks(),
	types: {
		pageLoad: function ( stats ) {
			console.log( 'pageLoad init script.' );
			// This does not need to do anything special.
		},
		domEvent: function ( stats ) {
			console.log( 'pageLoad init script.' );
			const events = {};
			stats.forEach( function ( statToRecord ) {
				const triggerName = statToRecord.trigger;
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
				}
			} );
		},
		// New style of declaration, a stat that relies on calling a custom js func.
		initListingImpression: function ( stats ) {
			console.log( 'initListingImpression init script.' );
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

	},
	initCallbacks: []
};

window.WPJMStats = window.WPJMStats || WPJMStats;

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

domReady( function () {
	const jobStatsSettings = window.job_manager_stats;
	WPJMStats.init( jobStatsSettings.stats_to_log );
} );
