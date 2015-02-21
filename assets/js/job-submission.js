jQuery(document).ready(function($) {
	jQuery('body').on( 'click', '.job-manager-remove-uploaded-file', function() {
		jQuery(this).closest( '.job-manager-uploaded-file' ).remove();
		return false;
	});
});