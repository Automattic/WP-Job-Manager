export function updateDailyUnique( key ) {
	const date = new Date();
	const expiresAtTimestamp = date.getTime() + 24 * 60 * 60 * 1000;
	window.localStorage[key] = expiresAtTimestamp;
}

export function getDailyUnique( name ) {
	if ( window.localStorage[name] ) {
		const date = new Date();
		const now  = date.getTime();
		const expiration = parseInt( window.localStorage[ name ], 10 );
		return expiration >= now;
	}
	return false;
}

export function setUniques( uniquesToSet ) {
	uniquesToSet.forEach( function( uniqueKey ) {
		updateDailyUnique( uniqueKey );
	} );
}

export function checkUniqueRecordedToday( statToRecord ) {
	const uniqueKey = statToRecord.unique_key || '';
	return checkUnique( statToRecord ) && true === getDailyUnique( uniqueKey );
}

export function checkUnique( statToRecord ) {
	const uniqueKey = statToRecord.unique_key || '';
	const isUnique  = uniqueKey.length > 0;

	return isUnique;
}
