jQuery(document).ready(function($) {
	jQuery( '.job-manager-remove-uploaded-file' ).click(function() {
		jQuery( '.job-manager-uploaded-file' ).remove();
		return false;
	});
});