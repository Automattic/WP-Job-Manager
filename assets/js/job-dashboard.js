/* global job_manager_job_dashboard */
jQuery(document).ready(function($) {

	$('.job-dashboard-action-delete').click(function() {
		return window.confirm( job_manager_job_dashboard.i18n_confirm_delete );
	});

});