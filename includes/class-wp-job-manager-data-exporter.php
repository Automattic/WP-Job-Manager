<?php
/**
 * Defines a class to handle the user data export
 *
 * @package wp-job-manager
 * @since 1.31.1
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WP_Job_Manager_Data_Exporter' ) ) {
	/**
	 * Handles the user data export.
	 *
	 * @package
	 * @since
	 */
	class WP_Job_Manager_Data_Exporter {
		/**
		 * Register the user data exporter method
		 *
		 * @param array $exporters
		 * @return array $exporters
		 */
		public function register_wpjm_user_data_exporter( $exporters ){
			$exporters['wp-job-manager'] = array(
				'exporter_friendly_name' => __( 'WP Job Manager' ),
				'callback' => array( $this, 'user_data_exporter' ),
			);
			return $exporters;
		}

		/**
		 * Data exporter
		 *
		 * @param string $email_address
		 * @return array
		 */
		public function user_data_exporter( $email_address ) {
			$user = get_user_by( 'email', $email_address );
			if ( false === $user ) {
				return;
			}

			$user_data_to_export = array();
			$user_meta_keys = array(
				'_company_logo',
				'_company_name',
				'_company_website',
				'_company_tagline',
				'_company_twitter',
				'_company_video',
			);

			foreach ( $user_meta_keys as $user_meta_key ) {	
				$user_meta = get_user_meta( $user->ID, $user_meta_key, true );

				if ( empty( $user_meta ) ) {
					continue;
				}

				if ( '_company_logo' === $user_meta_key) {
					$user_meta  = wp_get_attachment_url( $user_meta );
				}

				$user_data_to_export[] = array(
					'name'	 => __( $user_meta_key ),
					'value'	 => $user_meta,
				);
			}

			$export_items = array(
				'group_id'		 => 'wpjm-user-data',
				'group_label'	 => __( 'WP Job Manager User Data' ),
				'item_id'		 => "wpjm-user-data-{$user->ID}",
				'data'			 => $user_data_to_export,
			);

			return array(
				'data' =>$export_items,
				'done' => true,
			);
		}
	}
}
