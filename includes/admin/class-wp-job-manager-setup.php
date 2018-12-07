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
		add_action( 'admin_init', array( $this, 'redirect' ) );
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
	 * Sends user to the setup page on first activation.
	 */
	public function redirect() {
		// Bail if no activation redirect transient is set.
		if ( ! get_transient( '_job_manager_activation_redirect' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Delete the redirect transient.
		delete_transient( '_job_manager_activation_redirect' );

		// Bail if activating from network, or bulk, or within an iFrame.
		if ( is_network_admin() || isset( $_GET['activate-multi'] ) || defined( 'IFRAME_REQUEST' ) ) {
			return;
		}

		if ( ( isset( $_GET['action'] ) && 'upgrade-plugin' === $_GET['action'] ) && ( isset( $_GET['plugin'] ) && strstr( $_GET['plugin'], 'wp-job-manager.php' ) ) ) {
			return;
		}

		wp_redirect( admin_url( 'index.php?page=job-manager-setup' ) );
		exit;
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

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$enable = isset( $_POST['job_manager_usage_tracking_enabled'] )
				&& '1' === $_POST['job_manager_usage_tracking_enabled'];

			$nonce       = isset( $_POST['nonce'] ) ? $_POST['nonce'] : null;
			$valid_nonce = wp_verify_nonce( $nonce, 'enable-usage-tracking' );

			if ( $valid_nonce ) {
				$usage_tracking->set_tracking_enabled( $enable );
				$usage_tracking->hide_tracking_opt_in();
			}
		}

		$this->output();
	}

	/**
	 * Usage tracking opt in text for setup page.
	 */
	private function opt_in_text() {
		return WP_Job_Manager_Usage_Tracking::get_instance()->opt_in_checkbox_text();
	}

	/**
	 * Output opt-in checkbox if usage tracking isn't already enabled.
	 */
	private function maybe_output_opt_in_checkbox() {
		// Only show the checkbox if we aren't already opted in.
		$usage_tracking = WP_Job_Manager_Usage_Tracking::get_instance();
		if ( ! $usage_tracking->get_tracking_enabled() ) {
			?>
			<p>
				<label>
					<input
						type="checkbox"
						name="job_manager_usage_tracking_enabled"
						value="1" />
					<?php
					echo wp_kses(
						$this->opt_in_text(),
						$usage_tracking->opt_in_dialog_text_allowed_html()
					);
					?>
				</label>
			</p>
			<?php
		}
	}

	/**
	 * Displays setup page.
	 */
	public function output() {
		$step = ! empty( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;

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
