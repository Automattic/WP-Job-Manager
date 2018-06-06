<?php

require 'includes/class-wp-job-manager-data-exporter';

class WP_Job_Manager_Data_Exporter_Test extends WP_UnitTestCase {
	/**
	 * Setup user meta
	 *
	 * @param array $args
	 */
	private function setupUserMeta( $args ) {
		$user_id = $this->factory()->user->create(
			array(
				'user_login' => 'johndoe',
				'user_email' => 'johndoe@example.com',
				'role' => 'subscriber',
			)
		);

		if ( isset( $args['_company_logo' ] ) ) {
			$args['_company_logo'] = $this->factory()->post->create(
				array(
					'post_type' => 'attachment'
				)
			);
		}

		foreach ( $args as $key => $value ) {
			update_user_meta( $user_id, $key, $value );
		}
	}
}
