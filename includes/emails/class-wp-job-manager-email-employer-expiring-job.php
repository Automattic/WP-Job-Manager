<?php
/**
 * File containing the class WP_Job_Manager_Email_Employer_Expiring_Job.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email notification to employers when a job is expiring.
 *
 * @since 1.31.0
 * @extends WP_Job_Manager_Email
 */
class WP_Job_Manager_Email_Employer_Expiring_Job extends WP_Job_Manager_Email_Template {
	const SETTING_NOTICE_PERIOD_NAME    = 'notice_period_days';
	const SETTING_NOTICE_PERIOD_DEFAULT = '1';

	/**
	 * Get the unique email notification key.
	 *
	 * @return string
	 */
	public static function get_key() {
		return 'employer_expiring_job';
	}

	/**
	 * Get the friendly name for this email notification.
	 *
	 * @return string
	 */
	public static function get_name() {
		return __( 'Employer Notice of Expiring Job Listings', 'wp-job-manager' );
	}

	/**
	 * Get the description for this email notification.
	 *
	 * @type abstract
	 * @return string
	 */
	public static function get_description() {
		return __( 'Send notices to employers before a job listing expires.', 'wp-job-manager' );
	}

	/**
	 * Get the notice period in days from the notification settings.
	 *
	 * @param array $settings
	 * @return int
	 */
	public static function get_notice_period( $settings ) {
		if ( isset( $settings[ self::SETTING_NOTICE_PERIOD_NAME ] ) ) {
			return absint( $settings[ self::SETTING_NOTICE_PERIOD_NAME ] );
		}
		return absint( self::SETTING_NOTICE_PERIOD_DEFAULT );
	}

	/**
	 * Get the email subject.
	 *
	 * @return string
	 */
	public function get_subject() {
		$args = $this->get_args();

		/**
		 * Job listing post object.
		 *
		 * @var WP_Post $job
		 */
		$job = $args['job'];

		// translators: Placeholder %s is the job listing post title.
		return sprintf( __( 'Job Listing Expiring: %s', 'wp-job-manager' ), $job->post_title );
	}

	/**
	 * Get `From:` address header value. Can be simple email or formatted `Firstname Lastname <email@example.com>`.
	 *
	 * @return string|bool Email from value or false to use WordPress' default.
	 */
	public function get_from() {
		return false;
	}

	/**
	 * Get array or comma-separated list of email addresses to send message.
	 *
	 * @return string|array
	 */
	public function get_to() {
		$args = $this->get_args();
		return $args['author']->user_email;
	}

	/**
	 * Expand arguments as necessary for the generation of the email.
	 *
	 * @param array $args
	 * @return mixed
	 */
	protected function prepare_args( $args ) {
		$args = parent::prepare_args( $args );

		if ( isset( $args['job'] ) ) {
			$args['expiring_today'] = false;
			$today                  = wp_date( 'Y-m-d' );
			$expiring_date          = WP_Job_Manager_Post_Types::instance()->get_job_expiration( $args['job'] );
			if ( ! empty( $args['job']->_job_expires ) && $today === $expiring_date->format( 'Y-m-d' ) ) {
				$args['expiring_today'] = true;
			}
		}

		return $args;
	}

	/**
	 * Get the settings for this email notifications.
	 *
	 * @return array
	 */
	public static function get_setting_fields() {
		$fields   = parent::get_setting_fields();
		$fields[] = [
			'name'       => self::SETTING_NOTICE_PERIOD_NAME,
			'std'        => self::SETTING_NOTICE_PERIOD_DEFAULT,
			'label'      => __( 'Notice Period', 'wp-job-manager' ),
			'type'       => 'number',
			'after'      => ' ' . __( 'days', 'wp-job-manager' ),
			'attributes' => [ 'min' => 0 ],
		];
		return $fields;
	}

	/**
	 * Is this email notification enabled by default?
	 *
	 * @return bool
	 */
	public static function is_default_enabled() {
		return false;
	}

	/**
	 * Checks the arguments and returns whether the email notification is properly set up.
	 *
	 * @return bool
	 */
	public function is_valid() {
		$args = $this->get_args();
		return isset( $args['job'] )
					&& $args['job'] instanceof WP_Post
					&& isset( $args['author'] )
					&& $args['author'] instanceof WP_User
					&& ! empty( $args['author']->user_email );
	}

}
