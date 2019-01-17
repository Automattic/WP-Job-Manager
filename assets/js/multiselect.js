/* global job_manager_select2_args */
jQuery( function( $ ) {
	if ( $.isFunction( $.fn.select2 ) && typeof job_manager_select2_args !== 'undefined' ) {
		$( '.job-manager-multiselect:visible' ).select2( job_manager_select2_args );
	}
} );
