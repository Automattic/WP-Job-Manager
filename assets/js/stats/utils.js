export function debounce( func, delay ) {
	let timeoutId;

	return function ( ...args ) {
		clearTimeout( timeoutId );

		timeoutId = setTimeout( () => {
			func( ...args );
		}, delay );
	};
}

export function filterZeroes( list ) {
	return list.filter( function ( i ) {
		return i > 0;
	} );
}

export function findIdInClassNames( node ) {
	return +node.className.match( /\bpost-(\d+)\b/ )?.[ 1 ] || 0;
}
