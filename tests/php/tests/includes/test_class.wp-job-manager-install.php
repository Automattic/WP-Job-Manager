<?php

class WP_Test_WP_Job_Manager_Install extends WPJM_BaseTest {
	/**
	 * @covers WP_Job_Manager_Install::install
	 */
	public function test_install_adds_job_manager_role_with_listing_caps() {
		remove_role( 'job_manager' );

		$this->install();

		$role = get_role( 'job_manager' );
		$this->assertInstanceOf( WP_Role::class, $role );
		$this->assertTrue( $role->has_cap( 'read' ) );
		$this->assertTrue( $role->has_cap( 'upload_files' ) );
		$this->assertTrue( $role->has_cap( \WP_Job_Manager_Post_Types::CAP_MANAGE_LISTINGS ) );
		$this->assertTrue( $role->has_cap( \WP_Job_Manager_Post_Types::CAP_EDIT_OTHERS_LISTINGS ) );
		$this->assertTrue( $role->has_cap( \WP_Job_Manager_Post_Types::CAP_DELETE_OTHERS_LISTINGS ) );
		$this->assertTrue( $role->has_cap( \WP_Job_Manager_Post_Types::CAP_MANAGE_LISTING_TERMS ) );
	}

	/**
	 * @covers WP_Job_Manager_Install::install
	 */
	public function test_job_manager_role_cannot_manage_site() {
		$this->install();

		$user_id = $this->factory->user->create( [ 'role' => 'job_manager' ] );
		$user    = get_user_by( 'id', $user_id );

		$this->assertTrue( $user->has_cap( \WP_Job_Manager_Post_Types::CAP_EDIT_LISTINGS ) );
		$this->assertFalse( $user->has_cap( 'manage_options' ) );
		$this->assertFalse( $user->has_cap( 'edit_posts' ) );
		$this->assertFalse( $user->has_cap( 'edit_users' ) );
		$this->assertFalse( $user->has_cap( 'list_users' ) );
		$this->assertFalse( $user->has_cap( 'activate_plugins' ) );
		$this->assertFalse( $user->has_cap( 'switch_themes' ) );
	}

	/**
	 * @covers WP_Job_Manager_Install::install
	 */
	public function test_install_restores_missing_job_manager_caps() {
		$this->install();
		get_role( 'job_manager' )->remove_cap( \WP_Job_Manager_Post_Types::CAP_PUBLISH_LISTINGS );

		$this->install();

		$this->assertTrue( get_role( 'job_manager' )->has_cap( \WP_Job_Manager_Post_Types::CAP_PUBLISH_LISTINGS ) );
	}

	/**
	 * Runs the installer and reloads roles, as WP_Roles::add_cap() does not refresh the role objects.
	 */
	private function install() {
		WP_Job_Manager_Install::install();
		wp_roles()->for_site();
	}
}
