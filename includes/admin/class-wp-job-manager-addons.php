<?php
/**
 * Addons Page
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'WP_Job_Manager_Addons' ) ) :

/**
 * Handles the admin add-ons page.
 *
 * @package wp-job-manager
 * @since 1.1.0
 */
class WP_Job_Manager_Addons {

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
	 * Handles output of the reports page in admin.
	 */
	public function output() {

		if ( false === ( $addons = get_transient( 'wp_job_manager_addons_html' ) ) ) {

			$raw_addons = wp_remote_get(
				'https://wpjobmanager.com/add-ons/',
				array(
					'timeout'     => 10,
					'redirection' => 5,
					'sslverify'   => false
				)
			);

			if ( ! is_wp_error( $raw_addons ) ) {

				$raw_addons = wp_remote_retrieve_body( $raw_addons );

				// Get Products
				$dom = new DOMDocument();
				libxml_use_internal_errors(true);
				$dom->loadHTML( $raw_addons );

				$xpath  = new DOMXPath( $dom );
				$tags   = $xpath->query('//ul[@class="products"]');
				foreach ( $tags as $tag ) {
					$addons = $tag->ownerDocument->saveXML( $tag );
					break;
				}

				$addons = wp_kses_post( $addons );

				if ( $addons ) {
					set_transient( 'wp_job_manager_addons_html', $addons, 60*60*24*7 ); // Cached for a week
				}
			}
		}

		?>
		<div class="wrap wp_job_manager wp_job_manager_addons_wrap">
			<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-addons' ) ); ?>" class="nav-tab<?php if ( ! isset( $_GET['section'] ) || 'helper' !== $_GET['section'] ) { echo ' nav-tab-active'; } ?>"><?php _e( 'WP Job Manager Add-ons', 'wp-job-manager' ); ?></a>
				<?php if ( current_user_can( 'update_plugins' ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-addons&section=helper' ) ); ?>" class="nav-tab<?php if ( isset( $_GET['section'] ) && 'helper' === $_GET['section'] ) { echo ' nav-tab-active'; } ?>"><?php _e( 'Licenses', 'wp-job-manager' ); ?></a>
				<?php endif; ?>
			</nav>
			<?php
			if ( isset( $_GET['section'] ) && 'helper' === $_GET['section'] ) {
				do_action( 'job_manager_helper_output' );
			} else {
				echo '<h1 class="screen-reader-text">' . __( 'WP Job Manager Add-ons', 'wp-job-manager' ) . '</h1>';
				echo '<div id="job-manager-addons-banner" class="notice updated below-h2"><strong>' . __( 'Do you need multiple add-ons?', 'wp-job-manager' ) . '</strong> <a href="https://wpjobmanager.com/add-ons/bundle/" class="button">' . __( 'Check out the core add-on bundle &rarr;', 'wp-job-manager' ) . '</a></div>';
				echo $addons;
			}
			?>
		</div>
		<?php
	}
}

endif;

return WP_Job_Manager_Addons::instance();
