/* global inlineEditPost */

/**
 * Populates the Featured/Filled checkboxes when Quick Edit opens for a job listing.
 *
 * @param {Function} $ jQuery.
 */
export const initializeJobQuickEdit = $ => {
	if ( typeof inlineEditPost === 'undefined' ) {
		return;
	}

	const wpInlineEdit = inlineEditPost.edit;

	inlineEditPost.edit = function ( id ) {
		wpInlineEdit.apply( this, arguments );

		const postId = typeof id === 'object' ? this.getId( id ) : id;
		const $rowData = $( '#inline_' + postId );

		if ( ! $rowData.length ) {
			return;
		}

		const $editRow = $( '#edit-' + postId );

		$editRow
			.find( 'input[name="_featured"]' )
			.prop( 'checked', '1' === $rowData.find( '.featured' ).text() );
		$editRow
			.find( 'input[name="_filled"]' )
			.prop( 'checked', '1' === $rowData.find( '.filled' ).text() );
	};
};
