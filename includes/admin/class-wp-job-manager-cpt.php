<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WP_Job_Manager_CPT class.
 */
class WP_Job_Manager_CPT {

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		add_action( "restrict_manage_posts", array( $this, "jobs_by_category" ) );
		add_filter( 'enter_title_here', array( $this, 'enter_title_here' ), 1, 2 );
		add_filter( 'manage_edit-job_listing_columns', array( $this, 'columns' ) );
		add_action( 'manage_job_listing_posts_custom_column', array( $this, 'custom_columns' ), 2 );
		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );

		foreach ( array( 'post', 'post-new' ) as $hook )
			add_action( "admin_footer-{$hook}.php", array( $this,'extend_submitdiv_post_status' ) );
	}

	/**
	 * jobs_by_category function.
	 *
	 * @access public
	 * @param int $show_counts (default: 1)
	 * @param int $hierarchical (default: 1)
	 * @param int $show_uncategorized (default: 1)
	 * @param string $orderby (default: '')
	 * @return void
	 */
	public function jobs_by_category( $show_counts = 1, $hierarchical = 1, $show_uncategorized = 1, $orderby = '' ) {
		global $typenow, $wp_query;

	    if ( $typenow != 'job_listing' )
	    	return;

		include_once( 'class-wp-job-manager-category-walker.php' );

		$r = array();
		$r['pad_counts'] 	= 1;
		$r['hierarchical'] 	= $hierarchical;
		$r['hide_empty'] 	= 1;
		$r['show_count'] 	= $show_counts;
		$r['selected'] 		= ( isset( $wp_query->query['job_listing_category'] ) ) ? $wp_query->query['job_listing_category'] : '';

		$r['menu_order'] = false;

		if ( $orderby == 'order' )
			$r['menu_order'] = 'asc';
		elseif ( $orderby )
			$r['orderby'] = $orderby;

		$terms = get_terms( 'job_listing_category', $r );

		if (!$terms) return;

		$output  = "<select name='job_listing_category' id='dropdown_job_listing_category'>";
		$output .= '<option value="" ' .  selected( isset( $_GET['job_listing_category'] ) ? $_GET['job_listing_category'] : '', '', false ) . '>'.__( 'Select a category', "job_manager" ).'</option>';
		$output .= $this->walk_category_dropdown_tree( $terms, 0, $r );
		$output .="</select>";

		echo $output;
	}

	/**
	 * Walk the Product Categories.
	 *
	 * @access public
	 * @return void
	 */
	private function walk_category_dropdown_tree() {
		$args = func_get_args();

		// the user's options are the third parameter
		if ( empty($args[2]['walker']) || !is_a($args[2]['walker'], 'Walker') )
			$walker = new WP_Job_Manager_Category_Walker;
		else
			$walker = $args[2]['walker'];

		return call_user_func_array( array( $walker, 'walk' ), $args );
	}

	/**
	 * enter_title_here function.
	 *
	 * @access public
	 * @return void
	 */
	public function enter_title_here( $text, $post ) {
		if ( $post->post_type == 'job_listing' )
			return __( 'Job position title', "job_manager" );
		return $text;
	}

	/**
	 * post_updated_messages function.
	 *
	 * @access public
	 * @param mixed $messages
	 * @return void
	 */
	public function post_updated_messages( $messages ) {
		global $post, $post_ID;

		$messages['job_listing'] = array(
			0 => '',
			1 => sprintf( __( 'Job listing updated. <a href="%s">View Job</a>', "job_manager" ), esc_url( get_permalink( $post_ID ) ) ),
			2 => __( 'Custom field updated.', "job_manager" ),
			3 => __( 'Custom field deleted.', "job_manager" ),
			4 => __( 'Job listing updated.', "job_manager" ),
			5 => isset( $_GET['revision'] ) ? sprintf( __( 'Job listing restored to revision from %s', "job_manager" ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => sprintf( __( 'Job listing published. <a href="%s">View Job</a>', "job_manager" ), esc_url( get_permalink( $post_ID ) ) ),
			7 => __('Job listing saved.', "job_manager"),
			8 => sprintf( __( 'Job listing submitted. <a target="_blank" href="%s">Preview Job</a>', "job_manager" ), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
			9 => sprintf( __( 'Job listing scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview Job</a>', "job_manager" ),
			  date_i18n( __( 'M j, Y @ G:i', "job_manager" ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post_ID ) ) ),
			10 => sprintf( __( 'Job listing draft updated. <a target="_blank" href="%s">Preview Job</a>', "job_manager" ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
		);

		return $messages;
	}

	/**
	 * columns function.
	 *
	 * @access public
	 * @param mixed $columns
	 * @return void
	 */
	public function columns( $columns ) {
		$columns = array();

		$columns["cb"]                   = "<input type=\"checkbox\" />";
		$columns["job_listing_type"]     = __( "Type", "job_manager" );
		$columns["position"]             = __( "Position", "job_manager" );
		$columns["company"]              = __( "Company", "job_manager" );
		$columns["job_posted"]           = __( "Posted", "job_manager" );
		$columns["job_expires"]           = __( "Expires", "job_manager" );
		if ( get_option( 'job_manager_enable_categories' ) )
			$columns["job_listing_category"] = __( "Categories", "job_manager" );
		$columns['status']               = __( "Job Status", "job_manager" );
		$columns['filled']               = __( "Filled?", "job_manager" );
		$columns["job_actions"]          = __( "Actions", "job_manager" );

		return $columns;
	}

	/**
	 * custom_columns function.
	 *
	 * @access public
	 * @param mixed $column
	 * @return void
	 */
	public function custom_columns( $column ) {
		global $post, $job_manager;

		switch ( $column ) {
			case "job_listing_type" :
				$type = get_the_job_type( $post );
				if ( $type )
					echo '<span class="job-type ' . $type->slug . '">' . $type->name . '</span>';
			break;
			case "position" :
				edit_post_link( $post->post_title, '<strong>', '</strong>', $post->ID );
				echo '<span class="location">' . get_the_job_location( $post ) . '</span>';
			break;
			case "company" :
				the_company_logo();
				the_company_twitter();

				if ( get_the_company_website() )
					the_company_name( '<strong><a href="' . get_the_company_website() . '">', '</a></strong>' );
				else
					the_company_name( '<strong>', '</strong> ' );

				the_company_tagline( '<span class="tagline">', '</span>' );
			break;
			case "job_listing_category" :
				if ( ! $terms = get_the_term_list( $post->ID, $column, '', ', ', '' ) ) echo '<span class="na">&ndash;</span>'; else echo $terms;
			break;
			case "status" :
				echo ucwords( $post->post_status );
			break;
			case "filled" :
				if ( is_position_filled( $post ) ) echo '&#10004;'; else echo '&ndash;';
			break;
			case "job_posted" :
				echo '<strong>' . date_i18n( get_option( 'date_format' ), strtotime( $post->post_date ) ) . '</strong><span>';
				echo ( empty( $post->post_author ) ? __( 'by a guest', 'job_manager' ) : sprintf( __( 'by %s', 'job_manager' ), '<a href="' . get_edit_user_link( $post->post_author ) . '">' . get_the_author() . '</a>' ) ) . '</span>';
			break;
			case "job_expires" :
				if ( $post->_job_expires )
					echo '<strong>' . date_i18n( get_option( 'date_format' ), strtotime( $post->_job_expires ) ) . '</strong>';
				else
					echo '&ndash;';
			break;
			case "job_actions" :
				$admin_actions           = array();
				if ( $post->post_status !== 'trash' ) {
					$admin_actions['view']   = array(
						'action'  => 'view',
						'name'    => __( 'View', 'job_manager' ),
						'url'     => get_permalink( $post->ID )
					);
					$admin_actions['edit']   = array(
						'action'  => 'edit',
						'name'    => __( 'Edit', 'job_manager' ),
						'url'     => get_edit_post_link( $post->ID )
					);
					$admin_actions['delete'] = array(
						'action'  => 'delete',
						'name'    => __( 'Delete', 'job_manager' ),
						'url'     => get_delete_post_link( $post->ID )
					);
				}

				$admin_actions = apply_filters( 'wp_job_manager_admin_actions', $admin_actions, $post );

				foreach ( $admin_actions as $action ) {
					$image = isset( $action['image_url'] ) ? $action['image_url'] : JOB_MANAGER_PLUGIN_URL . '/assets/images/icons/' . $action['action'] . '.png';
					printf( '<a class="button tips" href="%s" data-tip="%s"><img src="%s" alt="%s" width="14" /></a>', esc_url( $action['url'] ), esc_attr( $action['name'] ), esc_attr( $image ), esc_attr( $action['name'] ) );
				}

			break;
		}
	}

    /**
	 * Adds post status to the "submitdiv" Meta Box and post type WP List Table screens. Based on https://gist.github.com/franz-josef-kaiser/2930190
	 *
	 * @return void
	 */
	public function extend_submitdiv_post_status() {
		global $wp_post_statuses, $post, $post_type;

		// Abort if we're on the wrong post type, but only if we got a restriction
		if ( 'job_listing' !== $post_type ) {
			return;
		}

		// Get all non-builtin post status and add them as <option>
		$options = $display = '';
		foreach ( $wp_post_statuses as $status )
		{
			if ( ! $status->_builtin ) {
				// Match against the current posts status
				$selected = selected( $post->post_status, $status->name, false );

				// If we one of our custom post status is selected, remember it
				$selected AND $display = $status->label;

				// Build the options
				$options .= "<option{$selected} value='{$status->name}'>{$status->label}</option>";
			}
		}
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function($)
			{
				<?php
				// Add the selected post status label to the "Status: [Name] (Edit)"
				if ( ! empty( $display ) ) :
				?>
					$( '#post-status-display' ).html( '<?php echo $display; ?>' )
				<?php
				endif;

				// Add the options to the <select> element
				?>
				var select = $( '#post-status-select' ).find( 'select' );
				$( select ).append( "<?php echo $options; ?>" );
			} );
		</script>
		<?php
	}
}

new WP_Job_Manager_CPT();