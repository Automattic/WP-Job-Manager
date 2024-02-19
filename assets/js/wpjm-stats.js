/* global job_manager_stats */

import domReady from '@wordpress/dom-ready';

( function () {
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

	domReady( function () {
		const statsToRecord = [];
		const jobStatsSettings = window.job_manager_stats;
		const ajaxUrl          = jobStatsSettings.ajax_url;
		const ajaxNonce      = jobStatsSettings.ajax_nonce;
		const uniquesToSet = [];
		const setUniques   = function() {
			uniquesToSet.forEach( function( uniqueKey ) {
				updateDailyUnique( uniqueKey );
			} );
		};

		jobStatsSettings.stats_to_log?.forEach( function ( statToRecord ) {
			const statToRecordKey = statToRecord.name;
			const uniqueKey       = statToRecord.unique_key || '';
			if ( uniqueKey.length === 0 ) {
				statsToRecord.push( statToRecordKey );
			} else {
				if ( false === getDailyUnique( uniqueKey ) ) {
					uniquesToSet.push( uniqueKey );
					statsToRecord.push( statToRecordKey );
				}
			}
		} );

		const postData = new URLSearchParams( {
			_ajax_nonce: ajaxNonce,
			post_id: jobStatsSettings.post_id || 0,
			action: 'job_manager_log_stat',
			stats: statsToRecord.join( ',' ),
		} );

		fetch( ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: postData,
		} ).finally( function () {
			setUniques();
		} );
	} );
} )();
