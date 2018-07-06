
/**
 * WordPress Dependencies.
 */
const { __ } = wp.i18n;

/**
 * Sidebar component.
 *
 * @return {Component} the Sidebar component.
 */
export default function Sidebar( { className } ) {
	return (
		<p className={ className }>
			{ __( 'Replaces the [jobs] shortcode' ) }
		</p>
	);
}
