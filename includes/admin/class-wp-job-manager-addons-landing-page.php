<?php
/**
 * File WP_Job_Manager_Addons_Landing_Page class.
 *
 * @package wp-job-manager
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Landing pages for Applications and Resumes add-ons.
 *
 * @since 2.0.0
 */
class WP_Job_Manager_Addons_Landing_Page {

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  2.0.0
	 */
	private static $instance = null;

	/**
	 * Singleton accessor.
	 *
	 * @since  2.0.0
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize class for landing pages.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_applications_menu_item' ], 12 );
		add_action( 'admin_menu', [ $this, 'add_resumes_menu_item' ], 12 );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_styles' ] );
	}

	/**
	 * Register styles for landing pages.
	 *
	 * @access private
	 * @since 2.0.0
	 */
	public function register_styles() {
		WP_Job_Manager::register_style( 'job_manager_admin_landing_css', 'css/admin-landing.css', [ 'job_manager_brand' ] );
	}

	/**
	 * Add Applications add-on menu item if needed.
	 *
	 * @access private
	 * @since 2.0.0
	 */
	public function add_applications_menu_item() {
		$was_addon_installed = (bool) get_option( 'wp_job_manager_applications_version', false );
		$was_page_dismissed  = (bool) get_option( 'job_manager_addon_upsell_applications', false );

		/**
		 * Filters whether the 'Applications' landing page should be added to the Job Manager menu.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $show True if the menu should be added.
		 */
		if ( apply_filters( 'job_manager_addon_upsell_applications', ! $was_addon_installed && ! $was_page_dismissed ) ) {

			add_submenu_page(
				'edit.php?post_type=job_listing',
				__( 'WP Job Manager - Applications', 'wp-job-manager' ),
				sprintf(
					'<div class="wpjm-addon-upsell">%s <span class="wpjm-addon-upsell__badge">%s</span></div>',
					esc_html__( 'Applications', 'wp-job-manager' ),
					esc_html__( 'Pro', 'wp-job-manager' )
				),
				'manage_options',
				'job-manager-landing-application',
				[ $this, 'applications_landing_page' ]
			);
		}
	}

	/**
	 * Add Resumes add-on menu item if needed.
	 *
	 * @access private
	 * @since 2.0.0
	 */
	public function add_resumes_menu_item() {
		$was_addon_installed = ! ! get_option( 'wp_resume_manager_version', false );
		$was_page_dismissed  = ! ! get_option( 'job_manager_addon_upsell_resumes', false );

		/**
		 * Filters whether the 'Resumes' landing page should be added to the Job Manager menu.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $show True if the menu should be added.
		 */
		if ( apply_filters( 'job_manager_addon_upsell_resumes', ! $was_addon_installed && ! $was_page_dismissed ) ) {

			add_submenu_page(
				'edit.php?post_type=job_listing',
				__( 'WP Job Manager - Resumes', 'wp-job-manager' ),
				sprintf(
					'<div class="wpjm-addon-upsell">%s <span class="wpjm-addon-upsell__badge">%s</span></div>',
					esc_html__( 'Resumes', 'wp-job-manager' ),
					esc_html__( 'Pro', 'wp-job-manager' )
				),
				'manage_options',
				'job-manager-landing-resumes',
				[ $this, 'resumes_landing_page' ]
			);
		}
	}

