<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Setup class.
 */
class WP_Job_Manager_Setup {

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 12 );
		add_action( 'admin_head', array( $this, 'admin_head' ) );
		add_action( 'admin_init', array( $this, 'redirect' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 12 );
	}

	/**
	 * admin_menu function.
	 *
	 * @access public
	 * @return void
	 */
	public function admin_menu() {
		add_dashboard_page( __( 'Setup', 'wp-job-manager' ), __( 'Setup', 'wp-job-manager' ), 'manage_options', 'job-manager-setup', array( $this, 'output' ) );
	}

	/**
	 * Add styles just for this page, and remove dashboard page links.
	 *
	 * @access public
	 * @return void
	 */
	public function admin_head() {
		remove_submenu_page( 'index.php', 'job-manager-setup' );
	}

	/**
	 * Sends user to the setup page on first activation
	 */
	public function redirect() {
		// Bail if no activation redirect transient is set
	    if ( ! get_transient( '_job_manager_activation_redirect' ) ) {
			return;
	    }

	    if ( ! current_user_can( 'manage_options' ) ) {
	    	return;
	    }

		// Delete the redirect transient
		delete_transient( '_job_manager_activation_redirect' );

		// Bail if activating from network, or bulk, or within an iFrame
		if ( is_network_admin() || isset( $_GET['activate-multi'] ) || defined( 'IFRAME_REQUEST' ) ) {
			return;
		}

		if ( ( isset( $_GET['action'] ) && 'upgrade-plugin' == $_GET['action'] ) && ( isset( $_GET['plugin'] ) && strstr( $_GET['plugin'], 'wp-job-manager.php' ) ) ) {
			return;
		}

		wp_redirect( admin_url( 'index.php?page=job-manager-setup' ) );
		exit;
	}

	/**
	 * Enqueue scripts for setup page
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_style( 'job_manager_setup_css', JOB_MANAGER_PLUGIN_URL . '/assets/css/setup.css', array( 'dashicons' ) );
	}

	/**
	 * Create a page.
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
			'comment_status' => 'closed'
		);
		$page_id = wp_insert_post( $page_data );

		if ( $option ) {
			update_option( $option, $page_id );
		}
	}

	/**
	 * Output addons page
	 */
	public function output() {
		$step = ! empty( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;

		if ( 3 === $step && ! empty( $_POST ) ) {
			$create_pages    = isset( $_POST['wp-job-manager-create-page'] ) ? $_POST['wp-job-manager-create-page'] : array();
			$page_titles     = $_POST['wp-job-manager-page-title'];
			$pages_to_create = array(
				'submit_job_form' => '[submit_job_form]',
				'job_dashboard'   => '[job_dashboard]',
				'jobs'            => '[jobs]'
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
			<h2><?php _e( 'WP Job Manager Setup', 'wp-job-manager' ); ?></h2>

			<ul class="wp-job-manager-setup-steps">
				<li class="<?php if ( $step === 1 ) echo 'wp-job-manager-setup-active-step'; ?>"><?php _e( '1. Introduction', 'wp-job-manager' ); ?></li>
				<li class="<?php if ( $step === 2 ) echo 'wp-job-manager-setup-active-step'; ?>"><?php _e( '2. Page Setup', 'wp-job-manager' ); ?></li>
				<li class="<?php if ( $step === 3 ) echo 'wp-job-manager-setup-active-step'; ?>"><?php _e( '3. Done', 'wp-job-manager' ); ?></li>
			</ul>

			<?php if ( 1 === $step ) : ?>

				<h3><?php _e( 'Setup Wizard Introduction', 'wp-job-manager' ); ?></h3>

				<p><?php _e( 'Thanks for installing <em>WP Job Manager</em>!', 'wp-job-manager' ); ?></p>
				<p><?php _e( 'This setup wizard will help you get started by creating the pages for job submission, job management, and listing your jobs.', 'wp-job-manager' ); ?></p>
				<p><?php printf( __( 'If you want to skip the wizard and setup the pages and shortcodes yourself manually, the process is still relatively simple. Refer to the %sdocumentation%s for help.', 'wp-job-manager' ), '<a href="https://wpjobmanager.com/documentation/">', '</a>' ); ?></p>

				<p class="submit">
					<a href="<?php echo esc_url( add_query_arg( 'step', 2 ) ); ?>" class="button button-primary"><?php _e( 'Continue to page setup', 'wp-job-manager' ); ?></a>
					<a href="<?php echo esc_url( add_query_arg( 'skip-job-manager-setup', 1, admin_url( 'index.php?page=job-manager-setup&step=3' ) ) ); ?>" class="button"><?php _e( 'Skip setup. I will setup the plugin manually', 'wp-job-manager' ); ?></a>
				</p>

			<?php endif; ?>
			<?php if ( 2 === $step ) : ?>

				<h3><?php _e( 'Page Setup', 'wp-job-manager' ); ?></h3>

				<p><?php printf( __( '<em>WP Job Manager</em> includes %1$sshortcodes%2$s which can be used within your %3$spages%2$s to output content. These can be created for you below. For more information on the job shortcodes view the %4$sshortcode documentation%2$s.', 'wp-job-manager' ), '<a href="http://codex.wordpress.org/Shortcode" title="What is a shortcode?" target="_blank" class="help-page-link">', '</a>', '<a href="http://codex.wordpress.org/Pages" target="_blank" class="help-page-link">', '<a href="https://wpjobmanager.com/document/shortcode-reference/" target="_blank" class="help-page-link">' ); ?></p>

				<form action="<?php echo esc_url( add_query_arg( 'step', 3 ) ); ?>" method="post">
					<table class="wp-job-manager-shortcodes widefat">
						<thead>
							<tr>
								<th>&nbsp;</th>
								<th><?php _e( 'Page Title', 'wp-job-manager' ); ?></th>
								<th><?php _e( 'Page Description', 'wp-job-manager' ); ?></th>
								<th><?php _e( 'Content Shortcode', 'wp-job-manager' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><input type="checkbox" checked="checked" name="wp-job-manager-create-page[submit_job_form]" /></td>
								<td><input type="text" value="<?php echo esc_attr( _x( 'Post a Job', 'Default page title (wizard)', 'wp-job-manager' ) ); ?>" name="wp-job-manager-page-title[submit_job_form]" /></td>
								<td>
									<p><?php _e( 'This page allows employers to post jobs to your website from the front-end.', 'wp-job-manager' ); ?></p>

									<p><?php _e( 'If you do not want to accept submissions from users in this way (for example you just want to post jobs from the admin dashboard) you can skip creating this page.', 'wp-job-manager' ); ?></p>
								</td>
								<td><code>[submit_job_form]</code></td>
							</tr>
							<tr>
								<td><input type="checkbox" checked="checked" name="wp-job-manager-create-page[job_dashboard]" /></td>
								<td><input type="text" value="<?php echo esc_attr( _x( 'Job Dashboard', 'Default page title (wizard)', 'wp-job-manager' ) ); ?>" name="wp-job-manager-page-title[job_dashboard]" /></td>
								<td>
									<p><?php _e( 'This page allows employers to manage and edit their own jobs from the front-end.', 'wp-job-manager' ); ?></p>

									<p><?php _e( 'If you plan on managing all listings from the admin dashboard you can skip creating this page.', 'wp-job-manager' ); ?></p>
								</td>
								<td><code>[job_dashboard]</code></td>
							</tr>
							<tr>
								<td><input type="checkbox" checked="checked" name="wp-job-manager-create-page[jobs]" /></td>
								<td><input type="text" value="<?php echo esc_attr( _x( 'Jobs', 'Default page title (wizard)', 'wp-job-manager' ) ); ?>" name="wp-job-manager-page-title[jobs]" /></td>
								<td><?php _e( 'This page allows users to browse, search, and filter job listings on the front-end of your site.', 'wp-job-manager' ); ?></td>
								<td><code>[jobs]</code></td>
							</tr>
						</tbody>
						<tfoot>
							<tr>
								<th colspan="4">
									<input type="submit" class="button button-primary" value="Create selected pages" />
									<a href="<?php echo esc_url( add_query_arg( 'step', 3 ) ); ?>" class="button"><?php _e( 'Skip this step', 'wp-job-manager' ); ?></a>
								</th>
							</tr>
						</tfoot>
					</table>
				</form>

			<?php endif; ?>
			<?php if ( 3 === $step ) : ?>

				<h3><?php _e( 'All Done!', 'wp-job-manager' ); ?></h3>

				<p><?php _e( 'Looks like you\'re all set to start using the plugin. In case you\'re wondering where to go next:', 'wp-job-manager' ); ?></p>

				<ul class="wp-job-manager-next-steps">
					<li><a href="<?php echo admin_url( 'edit.php?post_type=job_listing&page=job-manager-settings' ); ?>"><?php _e( 'Tweak the plugin settings', 'wp-job-manager' ); ?></a></li>
					<li><a href="<?php echo admin_url( 'post-new.php?post_type=job_listing' ); ?>"><?php _e( 'Add a job via the back-end', 'wp-job-manager' ); ?></a></li>

					<?php if ( $permalink = job_manager_get_permalink( 'submit_job_form' ) ) : ?>
						<li><a href="<?php echo esc_url( $permalink ); ?>"><?php _e( 'Add a job via the front-end', 'wp-job-manager' ); ?></a></li>
					<?php else : ?>
						<li><a href="https://wpjobmanager.com/document/the-job-submission-form/"><?php _e( 'Find out more about the front-end job submission form', 'wp-job-manager' ); ?></a></li>
					<?php endif; ?>

					<?php if ( $permalink = job_manager_get_permalink( 'jobs' ) ) : ?>
						<li><a href="<?php echo esc_url( $permalink ); ?>"><?php _e( 'View submitted job listings', 'wp-job-manager' ); ?></a></li>
					<?php else : ?>
						<li><a href="https://wpjobmanager.com/document/shortcode-reference/#section-1"><?php _e( 'Add the [jobs] shortcode to a page to list jobs', 'wp-job-manager' ); ?></a></li>
					<?php endif; ?>

					<?php if ( $permalink = job_manager_get_permalink( 'job_dashboard' ) ) : ?>
						<li><a href="<?php echo esc_url( $permalink ); ?>"><?php _e( 'View the job dashboard', 'wp-job-manager' ); ?></a></li>
					<?php else : ?>
						<li><a href="https://wpjobmanager.com/document/the-job-dashboard/"><?php _e( 'Find out more about the front-end job dashboard', 'wp-job-manager' ); ?></a></li>
					<?php endif; ?>
				</ul>

				<p><?php printf( __( 'And don\'t forget, if you need any more help using <em>WP Job Manager</em> you can consult the %1$sdocumentation%2$s or %3$spost on the forums%2$s!', 'wp-job-manager' ), '<a href="https://wpjobmanager.com/documentation/">', '</a>', '<a href="https://wordpress.org/support/plugin/wp-job-manager">' ); ?></p>

				<div class="wp-job-manager-support-the-plugin">
					<h3><?php _e( 'Support the Ongoing Development of this Plugin', 'wp-job-manager' ); ?></h3>
					<p><?php _e( 'There are many ways to support open-source projects such as WP Job Manager, for example code contribution, translation, or even telling your friends how awesome the plugin (hopefully) is. Thanks in advance for your support - it is much appreciated!', 'wp-job-manager' ); ?></p>
					<ul>
						<li class="icon-review"><a href="https://wordpress.org/support/view/plugin-reviews/wp-job-manager#postform"><?php _e( 'Leave a positive review', 'wp-job-manager' ); ?></a></li>
						<li class="icon-localization"><a href="https://www.transifex.com/projects/p/wp-job-manager/"><?php _e( 'Contribute a localization', 'wp-job-manager' ); ?></a></li>
						<li class="icon-code"><a href="https://github.com/mikejolley/WP-Job-Manager"><?php _e( 'Contribute code or report a bug', 'wp-job-manager' ); ?></a></li>
						<li class="icon-forum"><a href="https://wordpress.org/support/plugin/wp-job-manager"><?php _e( 'Help other users on the forums', 'wp-job-manager' ); ?></a></li>
					</ul>
				</div>

			<?php endif; ?>
		</div>
		<?php
	}
}

new WP_Job_Manager_Setup();