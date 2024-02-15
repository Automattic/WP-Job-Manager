/* global job_manager_stats */

import domReady from '@wordpress/dom-ready';
import { createHooks } from '@wordpress/hooks';

( function () {
	window.wpjmStatHooks = window.wpjmStatHooks || createHooks();

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

	window.wpjmLogStats = window.wpjmLogStats || function ( statsToRecord, uniquesToSet ) {
		const jobStatsSettings = window.job_manager_stats;
		const ajaxUrl          = jobStatsSettings.ajax_url;
		const ajaxNonce        = jobStatsSettings.ajax_nonce;

		uniquesToSet = uniquesToSet || [];
		statsToRecord = statsToRecord || [];

		if ( statsToRecord.length < 1 ) {
			return Promise.resolve(); // Could also be an error.
		}

		const postData = new URLSearchParams( {
			_ajax_nonce: ajaxNonce,
			post_id: jobStatsSettings.post_id || 0,
			action: 'job_manager_log_stat',
			stats: statsToRecord.join( ',' ),
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
		const uniquesToSet     = [];
		const stats            = statsByTrigger[triggerName] || [];
		const events           = {};

		stats.forEach( function ( statToRecord ) {
			const statToRecordKey = statToRecord.name;
			const uniqueKey       = statToRecord.unique_key || '';
			const isUnique        = uniqueKey.length > 0;

			if ( ! isUnique ) {
				statsToRecord.push( statToRecordKey );
			} else {
				if ( false === getDailyUnique( uniqueKey ) ) {
					uniquesToSet.push( uniqueKey );
					statsToRecord.push( statToRecordKey );
				}
			}

			if ( statToRecord.element && statToRecord.event ) {
				const elemToAttach = document.querySelector( statToRecord.element );
				if ( elemToAttach && ! events[statToRecord.element] ) {
					elemToAttach.addEventListener( statToRecord.event, function ( e ) {
						if ( isUnique && false !== getDailyUnique( uniqueKey ) ) {
							return;
						}

						window.wpjmStatHooks.doAction( triggerName );
					} );
					events[statToRecord.element] = true;
				}
			}
		} );

		// Hook action to call logStats.
		window.wpjmStatHooks.addAction( triggerName, 'wpjm-stats', function () {
			window.wpjmLogStats( statsToRecord, uniquesToSet );
		}, 10 );
	}


	domReady( function () {
		const jobStatsSettings = window.job_manager_stats;

		const statsByTrigger = jobStatsSettings.stats_to_log?.reduce( function ( accum, statToRecord ) {
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

		// Kick things off.
		console.log('kick thing off');
		window.wpjmStatHooks.doAction( 'page-load' );
	} );
} )();
