/**
 * Internal dependencies
 */
import wpjmModal from './wpjm-modal';

export const postOpenPromoteModal = ( dialog, href ) => {
	dialog.innerHTML = `
	<form class="dialog" method="dialog">
		<button class="dialog-close" type="submit">X</button>
	</form>
	<promote-job-template>
		<div slot="buttons" class="promote-buttons-group">
			<a id="wpjm-promote-button" class="promote-button button button-primary" target="_blank" rel="noopener noreferrer" href="${ href }">${ job_manager_admin_params.job_listing_promote_strings.promote_job }</a>
			<a class="promote-button button button-secondary" target="_blank" rel="noopener noreferrer" href="https://wpjobmanager.com/jobtarget?utm_source=plugin_wpjm&utm_medium=promote-dialog&utm_campaign=promoted-jobs">${ job_manager_admin_params.job_listing_promote_strings.learn_more }</a>
			<a class="promote-dismiss wpjm-notice-dismiss" href="#">${ job_manager_admin_params.job_listing_promote_strings.dismiss }</a>
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

export const initializePromoteModals = () => {
	wpjmModal( '.promote_job', '#promote-dialog', ( element, dialog ) => {
		const href = element.getAttribute( 'data-href' );
		postOpenPromoteModal( dialog, href );
	} );
	wpjmModal( '.jm-promoted__deactivate', '#deactivate-dialog', ( element, dialog ) => {
		const href = element.getAttribute( 'data-href' );
		postOpenDeactivateModal( dialog, href );
	} );

	customElements.define( 'promote-job-template',
		class extends HTMLElement {
			constructor() {
				super();
				const promoteJobs = document.getElementById( 'promote-job-template' ).content;
				const shadowRoot  = this.attachShadow( {
					mode: 'open',
				} );
				shadowRoot.appendChild( promoteJobs.cloneNode( true ) );
			}
		} );
}
