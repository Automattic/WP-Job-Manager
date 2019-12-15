(function($) {

  // we create a copy of the WP inline edit post function
  var $wp_inline_edit = inlineEditPost.edit;

  // and then we overwrite the function with our own code
  inlineEditPost.edit = function( id ) {

    // "call" the original WP edit function
    // we don't want to leave WordPress hanging
    $wp_inline_edit.apply( this, arguments );

  };

  $( '#bulk_edit' ).live( 'click', function() {
    var $bulk_row = $( '#bulk-edit' );
    // get the selected post ids that are being edited
    var $post_ids = new Array();
    $bulk_row.find( '#bulk-titles' ).children().each( function() {
      $post_ids.push( $( this ).attr( 'id' ).replace( /^(ttle)/i, '' ) );
    });
    // get the job type
    var $job_type = $bulk_row.find( 'input[name="job_listing_type"]:checked' ).val();

    $.ajax({
      url: ajaxurl,
      type: 'POST',
      async: false,
      cache: false,
      data: {
        action: 'bulk_edit_save_job_listing_action',
        post_ids: $post_ids,
        job_type: $job_type
      }
    });
  });

})(jQuery);
