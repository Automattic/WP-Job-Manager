<?php
/**
 * File containing the class \WP_Job_Manager\Admin\Release_Notice.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin notice about changes and new features introduced in the latest release.
 */
class Release_Notice {

	/**
	 * Set up notice.
	 */
	public static function init() {
		add_filter( 'wpjm_admin_notices', [ __CLASS__, 'add_release_notice' ] );

		add_action( 'job_manager_action_enable_stats', fn() => \WP_Job_Manager_Settings::instance()->set_setting( \WP_Job_Manager\Stats::OPTION_ENABLE_STATS, '1' ) );
	}

	/**
	 * Add a release notice for the 2.3.0 release.
	 *
	 * @param array $notices
	 *
	 * @return array
	 */
	public static function add_release_notice( $notices ) {

		// Make sure to update the version number in the notice ID when changing this notice for a new release.

		$notice_id = 'release_notice_2_3_0';

		$action_url            = \WP_Job_Manager_Admin_Notices::get_action_url( 'enable_stats', $notice_id );
		$notices[ $notice_id ] = [
			'type'          => 'site-wide',
			'label'         => 'New',
			'heading'       => 'Job Statistics',

			'message'       => '<div>' . __(
				'
<p>Collect analytics data about site visitors for each job listing. Display the detailed statistics in the refreshed jobs dashboard.</p>
<ul>
	<li>Page views and unique visitors with daily breakdown</li>
	<li>Search impressions and apply button clicks</li>
	<li>Add-on integration: Job alert impressions, bookmarks, application stats</li>
	<li>GDPR-compliant, with no personal user information collected</li>
</ul>
',
				'wp-job-manager'
			) . '</div>',
			'actions'       => [
				[
					'label'   => __( 'Enable', 'wp-job-manager' ),
					'url'     => $action_url,
					'primary' => true,
				],
				[
					'label'   => __( 'Dismiss', 'wp-job-manager' ),
					'url'     => \WP_Job_Manager_Admin_Notices::get_dismiss_url( $notice_id ),
					'primary' => false,
				],
				[
					'label' => __( 'See what\'s new in 2.3', 'wp-job-manager' ),
					'url'   => 'https://wpjobmanager.com/2024/03/27/new-in-2-3-job-statistics/',
					'class' => 'is-link',
				],
			],
			'icon'          => false,
			'level'         => 'landing',
			'image'         => 'https://wpjobmanager.com/wp-content/uploads/2024/03/jm-230-release.png',
			'dismissible'   => false,
			'extra_details' => '',
			'conditions'    => [
				[
					'type'    => 'screens',
					'screens' => [ 'edit-job_listing' ],
				],
			],
		];

		return $notices;
	}

}
