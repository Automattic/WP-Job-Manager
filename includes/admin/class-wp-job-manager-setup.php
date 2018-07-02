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
		?>
		<div class="wrap wp_job_manager wp_job_manager_addons_wrap">
			<h2><?php esc_html_e( 'WP Job Manager Setup', 'wp-job-manager' ); ?></h2>

			<ul class="wp-job-manager-setup-steps">
				<?php
					$step_classes          = array_fill( 1, 3, '' );
					$step_classes[ $step ] = 'wp-job-manager-setup-active-step';
				?>
				<li class="<?php echo sanitize_html_class( $step_classes[1] ); ?>"><?php esc_html_e( '1. Introduction', 'wp-job-manager' ); ?></li>
				<li class="<?php echo sanitize_html_class( $step_classes[2] ); ?>"><?php esc_html_e( '2. Page Setup', 'wp-job-manager' ); ?></li>
				<li class="<?php echo sanitize_html_class( $step_classes[3] ); ?>"><?php esc_html_e( '3. Done', 'wp-job-manager' ); ?></li>
			</ul>

			<?php if ( 1 === $step ) : ?>

				<h3><?php esc_html_e( 'Welcome to the Setup Wizard!', 'wp-job-manager' ); ?></h3>

				<p><?php echo wp_kses_post( __( 'Thanks for installing <em>WP Job Manager</em>! Let\'s get your site ready to accept job listings.', 'wp-job-manager' ) ); ?></p>
				<p><?php echo wp_kses_post( __( 'This setup wizard will walk you through the process of creating pages for job submissions, management, and listings.', 'wp-job-manager' ) ); ?></p>
				<p>
					<?php
					// translators: Placeholder %s is the path to WPJM documentation site.
					echo wp_kses_post( sprintf( __( 'If you\'d prefer to skip this and set up your pages manually, our <a href="%s">documentation</a> will walk you through each step.', 'wp-job-manager' ), 'https://wpjobmanager.com/documentation/' ) );
					?>
				</p>

				<form method="post" action="<?php echo esc_url( add_query_arg( 'step', 2 ) ); ?>">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'enable-usage-tracking' ) ); ?>" />

					<?php $this->maybe_output_opt_in_checkbox(); ?>

					<p class="submit">
						<input type="submit" value="<?php esc_html_e( 'Start setup', 'wp-job-manager' ); ?>" class="button button-primary" />
						<a href="<?php echo esc_url( add_query_arg( 'skip-job-manager-setup', 1, admin_url( 'index.php?page=job-manager-setup&step=3' ) ) ); ?>" class="button"><?php esc_html_e( 'Skip setup. I will set up the plugin manually.', 'wp-job-manager' ); ?></a>
					</p>
				</form>

			<?php endif; ?>
			<?php if ( 2 === $step ) : ?>

				<h3><?php esc_html_e( 'Page Setup', 'wp-job-manager' ); ?></h3>

				<p><?php esc_html_e( 'With WP Job Manager, employers and applicants can post, manage, and browse job listings right on your website. Tell us which of these common pages you\'d like your site to have and we\'ll create and configure them for you.', 'wp-job-manager' ); ?></p>
				<p>
					<?php
					echo wp_kses_post( sprintf(
						// translators: %1$s is URL to WordPress core shortcode documentation. %2$s is URL to WPJM specific shortcode reference.
						__( '(These pages are created using <a href="%1$s" title="What is a shortcode?" target="_blank" class="help-page-link">shortcodes</a>, 
								which we take care of in this step. If you\'d like to build these pages yourself or want to add one of these options to an existing 
								page on your site, you can skip this step and take a look at <a href="%2$s" target="_blank" class="help-page-link">shortcode documentation</a> for detailed instructions.)', 'wp-job-manager' ),
						'http://codex.wordpress.org/Shortcode',
						'https://wpjobmanager.com/document/shortcode-reference/'
					) );
					?>
				</p>

				<form action="<?php echo esc_url( add_query_arg( 'step', 3 ) ); ?>" method="post">
				<?php wp_nonce_field( 'step_3', 'setup_wizard' ); ?>
					<table class="wp-job-manager-shortcodes widefat">
						<thead>
							<tr>
								<th>&nbsp;</th>
								<th><?php esc_html_e( 'Page Title', 'wp-job-manager' ); ?></th>
								<th><?php esc_html_e( 'Page Description', 'wp-job-manager' ); ?></th>
								<th><?php esc_html_e( 'Content Shortcode', 'wp-job-manager' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><input type="checkbox" checked="checked" name="wp-job-manager-create-page[submit_job_form]" /></td>
								<td><input type="text" value="<?php echo esc_attr( _x( 'Post a Job', 'Default page title (wizard)', 'wp-job-manager' ) ); ?>" name="wp-job-manager-page-title[submit_job_form]" /></td>
								<td>
									<p><?php esc_html_e( 'Creates a page that allows employers to post new jobs directly from a page on your website, instead of requiring them to log in to an admin area. If you\'d rather not allow this -- for example, if you want employers to use the admin dashboard only -- you can uncheck this setting.', 'wp-job-manager' ); ?></p>
								</td>
								<td><code>[submit_job_form]</code></td>
							</tr>
							<tr>
								<td><input type="checkbox" checked="checked" name="wp-job-manager-create-page[job_dashboard]" /></td>
								<td><input type="text" value="<?php echo esc_attr( _x( 'Job Dashboard', 'Default page title (wizard)', 'wp-job-manager' ) ); ?>" name="wp-job-manager-page-title[job_dashboard]" /></td>
								<td>
									<p><?php esc_html_e( 'Creates a page that allows employers to manage their job listings directly from a page on your website, instead of requiring them to log in to an admin area. If you want to manage all job listings from the admin dashboard only, you can uncheck this setting.', 'wp-job-manager' ); ?></p>
								</td>
								<td><code>[job_dashboard]</code></td>
							</tr>
							<tr>
								<td><input type="checkbox" checked="checked" name="wp-job-manager-create-page[jobs]" /></td>
								<td><input type="text" value="<?php echo esc_attr( _x( 'Jobs', 'Default page title (wizard)', 'wp-job-manager' ) ); ?>" name="wp-job-manager-page-title[jobs]" /></td>
								<td><?php esc_html_e( 'Creates a page where visitors can browse, search, and filter job listings.', 'wp-job-manager' ); ?></td>
								<td><code>[jobs]</code></td>
							</tr>
						</tbody>
						<tfoot>
							<tr>
								<th colspan="4">
									<input type="submit" class="button button-primary" value="Create selected pages" />
									<a href="<?php echo esc_url( add_query_arg( 'step', 3 ) ); ?>" class="button"><?php esc_html_e( 'Skip this step', 'wp-job-manager' ); ?></a>
								</th>
							</tr>
						</tfoot>
					</table>
				</form>

			<?php endif; ?>
			<?php if ( 3 === $step ) : ?>

				<h3><?php esc_html_e( 'You\'re ready to start using WP Job Manager!', 'wp-job-manager' ); ?></h3>

				<p><?php esc_html_e( 'Wondering what to do now? Here are some of the most common next steps:', 'wp-job-manager' ); ?></p>

				<ul class="wp-job-manager-next-steps">
					<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-settings' ) ); ?>"><?php esc_html_e( 'Tweak your settings', 'wp-job-manager' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=job_listing' ) ); ?>"><?php esc_html_e( 'Add a job using the admin dashboard', 'wp-job-manager' ); ?></a></li>
					<?php
					$permalink = job_manager_get_permalink( 'jobs' );
					if ( $permalink ) {
						?>
						<li><a href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'View submitted job listings', 'wp-job-manager' ); ?></a></li>
					<?php } else { ?>
						<li><a href="https://wpjobmanager.com/document/shortcode-reference/#section-1"><?php esc_html_e( 'Add job listings to a page using the [jobs] shortcode', 'wp-job-manager' ); ?></a></li>
					<?php } ?>

					<?php
					$permalink = job_manager_get_permalink( 'submit_job_form' );
					if ( $permalink ) {
						?>
						<li><a href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'Add a job via the front-end', 'wp-job-manager' ); ?></a></li>
					<?php } else { ?>
						<li><a href="https://wpjobmanager.com/document/the-job-submission-form/"><?php esc_html_e( 'Learn to use the front-end job submission board', 'wp-job-manager' ); ?></a></li>
					<?php } ?>

					<?php
					$permalink = job_manager_get_permalink( 'job_dashboard' );
					if ( $permalink ) {
						?>
						<li><a href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'View the job dashboard', 'wp-job-manager' ); ?></a></li>
					<?php } else { ?>
						<li><a href="https://wpjobmanager.com/document/the-job-dashboard/"><?php esc_html_e( 'Learn to use the front-end job dashboard', 'wp-job-manager' ); ?></a></li>
					<?php } ?>
				</ul>

				<p>
					<?php
					echo wp_kses_post(
						sprintf(
							// translators: %1$s is the URL to WPJM support documentation; %2$s is the URL to WPJM support forums.
							__( 'If you need help, you can find more detail in our 
							<a href="%1$s">support documentation</a> or post your question on the
							<a href="%2$s">WP Job Manager support forums</a>. Happy hiring!', 'wp-job-manager' ),
							'https://wpjobmanager.com/documentation/',
							'https://wordpress.org/support/plugin/wp-job-manager'
						)
					);
					?>
				</p>

				<div class="wp-job-manager-support-the-plugin">
					<h3><?php esc_html_e( 'Support WP Job Manager\'s Ongoing Development', 'wp-job-manager' ); ?></h3>
					<p><?php esc_html_e( 'There are lots of ways you can support open source software projects like this one: contributing code, fixing a bug, assisting with non-English translation, or just telling your friends about WP Job Manager to help spread the word. We appreciate your support!', 'wp-job-manager' ); ?></p>
					<ul>
						<li class="icon-review"><a href="https://wordpress.org/support/view/plugin-reviews/wp-job-manager#postform"><?php esc_html_e( 'Leave a positive review', 'wp-job-manager' ); ?></a></li>
						<li class="icon-localization"><a href="https://translate.wordpress.org/projects/wp-plugins/wp-job-manager"><?php esc_html_e( 'Contribute a localization', 'wp-job-manager' ); ?></a></li>
						<li class="icon-code"><a href="https://github.com/mikejolley/WP-Job-Manager"><?php esc_html_e( 'Contribute code or report a bug', 'wp-job-manager' ); ?></a></li>
						<li class="icon-forum"><a href="https://wordpress.org/support/plugin/wp-job-manager"><?php esc_html_e( 'Help other users on the forums', 'wp-job-manager' ); ?></a></li>
					</ul>
				</div>

			<?php endif; ?>
		</div>
		<?php
	}
}

WP_Job_Manager_Setup::instance();
