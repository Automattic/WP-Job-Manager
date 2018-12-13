<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles initial environment setup after plugin is first activated.
 *
 * @package wp-job-manager
 * @since 1.16.0
 */
class WP_Job_Manager_Setup {

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
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 12 );
		add_action( 'admin_head', array( $this, 'admin_head' ) );
		if ( isset( $_GET['page'] ) && 'job-manager-setup' === $_GET['page'] ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 12 );
		}
	}

	/**
	 * Adds setup link to admin dashboard menu briefly so the page callback is registered.
	 */
	public function admin_menu() {
		add_dashboard_page( __( 'Setup', 'wp-job-manager' ), __( 'Setup', 'wp-job-manager' ), 'manage_options', 'job-manager-setup', array( $this, 'setup_page' ) );
	}

	/**
	 * Removes the setup link from admin dashboard menu so just the handler callback is registered.
	 */
	public function admin_head() {
		remove_submenu_page( 'index.php', 'job-manager-setup' );
	}

	/**
	 * Enqueues scripts for setup page.
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_style( 'job_manager_setup_css', JOB_MANAGER_PLUGIN_URL . '/assets/css/setup.css', array( 'dashicons' ), JOB_MANAGER_VERSION );
	}

	/**
	 * Creates a page.
	 *
	 * @param  string $title
	 * @param  string $content
	 * @param  string $option
	 */
	public function create_page( $title, $content, $option ) {
		$page_data = array(
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'post_author'    => 1,
			'post_name'      => sanitize_title( $title ),
			'post_title'     => $title,
			'post_content'   => $content,
			'post_parent'    => 0,
			'comment_status' => 'closed',
		);
		$page_id   = wp_insert_post( $page_data );

		if ( $option ) {
			update_option( $option, $page_id );
		}
	}

	/**
	 * Handle request to the setup page.
	 */
	public function setup_page() {
		$usage_tracking = WP_Job_Manager_Usage_Tracking::get_instance();
		$step = ! empty( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			// Handle step 1 (usage tracking).
			$enable = isset( $_POST['job_manager_usage_tracking_enabled'] )
				&& '1' === $_POST['job_manager_usage_tracking_enabled'];

			$nonce       = isset( $_POST['nonce'] ) ? $_POST['nonce'] : null;
			$valid_nonce = wp_verify_nonce( $nonce, 'enable-usage-tracking' );

			if ( $valid_nonce ) {
				$usage_tracking->set_tracking_enabled( $enable );
				$usage_tracking->hide_tracking_opt_in();
			}

			// Handle step 2 -> step 3 (setting up pages).
			if ( 3 === $step && ! empty( $_POST ) ) {
				if ( false === wp_verify_nonce( $_REQUEST['setup_wizard'], 'step_3' ) ) {
					wp_die( 'Error in nonce. Try again.', 'wp-job-manager' );
				}
				$create_pages    = isset( $_POST['wp-job-manager-create-page'] ) ? $_POST['wp-job-manager-create-page'] : array();
				$page_titles     = $_POST['wp-job-manager-page-title'];
				$pages_to_create = array(
					'submit_job_form' => '[submit_job_form]',
					'job_dashboard'   => '[job_dashboard]',
					'jobs'            => '[jobs]',
				);

				foreach ( $pages_to_create as $page => $content ) {
					if ( ! isset( $create_pages[ $page ] ) || empty( $page_titles[ $page ] ) ) {
						continue;
					}
					$this->create_page( sanitize_text_field( $page_titles[ $page ] ), $content, 'job_manager_' . $page . '_page_id' );
				}
			}
		}

		// Handle step 3 (from step 1 or 2).
		if ( 3 === $step ) {
			WP_Job_Manager_Admin_Notices::remove_notice( WP_Job_Manager_Admin_Notices::NOTICE_CORE_SETUP );
		}

		$this->output();
	}

	/**
	 * Usage tracking opt in text for setup page.
	 *
	 * Used in `views/html-admin-setup-opt-in-usage-tracking.php`
	 */
	private function opt_in_text() {
		return WP_Job_Manager_Usage_Tracking::get_instance()->opt_in_checkbox_text();
	}

	/**
	 * Output opt-in checkbox if usage tracking isn't already enabled.
	 *
	 * Used in `views/html-admin-setup-step-1.php`
	 */
	private function maybe_output_opt_in_checkbox() {
		// Only show the checkbox if we aren't already opted in.
		$usage_tracking = WP_Job_Manager_Usage_Tracking::get_instance();
		if ( ! $usage_tracking->get_tracking_enabled() ) {
			include dirname( __FILE__ ) . '/views/html-admin-setup-opt-in-usage-tracking.php';
		}
	}

	/**
	 * Displays setup page.
	 */
	public function output() {
		$step = ! empty( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;

		include dirname( __FILE__ ) . '/views/html-admin-setup-header.php';
		if ( 1 === $step ) {
			include dirname( __FILE__ ) . '/views/html-admin-setup-step-1.php';
		} elseif ( 2 === $step ) {
			include dirname( __FILE__ ) . '/views/html-admin-setup-step-2.php';
		} elseif ( 3 === $step ) {
			include dirname( __FILE__ ) . '/views/html-admin-setup-step-3.php';
		}
		include dirname( __FILE__ ) . '/views/html-admin-setup-footer.php';
	}
}

WP_Job_Manager_Setup::instance();
