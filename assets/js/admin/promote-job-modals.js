export const postOpenPromoteModal = ( dialog, href ) => {
	dialog.innerHTML = `
	<form class="dialog" method="dialog">
		<button class="dialog-close" type="submit">X</button>
	</form>
	<promote-job-template>
		<div slot="buttons" class="promote-buttons-group">
			<a id="wpjm-promote-button" class="promote-button button button-primary" target="_blank" rel="noopener noreferrer" href="${ href }">${ job_manager_admin_params.job_listing_promote_strings.promote_job }</a>
			<a class="promote-button button button-secondary" target="_blank" rel="noopener noreferrer" href="#">${ job_manager_admin_params.job_listing_promote_strings.learn_more }</a>
		</div>
	<promote-job-template>`;

	dialog.querySelector( '#wpjm-promote-button' ).addEventListener( 'click', function() {
		dialog.close();
	} );
};

export const postOpenDeactivateModal = ( dialog, href ) => {
	const deactivateButton = dialog.querySelector( '.deactivate-promotion' );
	deactivateButton.setAttribute( 'href', href );
};
