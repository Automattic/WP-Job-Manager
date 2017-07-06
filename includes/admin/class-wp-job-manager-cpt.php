<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Handles actions and filters specific to the custom post type for Job Listings.
 *
 * @package wp-job-manager
 * @since 1.0.0
 */
class WP_Job_Manager_CPT {

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.26.0
	 */
	private static $_instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.26.0
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
		add_filter( 'enter_title_here', array( $this, 'enter_title_here' ), 1, 2 );
		add_filter( 'manage_edit-job_listing_columns', array( $this, 'columns' ) );
		add_filter( 'list_table_primary_column', array( $this, 'primary_column' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ) );
		add_action( 'manage_job_listing_posts_custom_column', array( $this, 'custom_columns' ), 2 );
		add_filter( 'manage_edit-job_listing_sortable_columns', array( $this, 'sortable_columns' ) );
		add_filter( 'request', array( $this, 'sort_columns' ) );
		add_action( 'parse_query', array( $this, 'search_meta' ) );
		add_filter( 'get_search_query', array( $this, 'search_meta_label' ) );
		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );
		add_action( 'bulk_actions-edit-job_listing', array( $this, 'add_bulk_actions' ) );
		add_action( 'handle_bulk_actions-edit-job_listing', array( $this, 'do_bulk_actions' ), 10, 3 );
		add_action( 'admin_init', array( $this, 'approve_job' ) );
		add_action( 'admin_notices', array( $this, 'action_notices' ) );

		if ( get_option( 'job_manager_enable_categories' ) ) {
			add_action( "restrict_manage_posts", array( $this, "jobs_by_category" ) );
		}

		foreach ( array( 'post', 'post-new' ) as $hook ) {
			add_action( "admin_footer-{$hook}.php", array( $this,'extend_submitdiv_post_status' ) );
		}
	}

	/**
	 * Returns the list of bulk actions that can be performed on job listings.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions_handled = array();
		$actions_handled['approve_jobs'] = array(
			'label' => __( 'Approve %s', 'wp-job-manager' ),
			'notice' => __( '%s approved', 'wp-job-manager' ),
			'handler' => array( $this, 'bulk_action_handle_approve_job' ),
		);
		$actions_handled['expire_jobs'] = array(
			'label' => __( 'Expire %s', 'wp-job-manager' ),
			'notice' => __( '%s expired', 'wp-job-manager' ),
			'handler' => array( $this, 'bulk_action_handle_expire_job' ),
		);
		$actions_handled['mark_jobs_filled'] = array(
			'label' => __( 'Mark %s Filled', 'wp-job-manager' ),
			'notice' => __( '%s marked as filled', 'wp-job-manager' ),
			'handler' => array( $this, 'bulk_action_handle_mark_job_filled' ),
		);
		$actions_handled['mark_jobs_not_filled'] = array(
			'label' => __( 'Mark %s Not Filled', 'wp-job-manager' ),
			'notice' => __( '%s marked as not filled', 'wp-job-manager' ),
			'handler' => array( $this, 'bulk_action_handle_mark_job_not_filled' ),
		);

		/**
		 * Filters the bulk actions that can be applied to job listings.
		 *
		 * @since 1.27.0
		 *
		 * @param array $actions_handled {
		 *     Bulk actions that can be handled, indexed by a unique key name (approve_jobs, expire_jobs, etc). Handlers
		 *     are responsible for checking abilities (`current_user_can( 'manage_job_listings', $post_id )`) before
		 *     performing action.
		 *
		 *     @type string   $label   Label for the bulk actions dropdown. Passed through sprintf with label name of job listing post type.
		 *     @type string   $notice  Success notice shown after performing the action. Passed through sprintf with title(s) of affected job listings.
		 *     @type callback $handler Callable handler for performing action. Passed one argument (int $post_id) and should return true on success and false on failure.
		 * }
		 */
		return apply_filters( 'wpjm_job_listing_bulk_actions', $actions_handled );
	}

	/**
	 * Adds bulk actions to drop downs on Job Listing admin page.
	 *
	 * @param array $bulk_actions
	 * @return array
	 */
	public function add_bulk_actions( $bulk_actions ) {
		global $wp_post_types;

		foreach ( $this->get_bulk_actions() as $key => $bulk_action ) {
			if ( isset( $bulk_action['label'] ) ) {
				$bulk_actions[ $key ] = sprintf( $bulk_action['label'], $wp_post_types['job_listing']->labels->name );
			}
		}
		return $bulk_actions;
	}

	/**
	 * Performs bulk actions on Job Listing admin page.
	 *
	 * @since 1.27.0
	 *
	 * @param string $redirect_url The redirect URL.
	 * @param string $action       The action being taken.
	 * @param array  $post_ids     The posts to take the action on.
	 */
	public function do_bulk_actions( $redirect_url, $action, $post_ids ) {
		$actions_handled = $this->get_bulk_actions();
		if ( isset ( $actions_handled[ $action ] ) && isset ( $actions_handled[ $action ]['handler'] ) ) {
			$handled_jobs = array();
			if ( ! empty( $post_ids ) ) {
				foreach ( $post_ids as $post_id ) {
					if ( 'job_listing' === get_post_type( $post_id )
					     && call_user_func( $actions_handled[ $action ]['handler'], $post_id ) ) {
						$handled_jobs[] = $post_id;
					}
				}
				wp_redirect( add_query_arg( 'handled_jobs', $handled_jobs, add_query_arg( 'action_performed', $action, $redirect_url ) ) );
				exit;
			}
		}
	}

	/**
	 * Performs bulk action to approve a single job listing.
	 *
	 * @param $post_id
	 *
	 * @return bool
	 */
	public function bulk_action_handle_approve_job( $post_id ) {
		$job_data = array(
			'ID'          => $post_id,
			'post_status' => 'publish',
		);
		if ( in_array( get_post_status( $post_id ), array( 'pending', 'pending_payment' ) )
		     && current_user_can( 'publish_post', $post_id )
		     && wp_update_post( $job_data )
		) {
			return true;
		}
		return false;
	}

	/**
	 * Performs bulk action to expire a single job listing.
	 *
	 * @param $post_id
	 *
	 * @return bool
	 */
	public function bulk_action_handle_expire_job( $post_id ) {
		$job_data = array(
			'ID'          => $post_id,
			'post_status' => 'expired',
		);
		if ( current_user_can( 'manage_job_listings', $post_id )
		     && wp_update_post( $job_data )
		) {
			return true;
		}
		return false;
	}

	/**
	 * Performs bulk action to mark a single job listing as filled.
	 *
	 * @param $post_id
	 *
	 * @return bool
	 */
	public function bulk_action_handle_mark_job_filled( $post_id ) {
		if ( current_user_can( 'manage_job_listings', $post_id )
		     && update_post_meta( $post_id, '_filled', 1 )
		) {
			return true;
		}
		return false;
	}

	/**
	 * Performs bulk action to mark a single job listing as not filled.
	 *
	 * @param $post_id
	 *
	 * @return bool
	 */
	public function bulk_action_handle_mark_job_not_filled( $post_id ) {
		if ( current_user_can( 'manage_job_listings', $post_id )
		     && update_post_meta( $post_id, '_filled', 0 )
		) {
			return true;
		}
		return false;
	}

	/**
	 * Approves a single job.
	 */
	public function approve_job() {
		if ( ! empty( $_GET['approve_job'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'approve_job' ) && current_user_can( 'publish_post', $_GET['approve_job'] ) ) {
			$post_id = absint( $_GET['approve_job'] );
			$job_data = array(
				'ID'          => $post_id,
				'post_status' => 'publish'
			);
			wp_update_post( $job_data );
			wp_redirect( remove_query_arg( 'approve_job', add_query_arg( 'handled_jobs', $post_id, add_query_arg( 'action_performed', 'approve_jobs', admin_url( 'edit.php?post_type=job_listing' ) ) ) ) );
			exit;
		}
	}

	/**
	 * Shows a notice if we did a bulk action.
	 */
	public function action_notices() {
		global $post_type, $pagenow;

		$handled_jobs = isset ( $_REQUEST['handled_jobs'] ) ? $_REQUEST['handled_jobs'] : false;
		$action = isset ( $_REQUEST['action_performed'] ) ? $_REQUEST['action_performed'] : false;
		$actions_handled = $this->get_bulk_actions();

		if ( $pagenow == 'edit.php'
			 && $post_type == 'job_listing'
			 && $action
			 && ! empty( $handled_jobs )
			 && isset ( $actions_handled[ $action ] )
			 && isset ( $actions_handled[ $action ]['notice'] )
		) {
			if ( is_array( $handled_jobs ) ) {
				$handled_jobs = array_map( 'absint', $handled_jobs );
				$titles       = array();
				foreach ( $handled_jobs as $job_id ) {
					$titles[] = wpjm_get_the_job_title( $job_id );
				}
				echo '<div class="updated"><p>' . sprintf( $actions_handled[ $action ]['notice'], '&quot;' . implode( '&quot;, &quot;', $titles ) . '&quot;' ) . '</p></div>';
			} else {
				echo '<div class="updated"><p>' . sprintf( $actions_handled[ $action ]['notice'], '&quot;' . wpjm_get_the_job_title( absint( $handled_jobs ) ) . '&quot;' ) . '</p></div>';
			}
		}
	}

	/**
	 * Shows category dropdown.
	 */
	public function jobs_by_category() {
		global $typenow, $wp_query;

	    if ( $typenow != 'job_listing' || ! taxonomy_exists( 'job_listing_category' ) ) {
	    	return;
	    }

	    include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-category-walker.php' );

		$r                 = array();
		$r['pad_counts']   = 1;
		$r['hierarchical'] = 1;
		$r['hide_empty']   = 0;
		$r['show_count']   = 1;
		$r['selected']     = ( isset( $wp_query->query['job_listing_category'] ) ) ? $wp_query->query['job_listing_category'] : '';
		$r['menu_order']   = false;
		$terms             = get_terms( 'job_listing_category', $r );
		$walker            = new WP_Job_Manager_Category_Walker;

		if ( ! $terms ) {
			return;
		}

		$output  = "<select name='job_listing_category' id='dropdown_job_listing_category'>";
		$output .= '<option value="" ' . selected( isset( $_GET['job_listing_category'] ) ? $_GET['job_listing_category'] : '', '', false ) . '>' . __( 'Select category', 'wp-job-manager' ) . '</option>';
		$output .= $walker->walk( $terms, 0, $r );
		$output .= "</select>";

		echo $output;
	}

	/**
	 * Filters page title placeholder text to show custom label.
	 *
	 * @param string      $text
	 * @param WP_Post|int $post
	 * @return string
	 */
	public function enter_title_here( $text, $post ) {
		if ( $post->post_type == 'job_listing' )
			return __( 'Position', 'wp-job-manager' );
		return $text;
	}

	/**
	 * Filters the post updated message array to add custom post type's messages.
	 *
	 * @param array $messages
	 * @return array
	 */
	public function post_updated_messages( $messages ) {
		global $post, $post_ID, $wp_post_types;

		$messages['job_listing'] = array(
			0 => '',
			1 => sprintf( __( '%s updated. <a href="%s">View</a>', 'wp-job-manager' ), $wp_post_types['job_listing']->labels->singular_name, esc_url( get_permalink( $post_ID ) ) ),
			2 => __( 'Custom field updated.', 'wp-job-manager' ),
			3 => __( 'Custom field deleted.', 'wp-job-manager' ),
			4 => sprintf( __( '%s updated.', 'wp-job-manager' ), $wp_post_types['job_listing']->labels->singular_name ),
			5 => isset( $_GET['revision'] ) ? sprintf( __( '%s restored to revision from %s', 'wp-job-manager' ), $wp_post_types['job_listing']->labels->singular_name, wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => sprintf( __( '%s published. <a href="%s">View</a>', 'wp-job-manager' ), $wp_post_types['job_listing']->labels->singular_name, esc_url( get_permalink( $post_ID ) ) ),
			7 => sprintf( __( '%s saved.', 'wp-job-manager' ), $wp_post_types['job_listing']->labels->singular_name ),
			8 => sprintf( __( '%s submitted. <a target="_blank" href="%s">Preview</a>', 'wp-job-manager' ), $wp_post_types['job_listing']->labels->singular_name, esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
			9 => sprintf( __( '%s scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview</a>', 'wp-job-manager' ), $wp_post_types['job_listing']->labels->singular_name,
			  date_i18n( __( 'M j, Y @ G:i', 'wp-job-manager' ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post_ID ) ) ),
			10 => sprintf( __( '%s draft updated. <a target="_blank" href="%s">Preview</a>', 'wp-job-manager' ), $wp_post_types['job_listing']->labels->singular_name, esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
		);

		return $messages;
	}

	/**
	 * Adds columns to admin listing of Job Listings.
	 *
	 * @param array $columns
	 * @return array
	 */
	public function columns( $columns ) {
		if ( ! is_array( $columns ) ) {
			$columns = array();
		}

		unset( $columns['title'], $columns['date'], $columns['author'] );

		$columns["job_position"]         = __( "Position", 'wp-job-manager' );
		$columns["job_listing_type"]     = __( "Type", 'wp-job-manager' );
		$columns["job_location"]         = __( "Location", 'wp-job-manager' );
		$columns['job_status']           = '<span class="tips" data-tip="' . __( "Status", 'wp-job-manager' ) . '">' . __( "Status", 'wp-job-manager' ) . '</span>';
		$columns["job_posted"]           = __( "Posted", 'wp-job-manager' );
		$columns["job_expires"]          = __( "Expires", 'wp-job-manager' );
		$columns["job_listing_category"] = __( "Categories", 'wp-job-manager' );
		$columns['featured_job']         = '<span class="tips" data-tip="' . __( "Featured?", 'wp-job-manager' ) . '">' . __( "Featured?", 'wp-job-manager' ) . '</span>';
		$columns['filled']               = '<span class="tips" data-tip="' . __( "Filled?", 'wp-job-manager' ) . '">' . __( "Filled?", 'wp-job-manager' ) . '</span>';
		$columns['job_actions']          = __( "Actions", 'wp-job-manager' );

		if ( ! get_option( 'job_manager_enable_categories' ) ) {
			unset( $columns["job_listing_category"] );
		}

		if ( ! get_option( 'job_manager_enable_types' ) ) {
			unset( $columns["job_listing_type"] );
		}

		return $columns;
	}

	/**
	 * This is required to make column responsive since WP 4.3
	 *
	 * @access public
	 * @param string $column
	 * @param string $screen
	 * @return string
	 */
	public function primary_column( $column, $screen ) {
		if ( 'edit-job_listing' === $screen ) {
			$column = 'job_position';
		}
		return $column;
	}

	/**
	 * Removes all action links because WordPress add it to primary column.
	 * Note: Removing all actions also remove mobile "Show more details" toggle button.
	 * So the button need to be added manually in custom_columns callback for primary column.
	 *
	 * @access public
	 * @param array $actions
	 * @return array
	 */
	public function row_actions( $actions ) {
		if ( 'job_listing' == get_post_type() ) {
			return array();
		}
		return $actions;
	}

	/**
	 * Displays the content for each custom column on the admin list for Job Listings.
	 *
	 * @param mixed $column
	 */
	public function custom_columns( $column ) {
		global $post;

		switch ( $column ) {
			case "job_listing_type" :
				$types = wpjm_get_the_job_types( $post );

				if ( $types && ! empty( $types ) ) {
					foreach ( $types as $type ) {
						echo '<span class="job-type ' . $type->slug . '">' . $type->name . '</span>';
					}
				}
			break;
			case "job_position" :
				echo '<div class="job_position">';
				echo '<a href="' . admin_url('post.php?post=' . $post->ID . '&action=edit') . '" class="tips job_title" data-tip="' . sprintf( __( 'ID: %d', 'wp-job-manager' ), $post->ID ) . '">' . wpjm_get_the_job_title() . '</a>';

				echo '<div class="company">';

				if ( get_the_company_website() ) {
					the_company_name( '<span class="tips" data-tip="' . esc_attr( get_the_company_tagline() ) . '"><a href="' . esc_url( get_the_company_website() ) . '">', '</a></span>' );
				} else {
					the_company_name( '<span class="tips" data-tip="' . esc_attr( get_the_company_tagline() ) . '">', '</span>' );
				}

				echo '</div>';

				the_company_logo();
				echo '</div>';
				echo '<button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>';
			break;
			case "job_location" :
				the_job_location( $post );
			break;
			case "job_listing_category" :
				if ( ! $terms = get_the_term_list( $post->ID, $column, '', ', ', '' ) ) echo '<span class="na">&ndash;</span>'; else echo $terms;
			break;
			case "filled" :
				if ( is_position_filled( $post ) ) echo '&#10004;'; else echo '&ndash;';
			break;
			case "featured_job" :
				if ( is_position_featured( $post ) ) echo '&#10004;'; else echo '&ndash;';
			break;
			case "job_posted" :
				echo '<strong>' . date_i18n( __( 'M j, Y', 'wp-job-manager' ), strtotime( $post->post_date ) ) . '</strong><span>';
				echo ( empty( $post->post_author ) ? __( 'by a guest', 'wp-job-manager' ) : sprintf( __( 'by %s', 'wp-job-manager' ), '<a href="' . esc_url( add_query_arg( 'author', $post->post_author ) ) . '">' . get_the_author() . '</a>' ) ) . '</span>';
			break;
			case "job_expires" :
				if ( $post->_job_expires )
					echo '<strong>' . date_i18n( __( 'M j, Y', 'wp-job-manager' ), strtotime( $post->_job_expires ) ) . '</strong>';
				else
					echo '&ndash;';
			break;
			case "job_status" :
				echo '<span data-tip="' . esc_attr( get_the_job_status( $post ) ) . '" class="tips status-' . esc_attr( $post->post_status ) . '">' . get_the_job_status( $post ) . '</span>';
			break;
			case "job_actions" :
				echo '<div class="actions">';
				$admin_actions = apply_filters( 'post_row_actions', array(), $post );

				if ( in_array( $post->post_status, array( 'pending', 'pending_payment' ) ) && current_user_can ( 'publish_post', $post->ID ) ) {
					$admin_actions['approve']   = array(
						'action'  => 'approve',
						'name'    => __( 'Approve', 'wp-job-manager' ),
						'url'     =>  wp_nonce_url( add_query_arg( 'approve_job', $post->ID ), 'approve_job' )
					);
				}
				if ( $post->post_status !== 'trash' ) {
					if ( current_user_can( 'read_post', $post->ID ) ) {
						$admin_actions['view']   = array(
							'action'  => 'view',
							'name'    => __( 'View', 'wp-job-manager' ),
							'url'     => get_permalink( $post->ID )
						);
					}
					if ( current_user_can( 'edit_post', $post->ID ) ) {
						$admin_actions['edit']   = array(
							'action'  => 'edit',
							'name'    => __( 'Edit', 'wp-job-manager' ),
							'url'     => get_edit_post_link( $post->ID )
						);
					}
					if ( current_user_can( 'delete_post', $post->ID ) ) {
						$admin_actions['delete'] = array(
							'action'  => 'delete',
							'name'    => __( 'Delete', 'wp-job-manager' ),
							'url'     => get_delete_post_link( $post->ID )
						);
					}
				}

				$admin_actions = apply_filters( 'job_manager_admin_actions', $admin_actions, $post );

				foreach ( $admin_actions as $action ) {
					if ( is_array( $action ) ) {
						printf( '<a class="button button-icon tips icon-%1$s" href="%2$s" data-tip="%3$s">%4$s</a>', $action['action'], esc_url( $action['url'] ), esc_attr( $action['name'] ), esc_html( $action['name'] ) );
					} else {
						echo str_replace( 'class="', 'class="button ', $action );
					}
				}

				echo '</div>';

			break;
		}
	}

	/**
	 * Filters the list table sortable columns for the admin list of Job Listings.
	 *
	 * @param mixed $columns
	 * @return array
	 */
	public function sortable_columns( $columns ) {
		$custom = array(
			'job_posted'   => 'date',
			'job_position' => 'title',
			'job_location' => 'job_location',
			'job_expires'  => 'job_expires'
		);
		return wp_parse_args( $custom, $columns );
	}

	/**
	 * Sorts the admin listing of Job Listings by updating the main query in the request.
	 *
	 * @param mixed $vars
	 * @return array
	 */
	public function sort_columns( $vars ) {
		if ( isset( $vars['orderby'] ) ) {
			if ( 'job_expires' === $vars['orderby'] ) {
				$vars = array_merge( $vars, array(
					'meta_key' 	=> '_job_expires',
					'orderby' 	=> 'meta_value'
				) );
			} elseif ( 'job_location' === $vars['orderby'] ) {
				$vars = array_merge( $vars, array(
					'meta_key' 	=> '_job_location',
					'orderby' 	=> 'meta_value'
				) );
			}
		}
		return $vars;
	}

	/**
	 * Searches custom fields as well as content.
	 *
	 * @param WP_Query $wp
	 */
	public function search_meta( $wp ) {
		global $pagenow, $wpdb;

		if ( 'edit.php' !== $pagenow || empty( $wp->query_vars['s'] ) || 'job_listing' !== $wp->query_vars['post_type'] ) {
			return;
		}

		$post_ids = array_unique( array_merge(
			$wpdb->get_col(
				$wpdb->prepare( "
					SELECT posts.ID
					FROM {$wpdb->posts} posts
					INNER JOIN {$wpdb->postmeta} p1 ON posts.ID = p1.post_id
					WHERE p1.meta_value LIKE '%%%s%%'
					OR posts.post_title LIKE '%%%s%%'
					OR posts.post_content LIKE '%%%s%%'
					AND posts.post_type = 'job_listing'
					",
					esc_sql( $wp->query_vars['s'] ),
					esc_sql( $wp->query_vars['s'] ),
					esc_sql( $wp->query_vars['s'] )
				)
			),
			array( 0 )
		) );

		// Adjust the query vars
		unset( $wp->query_vars['s'] );
		$wp->query_vars['job_listing_search'] = true;
		$wp->query_vars['post__in'] = $post_ids;
	}

	/**
	 * Changes the label when searching meta.
	 *
	 * @param string $query
	 * @return string
	 */
	public function search_meta_label( $query ) {
		global $pagenow, $typenow;

		if ( 'edit.php' !== $pagenow || $typenow !== 'job_listing' || ! get_query_var( 'job_listing_search' ) ) {
			return $query;
		}

		return wp_unslash( sanitize_text_field( $_GET['s'] ) );
	}

	/**
	 * Adds post status to the "submitdiv" Meta Box and post type WP List Table screens. Based on https://gist.github.com/franz-josef-kaiser/2930190
	 */
	public function extend_submitdiv_post_status() {
		global $post, $post_type;

		// Abort if we're on the wrong post type, but only if we got a restriction
		if ( 'job_listing' !== $post_type ) {
			return;
		}

		// Get all non-builtin post status and add them as <option>
		$options = $display = '';
		foreach ( get_job_listing_post_statuses() as $status => $name ) {
			$selected = selected( $post->post_status, $status, false );

			// If we one of our custom post status is selected, remember it
			$selected AND $display = $name;

			// Build the options
			$options .= "<option{$selected} value='{$status}'>{$name}</option>";
		}
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function($) {
				<?php if ( ! empty( $display ) ) : ?>
					jQuery( '#post-status-display' ).html( '<?php echo $display; ?>' );
				<?php endif; ?>

				var select = jQuery( '#post-status-select' ).find( 'select' );
				jQuery( select ).html( "<?php echo $options; ?>" );
			} );
		</script>
		<?php
	}
}
