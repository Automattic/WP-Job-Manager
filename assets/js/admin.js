jQuery(document).ready(function($) {
	// Tooltips
	$( ".tips, .help_tip" ).tipTip({
		'attribute' : 'data-tip',
		'fadeIn' : 50,
		'fadeOut' : 50,
		'delay' : 200
	});

	// Datepicker
	$( "input#_job_expires" ).datepicker({
		dateFormat: 'yy-mm-dd',
		minDate: 0
	});
});