/**
 * Helpers for checking a plugin's "Tested up to" headers against the current
 * WordPress release when preparing a release.
 *
 * "Tested up to" is a major.minor WordPress version. It lives in two places,
 * the main plugin file and readme.txt, and the two should agree.
 */

const VERSION_CHECK_URL = 'https://api.wordpress.org/core/version-check/1.7/';
const HEADER_PATTERN    = /(Tested up to:[ \t]*)(.*)/;

/**
 * Reduce a version to its major.minor parts, so `7.1.4` becomes `7.1`.
 *
 * @param {string} version A WordPress version.
 * @return {string} The major.minor version, or an empty string if there is none.
 */
export function majorMinor( version ) {
	const match = String( version ?? '' ).trim().match( /^(\d+)\.(\d+)/ );
	return match ? `${ match[ 1 ] }.${ match[ 2 ] }` : '';
}

/**
 * Read the "Tested up to" value from a plugin file or readme.
 *
 * @param {string} contents The file contents.
 * @return {string} The value as written, or an empty string if the header is missing.
 */
export function readTestedUpTo( contents ) {
	const match = contents.match( HEADER_PATTERN );
	return match ? match[ 2 ].trim() : '';
}

/**
 * Set the "Tested up to" value in a plugin file or readme. Contents without the
 * header are returned unchanged.
 *
 * @param {string} contents The file contents.
 * @param {string} value    The new value.
 * @return {string} The updated contents.
 */
export function setTestedUpTo( contents, value ) {
	return contents.replace( HEADER_PATTERN, `$1${ value }` );
}

/**
 * Whether a "Tested up to" value is older than a WordPress version. A missing
 * or unreadable value counts as older.
 *
 * @param {string} tested The "Tested up to" value.
 * @param {string} latest The WordPress version to compare with.
 * @return {boolean} True if `tested` is behind `latest`.
 */
export function isBehind( tested, latest ) {
	const [ testedMajor, testedMinor ] = majorMinor( tested ).split( '.' ).map( Number );

	if ( Number.isNaN( testedMajor ) || undefined === testedMinor ) {
		return true;
	}

	const [ latestMajor, latestMinor ] = majorMinor( latest ).split( '.' ).map( Number );

	return testedMajor < latestMajor || ( testedMajor === latestMajor && testedMinor < latestMinor );
}

/**
 * Describe what is wrong with a plugin's "Tested up to" headers, if anything.
 *
 * @param {Object} tested        The values found.
 * @param {string} tested.header The value in the main plugin file.
 * @param {string} tested.readme The value in readme.txt.
 * @param {string} latest        The current WordPress major.minor version.
 * @return {string[]} One line per problem. Empty when the headers agree and are current.
 */
export function testedUpToProblems( { header, readme }, latest ) {
	const problems = [];

	if ( majorMinor( header ) !== majorMinor( readme ) ) {
		problems.push( `"Tested up to" is ${ header || 'missing' } in the plugin header but ${ readme || 'missing' } in the readme.` );
	}

	const behind = [ ...new Set( [ header, readme ] ) ].filter( ( value ) => isBehind( value, latest ) );
	if ( behind.length ) {
		problems.push( `"Tested up to" ${ behind.map( ( value ) => value || 'missing' ).join( ' / ' ) } is behind WordPress ${ latest }.` );
	}

	return problems;
}

/**
 * Get the current stable WordPress version from wordpress.org.
 *
 * @return {Promise<string>} The major.minor version, or an empty string if it could not be fetched.
 */
export async function fetchLatestWordPress() {
	try {
		const response = await fetch( VERSION_CHECK_URL, { signal: AbortSignal.timeout( 10000 ) } );
		const { offers } = await response.json();
		return majorMinor( offers[ 0 ].version );
	} catch {
		return '';
	}
}
