
export function debounce(func, delay) {
	let timeoutId;

	return function (...args) {
		clearTimeout(timeoutId);

		timeoutId = setTimeout(() => {
			func(...args);
		}, delay);
	};
}

export function filterZeroes( list ) {
	return list.filter( function (i) { return i > 0; } );
}

export function findIdInClassNames( node ) {
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



