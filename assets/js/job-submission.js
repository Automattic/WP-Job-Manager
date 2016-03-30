jQuery(document).ready(function($) {
	$('body').on( 'click', '.job-manager-remove-uploaded-file', function() {
		$(this).closest( '.job-manager-uploaded-file' ).remove();
		return false;
	});
});