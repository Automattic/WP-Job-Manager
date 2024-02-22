/* global job_manager_stats */

import domReady from '@wordpress/dom-ready';
import { createHooks } from '@wordpress/hooks';
import {
	setUniques,
	checkUniqueRecordedToday,
	checkUnique,
} from './stats/unique';
import { initListingImpression } from "./stats/impressions";

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
		const statsToRecord    = [];
		const stats            = statsByTrigger[triggerName] || [];
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
			WPJMStats.types[type] && WPJMStats.types[type]( statsByType[type] );
		} );
	},

	hooks: createHooks(),
	types: {
		pageLoad: function ( stats ) {
			// This does not need to do anything special.
		},
		domEvent: function ( stats ) {
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
		initListingImpression

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
