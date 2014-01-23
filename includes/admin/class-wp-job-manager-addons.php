<?php
/**
 * Addons Page
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'WP_Job_Manager_Addons' ) ) :

/**
 * WP_Job_Manager_Addons Class
 */
class WP_Job_Manager_Addons {

	/**
	 * Handles output of the reports page in admin.
	 */
	public function output() {

		if ( false === ( $addons = get_transient( 'wp_job_manager_addons_html' ) ) ) {

			$raw_addons = wp_remote_get( 'http://mikejolley.com/projects/wp-job-manager/add-ons/' );

			if ( ! is_wp_error( $raw_addons ) ) {

				$raw_addons = wp_remote_retrieve_body( $raw_addons );

				// Get Products
				$dom = new DOMDocument();
				libxml_use_internal_errors(true);
				$dom->loadHTML( $raw_addons );

				$xpath  = new DOMXPath( $dom );
				$tags   = $xpath->query('//ul[@class="items"]');
				foreach ( $tags as $tag ) {
					$addons = $tag->ownerDocument->saveXML( $tag );
					break;
				}

				$addons = wp_kses_post( $addons );

				if ( $addons )
					set_transient( 'wp_job_manager_addons_html', $addons, 60*60*24*7 ); // Cached for a week
			}
		}

		?>
		<div class="wrap wp_job_manager wp_job_manager_addons_wrap">
			<div class="icon32 icon32-posts-job_listing" id="icon-edit"><br /></div>
			<h2><?php _e( 'WP Job Manager Add-ons', 'wp-job-manager' ); ?></h2>
			<?php echo $addons; ?>
		</div>
		<?php
	}
}

endif;

return new WP_Job_Manager_Addons();