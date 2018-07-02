<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles legacy actions and filters specific to the custom post type for Job Listings.
 *
 * @package wp-job-manager
 * @since 1.27.0
 */
class WP_Job_Manager_CPT_Legacy extends WP_Job_Manager_CPT {
	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.27.0
	 */
	private static $_instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.27.0
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_action( 'admin_footer-edit.php', array( $this, 'add_bulk_actions_legacy' ) );
		add_action( 'load-edit.php', array( $this, 'do_bulk_actions_legacy' ) );
		remove_action( 'bulk_actions-edit-job_listing', array( $this, 'add_bulk_actions' ) );
	}

	/**
	 * Adds bulk actions to drop downs on Job Listing admin page.
	 */
	public function add_bulk_actions_legacy() {
		global $post_type, $wp_post_types;

		$bulk_actions = array();
		foreach ( $this->get_bulk_actions() as $key => $bulk_action ) {
			$bulk_actions[] = array(
				'key'   => $key,
				'label' => sprintf( $bulk_action['label'], $wp_post_types['job_listing']->labels->name ),
			);
		}

		if ( 'job_listing' === $post_type ) {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function() {
					var actions = <?php echo wp_json_encode( $bulk_actions ); ?>;
					actions.forEach(function(el){
						jQuery( '<option>').val( el.key ).text(el.label).appendTo("select[name='action']");
						jQuery( '<option>').val( el.key ).text(el.label).appendTo("select[name='action2']");
					});
				});
			</script>
			<?php
		}
	}

	/**
	 * Performs bulk actions on Job Listing admin page.
	 */
	public function do_bulk_actions_legacy() {
		$wp_list_table   = _get_list_table( 'WP_Posts_List_Table' );
		$action          = $wp_list_table->current_action();
		$actions_handled = $this->get_bulk_actions();
		if ( isset( $actions_handled[ $action ] ) && isset( $actions_handled[ $action ]['handler'] ) ) {
			check_admin_referer( 'bulk-posts' );
			$post_ids = array_map( 'absint', array_filter( (array) $_GET['post'] ) );
			if ( ! empty( $post_ids ) ) {
				$this->do_bulk_actions( admin_url( 'edit.php?post_type=job_listing' ), $action, $post_ids );
			}
		}
	}
}