	/**
	 * Render Applications landing page.
	 *
	 * @access private
	 * @since 2.0.0
	 */
	public function applications_landing_page() {

		wp_enqueue_style( 'job_manager_admin_landing_css' );

		$dismiss_action = 'dismiss_applications_landing_page';
		$link           = 'https://wpjobmanager.com/add-ons/applications/?utm_source=plugin_wp-job-manager&utm_medium=upsell&utm_campaign=applications';

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce check.
		if ( isset( $_GET[ $dismiss_action ] ) && wp_verify_nonce( $_GET['_wpnonce'] ?? null, $dismiss_action ) ) {
			update_option( 'job_manager_addon_upsell_applications', 'hide' );
			wp_safe_redirect( admin_url( 'edit.php?post_type=job_listing' ) );
			exit;
		}

		?>
		<div class="wrap">
			<div class="wpjm-landing__heading">
				<h1 class="wp-heading-inline">
					<?php esc_html_e( 'Applications', 'wp-job-manager' ); ?>
				</h1>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( $dismiss_action, 'true' ), $dismiss_action ) ); ?>">
					<?php esc_html_e( 'Dismiss', 'wp-job-manager' ); ?>
				</a>
			</div>
			<div class="wpjm-landing">
				<div class="wpjm-landing__left">
					<img class="wpjm-landing__addon-logo"
						alt="<?php esc_html_e( 'Applications add-on logo', 'wp-job-manager' ); ?>"
						src="https://wpjobmanager.com/wp-content/uploads/2014/07/Applications.png" />
					<div class="wpjm-landing__content">
						<h2 class="wpjm-landing__title">
							<?php esc_html_e( 'Applications', 'wp-job-manager' ); ?>
						</h2>
						<h2 class="wpjm-landing__subtitle">
							<?php esc_html_e( '$79.00 USD / year (one site)', 'wp-job-manager' ); ?>
						</h2>
						<p>
							<?php esc_html_e( 'Allow candidates to apply to jobs using a form &amp; employers to view and manage the applications from their job dashboard.', 'wp-job-manager' ); ?>
						</p>
						<ul class="wpjm-list-checkmarks">
							<li>
								<?php esc_html_e( 'Apply to jobs via forms and save applications', 'wp-job-manager' ); ?>
							</li>
							<li>
								<?php esc_html_e( 'List applications in the employer job and admin dashboard', 'wp-job-manager' ); ?>
							</li>
							<li>
								<?php esc_html_e( 'Privately rate and comment on applications', 'wp-job-manager' ); ?>
							</li>
						</ul>

					</div>

					<div class="wpjm-landing__buttons">
						<a class="wpjm-button"
							target="_blank"
							href="<?php echo esc_url( $link ); ?>">
							<?php esc_html_e( 'Get Applications', 'wp-job-manager' ); ?>
						</a>
						<a class="wpjm-button is-link"
							target="_blank"
							href="<?php echo esc_url( $link ); ?>">
							<?php esc_html_e( 'See all features', 'wp-job-manager' ); ?>
						</a>
					</div>
				</div>

				<div class="wpjm-landing__right">
					<img class="wpjm-landing__visual"
						src="https://wpjobmanager.com/wp-content/uploads/2023/10/Applications-upsell.png" />
				</div>
			</div>
		</div>
		<?php

	}

	/**
	 * Render Resume Manager landing page.
	 *
	 * @access private
	 * @since 2.0.0
	 */
	public function resumes_landing_page() {

		wp_enqueue_style( 'job_manager_admin_landing_css' );

		$dismiss_action = 'dismiss_resumes_landing_page';
		$link           = 'https://wpjobmanager.com/add-ons/resume-manager/?utm_source=plugin_wp-job-manager&utm_medium=upsell&utm_campaign=resumes';

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce check.
		if ( isset( $_GET[ $dismiss_action ] ) && wp_verify_nonce( $_GET['_wpnonce'] ?? null, $dismiss_action ) ) {
			update_option( 'job_manager_addon_upsell_resumes', 'hide' );
			wp_safe_redirect( admin_url( 'edit.php?post_type=job_listing' ) );
			exit;
		}

		?>
		<div class="wrap">
			<div class="wpjm-landing__heading">
				<h1 class="wp-heading-inline">
					<?php esc_html_e( 'Resume Manager', 'wp-job-manager' ); ?>
				</h1>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( $dismiss_action, 'true' ), $dismiss_action ) ); ?>">
					<?php esc_html_e( 'Dismiss', 'wp-job-manager' ); ?>
				</a>
			</div>
			<div class="wpjm-landing">
				<div class="wpjm-landing__left">
					<img class="wpjm-landing__addon-logo"
						alt="<?php esc_html_e( 'Resume Manager add-on logo', 'wp-job-manager' ); ?>"
						src="https://wpjobmanager.com/wp-content/uploads/2014/02/Resumes.png" />
					<div class="wpjm-landing__content">
						<h2 class="wpjm-landing__title">
							<?php esc_html_e( 'Resume Manager', 'wp-job-manager' ); ?>
						</h2>
						<h2 class="wpjm-landing__subtitle">
							<?php esc_html_e( '$49.00 USD / year (one site)', 'wp-job-manager' ); ?>
						</h2>
						<p>
							<?php esc_html_e( 'Resume Manager is a plugin built on top of WP Job Manager which adds a resume submission form to your site and resume listings, all manageable from WordPress admin.', 'wp-job-manager' ); ?>
						</p>
						<ul class="wpjm-list-checkmarks">
							<li>
								<?php esc_html_e( 'Post resumes to your site and apply to jobs with the resumes', 'wp-job-manager' ); ?>
							</li>
							<li>
								<?php esc_html_e( 'List resumes, restricting access to certain user roles if you wish', 'wp-job-manager' ); ?>
							</li>
							<li>
								<?php esc_html_e( 'Restrict which user roles can view candidate contact details', 'wp-job-manager' ); ?>
							</li>
						</ul>

					</div>

					<div class="wpjm-landing__buttons">
						<a class="wpjm-button"
							target="_blank"
							href="<?php echo esc_url( $link ); ?>">
							<?php esc_html_e( 'Get Resume Manager', 'wp-job-manager' ); ?>
						</a>
						<a class="wpjm-button is-link"
							target="_blank"
							href="<?php echo esc_url( $link ); ?>">
							<?php esc_html_e( 'See all features', 'wp-job-manager' ); ?>
						</a>
					</div>
				</div>

				<div class="wpjm-landing__right">
					<img class="wpjm-landing__visual"
						src="https://wpjobmanager.com/wp-content/uploads/2023/10/ResumeManager-upsell.png" />
				</div>
			</div>
		</div>
		<?php
	}


}
