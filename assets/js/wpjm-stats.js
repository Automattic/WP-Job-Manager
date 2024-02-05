/* global job_manager_stats */

import domReady from '@wordpress/dom-ready';

( function () {
	function updateDailyUnique( key ) {
		var date = new Date();
		var expiresAtTimestamp = date.getTime() + 24 * 60 * 60 * 1000;
		window.localStorage[key] = expiresAtTimestamp;
	}

	function getDailyUnique( name ) {
		if ( window.localStorage[name] ) {
			var date = new Date();
			var now = date.getTime();
			var expiration = parseInt( window.localStorage[name], 10 );
			return expiration >= now;
		}
		return false;
	}

	domReady( function () {
		var statsToRecord = [];
		var jobStatsSettings = window.job_manager_stats;
		var ajaxUrl = jobStatsSettings.ajax_url;
		var ajaxNonce = jobStatsSettings.ajax_nonce;
		var uniquesToSet = [];
		var setUniques = function () {
			uniquesToSet.forEach( function ( uniqueKey ) {
				updateDailyUnique( uniqueKey );
			} );
		};

		jobStatsSettings.stats_to_log.forEach( function ( statToRecord ) {
			// do something.
			var statToRecordKey = statToRecord.name;
			var uniqueKey = statToRecord.unique_key || '';
			if ( uniqueKey.length === 0 ) {
				statsToRecord.push( statToRecordKey );
			} else {
				if ( false === getDailyUnique( uniqueKey ) ) {
					uniquesToSet.push( uniqueKey );
					statsToRecord.push( statToRecordKey );
				}
			}
		} );

		var postData = new URLSearchParams( {
			_ajax_nonce: ajaxNonce,
			post_id: jobStatsSettings.post_id || 0,
			action: 'job_manager_log_stat',
			stats: statsToRecord.join( ',' )
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
