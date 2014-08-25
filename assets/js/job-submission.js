jQuery(document).ready(function($) {
	jQuery( '.job-manager-remove-uploaded-file' ).click(function() {
		jQuery(this).closest( '.job-manager-uploaded-file' ).remove();
		return false;
	});
});