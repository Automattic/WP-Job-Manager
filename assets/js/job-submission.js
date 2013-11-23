jQuery(document).ready(function($) {
	jQuery( '.job-manager-remove-uploaded-image' ).click(function() {
		jQuery( '.job-manager-uploaded-image' ).remove();
		return false;
	});
});