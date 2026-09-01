/**
 * Progressively enhances `.wpjm-local-time` elements with the visitor's local time,
 * appended alongside the site-time value already rendered server-side.
 */
function localizeJobManagerTimes() {
	const elements = document.querySelectorAll( '.wpjm-local-time[datetime]' );

	if ( ! elements.length || typeof Intl === 'undefined' ) {
		return;
	}

	elements.forEach( function ( el ) {
		const date = new Date( el.getAttribute( 'datetime' ) );

		if ( isNaN( date.getTime() ) ) {
			return;
		}

		const options = { dateStyle: 'long', timeStyle: 'short' };
		const localText = new Intl.DateTimeFormat( undefined, options ).format( date );
		const siteTimezone = el.getAttribute( 'data-site-timezone' );

		try {
			if ( siteTimezone ) {
				const siteText = new Intl.DateTimeFormat(
					undefined,
					Object.assign( {}, options, { timeZone: siteTimezone } )
				).format( date );

				if ( siteText === localText ) {
					return;
				}
			}
		} catch ( error ) {
			// Site timezone isn't a recognized IANA identifier (e.g. a manual UTC offset).
			// Fall through and show the visitor's local time anyway.
		}

		const label = el.getAttribute( 'data-local-label' ) || '%s';
		el.insertAdjacentText( 'beforeend', ' ' + label.replace( '%s', localText ) );
	} );
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', localizeJobManagerTimes );
} else {
	localizeJobManagerTimes();
}
