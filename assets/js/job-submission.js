jQuery(document).ready(function($) {
	$( document.body ).on( 'click', '.job-manager-remove-uploaded-file', function() {
		$(this).closest( '.job-manager-uploaded-file' ).remove();
		return false;
	});
	$( document.body ).on( 'submit', '.job-manager-form:not(.prevent-spinner-behavior)', function() {
		$(this).find( '.spinner' ).addClass( 'is-active' );
		$(this).find( 'input[type=submit]' ).addClass( 'disabled' ).on( 'click', function() { return false; } );
	});
});
