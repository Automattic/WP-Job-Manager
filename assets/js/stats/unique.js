export function updateDailyUnique( key ) {
	const date = new Date();
	const expiresAtTimestamp = date.getTime() + 24 * 60 * 60 * 1000;
	window.localStorage[ key ] = expiresAtTimestamp;
}

export function getDailyUnique( name ) {
	if ( window.localStorage[ name ] ) {
		const date = new Date();
		const now = date.getTime();
		const expiration = parseInt( window.localStorage[ name ], 10 );
		return Number.isNaN( expiration ) ? false : expiration >= now;
	}
	return false;
}

export function setUniques( uniquesToSet ) {
	uniquesToSet.forEach( function ( uniqueKey ) {
		updateDailyUnique( uniqueKey );
	} );
}

export function checkUniqueRecordedToday( statToRecord ) {
	const uniqueKey = statToRecord.unique_key || '';
	return checkUnique( statToRecord ) && true === getDailyUnique( uniqueKey );
}

export function checkUnique( statToRecord ) {
	const uniqueKey = statToRecord.unique_key || '';
	const isUnique = uniqueKey.length > 0;

	return isUnique;
}

export function scheduleStaleUniqueCleanup( statsToRecord ) {
	const twoDaysInMillis = 24 * 60 * 60 * 1000 * 2;
	const cleanup = function () {
		const keyPrefixes = statsToRecord
			.filter( s => s.unique_key && s.unique_key.length > 0 )
			.map( s => s.name );

		for ( let i = 0; i < localStorage.length; i++ ) {
			const key = localStorage.key( i );
			const expiry = parseInt( localStorage.getItem( key ), 10 );
			const containsUniqueKeyPrefix = keyPrefixes.some( k => key.indexOf( k ) === 0 );
			const dateNow = +new Date();
			if ( ! isNaN( expiry ) && containsUniqueKeyPrefix && expiry + twoDaysInMillis < dateNow ) {
				localStorage.removeItem( key );
			}
		}
	};
	setTimeout( cleanup, 1000 );
}
