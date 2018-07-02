<?php
/**
 * Addons Page.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles the admin add-ons page.
 *
 * @package wp-job-manager
 * @since 1.1.0
 */
class WP_Job_Manager_Addons {
	const WPJM_COM_PRODUCTS_API_BASE_URL = 'https://wpjobmanager.com/wp-json/wpjmcom-products/1.0';

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
	 * Call API to get WPJM add-ons
	 *
	 * @since  1.30.0
	 *
	 * @param  string $category
	 *
	 * @return array of add-ons.
	 */
	private function get_add_ons( $category = null ) {
		$raw_add_ons = wp_remote_get(
			add_query_arg( array( array( 'category' => $category ) ), self::WPJM_COM_PRODUCTS_API_BASE_URL . '/search' )
		);
		if ( ! is_wp_error( $raw_add_ons ) ) {
			$add_ons = json_decode( wp_remote_retrieve_body( $raw_add_ons ) )->products;
		}
		return $add_ons;
	}

	/**
	 * Get categories for the add-ons screen
	 *
	 * @since  1.30.0
	 *
	 * @return array of objects.
	 */
	private function get_categories() {
		$add_on_categories = get_transient( 'jm_wpjmcom_add_on_categories' );
		if ( false === ( $add_on_categories ) ) {
			$raw_categories = wp_safe_remote_get( self::WPJM_COM_PRODUCTS_API_BASE_URL . '/categories' );
			if ( ! is_wp_error( $raw_categories ) ) {
				$add_on_categories = json_decode( wp_remote_retrieve_body( $raw_categories ) );
				if ( $add_on_categories ) {
					set_transient( 'jm_wpjmcom_add_on_categories', $add_on_categories, WEEK_IN_SECONDS );
				}
			}
		}
		return apply_filters( 'job_manager_add_on_categories', $add_on_categories );
	}

	/**
	 * Get messages for the add-ons screen
	 *
	 * @since  1.30.0
	 *
	 * @return array of objects.
	 */
	private function get_messages() {
		$add_on_messages = get_transient( 'jm_wpjmcom_add_on_messages' );
		if ( false === ( $add_on_messages ) ) {
			$raw_messages = wp_safe_remote_get(
				add_query_arg(
					array(
						'version' => JOB_MANAGER_VERSION,
						'lang'    => get_locale(),
					), self::WPJM_COM_PRODUCTS_API_BASE_URL . '/messages'
				)
			);
			if ( ! is_wp_error( $raw_messages ) ) {
				$add_on_messages = json_decode( wp_remote_retrieve_body( $raw_messages ) );
				if ( $add_on_messages ) {
					set_transient( 'jm_wpjmcom_add_on_messages', $add_on_messages, WEEK_IN_SECONDS );
				}
			}
		}
		return apply_filters( 'job_manager_add_on_messages', $add_on_messages );
	}

	/**
	 * Handles output of the reports page in admin.
	 */
	public function output() {
		?>
		<div class="wrap wp_job_manager wp_job_manager_add_ons_wrap">
			<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-addons' ) ); ?>" class="nav-tab
									<?php
									if ( ! isset( $_GET['section'] ) || 'helper' !== $_GET['section'] ) {
										echo ' nav-tab-active';
									}
									?>
				"><?php esc_html_e( 'WP Job Manager Add-ons', 'wp-job-manager' ); ?></a>
				<?php if ( current_user_can( 'update_plugins' ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-addons&section=helper' ) ); ?>" class="nav-tab
									<?php
									if ( isset( $_GET['section'] ) && 'helper' === $_GET['section'] ) {
										echo ' nav-tab-active'; }
									?>
				"><?php esc_html_e( 'Licenses', 'wp-job-manager' ); ?></a>
				<?php endif; ?>
			</nav>
			<?php
			if ( isset( $_GET['section'] ) && 'helper' === $_GET['section'] ) {
				do_action( 'job_manager_helper_output' );
			} else {
				$category   = isset( $_GET['category'] ) ? sanitize_text_field( $_GET['category'] ) : null;
				$messages   = $this->get_messages();
				$categories = $this->get_categories();
				$add_ons    = $this->get_add_ons( $category );
				include_once dirname( __FILE__ ) . '/views/html-admin-page-addons.php';
			}
			?>
		</div>
		<?php
	}
}

return WP_Job_Manager_Addons::instance();
