<?php
/**
 * File containing the class WP_Job_Manager_Addons.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the admin add-ons page.
 *
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
	private static $instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.26.0
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
	 * Call API to get WPJM add-ons
	 *
	 * @since  1.30.0
	 *
	 * @param string $category Category slug.
	 * @param string $search_term Search term.
	 *
	 * @return array of add-ons.
	 */
	private function get_add_ons( $category = null, $search_term = null ) {
		$cache_key = 'jm_wpjmcom_add_ons_' . md5( wp_json_encode( compact( 'category', 'search_term' ) ) );
		$add_ons   = get_transient( $cache_key );
		if ( false === $add_ons ) {
			if ( ! empty( $search_term ) && ! empty( $category ) ) {
				$raw_add_ons = wp_remote_get(
					add_query_arg(
						[
							[
								'term'     => $search_term,
								'category' => $category,
							],
						],
						self::WPJM_COM_PRODUCTS_API_BASE_URL . '/search'
					)
				);
			} elseif ( ! empty( $search_term ) ) {
				$raw_add_ons = wp_remote_get( add_query_arg( [ [ 'term' => $search_term ] ], self::WPJM_COM_PRODUCTS_API_BASE_URL . '/search' ) );
			} else {
				$raw_add_ons = wp_remote_get( add_query_arg( [ [ 'category' => $category ] ], self::WPJM_COM_PRODUCTS_API_BASE_URL . '/search' ) );
			}

			if ( ! is_wp_error( $raw_add_ons ) && ( 200 === wp_remote_retrieve_response_code( $raw_add_ons ) ) ) {
				$add_ons = json_decode( wp_remote_retrieve_body( $raw_add_ons ) )->products;
				set_transient( $cache_key, $add_ons, HOUR_IN_SECONDS );
			} else {
				$add_ons = [];
			}
		}

		return $add_ons;
	}

	/**
	 * Get product icon for a core add-on.
	 *
	 * @param string $slug Add-on plugin slug or product URL.
	 *
	 * @return string|false
	 */
	public function get_icon( $slug ) {
		$addon = $this->get_add_on_product( $slug );

		return $addon ? remove_query_arg( [ 'w', 'h', 'crop' ], $addon->image ) : false;

	}

	/**
	 * Get product data for a core add-on.
	 *
	 * @param string $slug Add-on plugin slug.
	 *
	 * @return object|false
	 */
	public function get_add_on_product( $slug ) {
		if ( ! $slug ) {
			return false;
		}

		$addons = $this->get_add_ons();

		$slug   = preg_replace( '/^wp-job-manager-/', '', $slug );
		$is_url = str_starts_with( $slug, 'https://' );

		$addon = array_filter(
			$addons,
			function( $addon ) use ( $slug, $is_url ) {

				if ( $is_url ) {
					return ! empty( $addon->link ) && $addon->link === $slug;
				} else {
					$url = preg_replace( '|add-ons/(.+)/$|', '$1', $addon->link );

					return ! empty( $url ) && $url === $slug;
				}
			}
		);

		return current( $addon );
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
	 * Get animated SVG logo.
	 */
	public function get_animated_logo() {
		return '
		<div class="wp-block-group jm-logo__wrapper is-nowrap is-layout-flex">
			<div class="jm-logo">
				<svg xmlns="http://www.w3.org/2000/svg" class="jm-logo__head" fill="currentColor" viewBox="0 0 80 80">
					<path fill-rule="evenodd" d="M40 76c19.882 0 36-16.118 36-36S59.882 4 40 4 4 20.118 4 40s16.118 36 36 36Zm0 4c22.091 0 40-17.909 40-40S62.091 0 40 0 0 17.909 0 40s17.909 40 40 40Z"></path>
				</svg>
				<div class="jm-logo__inner">
					<svg xmlns="http://www.w3.org/2000/svg" class="jm-logo__face" fill="currentColor" viewBox="0 0 80 80">
						<path d="M28.842 41.536a1.611 1.611 0 0 1 2.216.53 10.466 10.466 0 0 0 8.935 5.006c3.778 0 7.09-2 8.935-5.005a1.611 1.611 0 1 1 2.747 1.685 13.689 13.689 0 0 1-11.682 6.543 13.689 13.689 0 0 1-11.682-6.543 1.611 1.611 0 0 1 .531-2.216Zm-.666-18.096a3.223 3.223 0 1 0-6.446 0 3.223 3.223 0 0 0 6.446 0Zm29.274 0a3.223 3.223 0 1 0-6.446 0 3.223 3.223 0 0 0 6.445 0Z"></path>
					</svg>
					<svg xmlns="http://www.w3.org/2000/svg" class="jm-logo__letters" fill="currentColor" viewBox="0 0 80 80">
						<path d="M33.6 42.888V28h-7.2v14.888c0 .695-.12 1.184-.358 1.466-.24.282-.576.424-1.01.424-.521 0-.934-.185-1.238-.554-.304-.391-.456-1.054-.456-1.987h-7.135c0 2.953.825 5.201 2.476 6.743 1.65 1.542 3.877 2.313 6.679 2.313 2.606 0 4.626-.706 6.059-2.117 1.455-1.434 2.183-3.53 2.183-6.288ZM62.924 51.2V28h-8.829l-4.952 13.846L44.061 28H35.2v23.2h7.232V39.565L45.983 51.2h6.19l3.551-11.635V51.2h7.2Z"></path>
					</svg>
				</div>
			</div>
		</div>';
	}

	/**
	 * Handles output of the reports page in admin.
	 */
	public function output() {
		?>
		<div class="wrap wp_job_manager wp_job_manager_add_ons_wrap job-manager-settings-wrap">
			<div class="job-manager-settings-header-wrap">
				<div class="job-manager-settings-header">
					<div class="job-manager-settings-header-row">
						<img class="job-manager-settings-logo"
							src="<?php echo esc_url( JOB_MANAGER_PLUGIN_URL . '/assets/images/jm-full-logo.png' ); ?>"
							alt="<?php esc_attr_e( 'Job Manager', 'wp-job-manager' ); ?>" />
					</div>
					<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-marketplace' ) ); ?>"
							class="nav-tab
						<?php
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
						if ( ! isset( $_GET['section'] ) || 'helper' !== $_GET['section'] ) {
							echo ' nav-tab-active';
						}
						?>
					">
							<?php esc_html_e( 'Marketplace', 'wp-job-manager' ); ?>
						</a>
						<?php if ( current_user_can( 'update_plugins' ) ) : ?>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-marketplace&section=helper' ) ); ?>"
								class="nav-tab
							<?php
							// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
							if ( isset( $_GET['section'] ) && 'helper' === $_GET['section'] ) {
								echo ' nav-tab-active';
							}
							?>
					">
								<?php esc_html_e( 'Licenses', 'wp-job-manager' ); ?>
							</a>
						<?php endif; ?>
					</nav>
					</div>
			</div>
			<div class="job-manager-settings-body">
				<div class="wp-header-end"></div>
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
				if ( isset( $_GET['section'] ) && 'helper' === $_GET['section'] ) {
					do_action( 'job_manager_helper_output' );
				} else {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
					$category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : null;
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
					$search     = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : null;
					$categories = $this->get_categories();

					if ( $search ) {
						$add_ons = $this->get_add_ons( null, $search );
					} else {
						$add_ons = $this->get_add_ons( $category );
					}

					include_once dirname( __FILE__ ) . '/views/html-admin-page-addons.php';
				}
				?>
			</div>
		</div>
		<?php
	}
}

return WP_Job_Manager_Addons::instance();
