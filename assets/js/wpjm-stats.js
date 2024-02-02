/* global wpjm_stats */
( function () {
	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	function createCookie(name,value,days,path) {
		if (days) {
			var date = new Date();
			date.setTime(date.getTime()+(days*24*60*60*1000));
			var expires = "; expires="+date.toGMTString();
		}
		else var expires = "";
		document.cookie = name+"="+value+expires+"; path=" + path;
	}

	function readCookie(name) {
		var nameEQ = name + "=";
		var ca = document.cookie.split(';');
		for(var i=0;i < ca.length;i++) {
			var c = ca[i];
			while (c.charAt(0)==' ') c = c.substring(1,c.length);
			if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
		}
		return null;
	}

	function eraseCookie(name) {
		createCookie(name,"",-1);
	}

	ready( function ()  {
		var statsToRecord = [];
		var jobStatsSettings = window.job_manager_stats;
		var ajaxUrl = jobStatsSettings.ajax_url;
		var ajaxNonce = jobStatsSettings.ajax_nonce;
		var cookiesToSet = [];
		var setCookies = function () {
			cookiesToSet.forEach( function ( uniqueKey ) {
				createCookie( uniqueKey, 1, 1, '' );
			} );
		};


		jobStatsSettings.stats_to_log.forEach( function ( statToRecord ) {
			// do something.
			var statToRecordKey = statToRecord.name;
			var uniqueKey = statToRecord.unique_key || '';
			if ( uniqueKey.length === 0 ) {
				statsToRecord.push( statToRecordKey );
			} else {
				if ( null === readCookie( uniqueKey) ) {
					cookiesToSet.push( uniqueKey );
					statsToRecord.push( statToRecordKey );
				}
			}
		} );
		// console.log( statsToRecord ); return;

		var postData = new URLSearchParams();
		postData.append( '_ajax_nonce', ajaxNonce );
		postData.append( 'post_id', jobStatsSettings.post_id || 0 );
		postData.append( 'action', 'job_manager_log_stat' );
		postData.append( 'stats', statsToRecord.join(',') );
		fetch( ajaxUrl, {
			method: 'POST',
			credentials: "same-origin",
			body: postData,
		} ).finally( function () {
			setCookies();
			console.log( 'sent');
		} );
	} );
}() );
