<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Base class for WP Job Manager's email notification system.
 *
 * @package wp-job-manager
 * @since 1.31.0
 */
final class WP_Job_Manager_Email_Notifications {
	/**
	 * @var array
	 */
	private static $deferred_notifications = array();

	/**
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Sets up initial hooks.
	 *
	 * @static
	 */
	public static function init() {
		add_action( 'job_manager_send_notification', array( __CLASS__, '_schedule_notification' ), 10, 2 );
		add_action( 'job_manager_email_init', array( __CLASS__, '_lazy_init' ) );
	}

	/**
	 * Gets list of email notifications handled by WP Job Manager core.
	 *
	 * @return array
	 */
	private static function core_email_notifications() {
		return array(
			'WP_Job_Manager_Email_Admin_New_Job',
		);
	}

	/**
	 * Sets up an email notification to be sent at the end of the script's execution.
	 *
	 * @param string $notification
	 * @param array  $args
	 */
	public static function _schedule_notification( $notification, $args = array() ) {
		if ( ! self::$initialized ) {
			/**
			 * Lazily load remaining files needed for email notifications. Do this here instead of in
			 * `shutdown` for proper logging in case of syntax errors.
			 *
			 * @since 1.31.0
			 */
			do_action( 'job_manager_email_init' );
			self::$initialized = true;
		}

		self::$deferred_notifications[] = array( $notification, $args );
	}

	/**
	 * Sends all notifications collected during execution.
	 *
	 * Do not call manually.
	 *
	 * @access private
	 */
	public static function _send_deferred_notifications() {
		$email_notifications = self::get_email_notifications( true );
		foreach ( self::$deferred_notifications as $email ) {
			if (
				! is_string( $email[0] )
				|| ! isset( $email_notifications[ $email[0] ] )
			) {
				continue;
			}

			$class_name = $email_notifications[ $email[0] ];
			$email_args = is_array( $email[1] ) ? $email[1] : array();

			self::send_email( $email[0], new $class_name( $email_args ) );
		}
	}

	/**
	 * Include email files.
	 *
	 * Do not call manually.
	 *
	 * @access private
	 */
	public static function _lazy_init() {
		add_action( 'shutdown', array( __CLASS__, '_send_deferred_notifications' ) );

		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/emails/class-wp-job-manager-email-admin-new-job.php';
	}

	/**
	 * Clear the deferred notifications email array.
	 *
	 * Do not call manually. Only for help with tests.
	 *
	 * @access private
	 */
	public static function _clear_deferred_notifications() {
		if ( ! defined( 'PHPUNIT_WPJM_TESTSUITE' ) || ! PHPUNIT_WPJM_TESTSUITE ) {
			die( "This is just for use while testing" );
		}
		self::$deferred_notifications = array();
	}

	/**
	 * Gets a list of all email notifications that WP Job Manager handles.
	 *
	 * @param bool $enabled_notifications_only
	 * @return array
	 */
	public static function get_email_notifications( $enabled_notifications_only = false ) {
		/**
		 * Retrieves all email notifications to be sent.
		 *
		 * @since 1.31.0
		 *
		 * @param array $email_notifications All the email notifications to be registered.
		 */
		$email_notification_classes = array_unique( apply_filters( 'job_manager_email_notifications', self::core_email_notifications() ) );
		$email_notifications = array();

		/**
		 * @var WP_Job_Manager_Email $email_class
		 */
		foreach ( $email_notification_classes as $email_class ) {
			// Check to make sure email notification is valid.
			if ( ! self::is_email_notification_valid( $email_class ) ) {
				continue;
			}

			// PHP 5.2: Using `call_user_func()` but `$email_class::get_key()` preferred.
			$email_notification_key = call_user_func( array( $email_class, 'get_key') );
			if (
				isset( $email_notifications[ $email_notification_key ] )
				|| ( $enabled_notifications_only && ! self::is_email_notification_enabled( $email_notification_key ) )
			) {
				continue;
			}

			$email_notifications[ $email_notification_key ] = $email_class;
		}

		return $email_notifications;
	}

	/**
	 * Generate the file name for the email template.
	 *
	 * @param string $template_name
	 * @param bool   $plain_text
	 * @return string
	 */
	public static function get_template_file_name( $template_name, $plain_text = false ) {
		$file_name_parts = array( 'emails' );
		if ( $plain_text ) {
			$file_name_parts[] = 'plain';
		}

		$file_name_parts[] = $template_name . '.php';

		return implode( '/', $file_name_parts );
	}

	/**
	 * Returns the total number of deferred notifications to be sent. Used in unit tests.
	 *
	 * @access private
	 *
	 * @return int
	 */
	public static function _get_deferred_notification_count() {
		return count( self::$deferred_notifications );
	}

	/**
	 * Confirms an email notification is valid.
	 *
	 * @access private
	 *
	 * @param string $email_class
	 * @return bool
	 */
	private static function is_email_notification_valid( $email_class ) {
		// PHP 5.2: Using `call_user_func()` but `$email_class::get_key()` preferred.
		return is_string( $email_class )
				&& class_exists( $email_class )
				&& is_subclass_of( $email_class, 'WP_Job_Manager_Email' )
				&& false !== call_user_func( array( $email_class, 'get_key') )
				&& false !== call_user_func( array( $email_class, 'get_name') );
	}

	/**
	 * Sends an email notification.
	 *
	 * @access private
	 *
	 * @param string               $email_notification_key
	 * @param WP_Job_Manager_Email $email
	 * @return bool
	 */
	private static function send_email( $email_notification_key, WP_Job_Manager_Email $email ) {
		if ( ! $email->is_valid() ) {
			return false;
		}

		$fields = array( 'to', 'from', 'subject', 'rich_content', 'plain_content', 'attachments', 'cc', 'headers' );
		$args = array();
		foreach ( $fields as $field ) {
			$method = 'get_' . $field;

			/**
			 * Filter email values for job manager notifications.
			 *
			 * @since 1.31.0
			 *
			 * @param mixed                $email_field_value Value to be filtered.
			 * @param WP_Job_Manager_Email $email             Email notification object.
			 */
			$args[ $field ] = apply_filters( "job_manager_email_{$email_notification_key}_{$field}", $email->$method(), $email );
		}

		$headers = is_array( $args['headers'] ) ? $args['headers'] : array();

		if ( ! empty( $args['from'] ) ) {
			$headers[] = 'From: ' . $args['from'];
		}

		if ( ! self::send_as_plain_text( $email_notification_key ) ) {
			$headers[] = 'Content-Type: text/html';
		}

		$content = self::get_email_content( $email_notification_key, $args );

		return wp_mail( $args['to'], $args['subject'], $content, $headers, $args['attachments'] );
	}

	/**
	 * Generates the content for an email.
	 *
	 * @access private
	 *
	 * @param string $email_notification_key
	 * @param array  $args
	 * @return string
	 */
	private static function get_email_content( $email_notification_key, $args ) {
		$plain_text = self::send_as_plain_text( $email_notification_key );

		ob_start();

		/**
		 * Output the header for all job manager emails.
		 *
		 * @since 1.31.0
		 *
		 * @param string $email_notification_key Unique email notification key.
		 * @param array  $args                   Arguments passed for generating email.
		 * @param bool   $plain_text             True if sending plain text email.
		 */
		do_action( 'job_manager_email_header', $email_notification_key, $args, $plain_text );

		if ( $plain_text ) {
			echo wptexturize( $args['plain_content'] );
		} else {
			echo wpautop( wptexturize( $args['rich_content'] ) );
		}

		/**
		 * Output the footer for all job manager emails.
		 *
		 * @since 1.31.0
		 *
		 * @param string $email_notification_key Unique email notification key.
		 * @param array  $args                   Arguments passed for generating email.
		 * @param bool   $plain_text             True if sending plain text email.
		 */
		do_action( 'job_manager_email_footer', $email_notification_key, $args, $plain_text );

		$content = ob_get_clean();
		return $content;
	}

	/**
	 * Checks if a particular notification is enabled or not.
	 *
	 * @access private
	 *
	 * @param string $email_notification_key
	 * @return bool
	 */
	private static function is_email_notification_enabled( $email_notification_key ) {
		/**
		 * Filter whether to send a notification email.
		 *
		 * @since 1.31.0
		 *
		 * @param bool   $send_notification
		 * @param string $email_notification_key
		 */
		return apply_filters( 'job_manager_emails_is_email_notification_enabled', true, $email_notification_key );
	}

	/**
	 * Checks if we should send emails using plain text.
	 *
	 * @access private
	 *
	 * @param string $email_notification_key
	 * @return bool
	 */
	private static function send_as_plain_text( $email_notification_key ) {
		/**
		 * Filter whether to send emails as plain text.
		 *
		 * @since 1.31.0
		 *
		 * @param bool   $send_as_plain_text
		 * @param string $email_notification_key
		 */
		return apply_filters( 'job_manager_emails_send_as_plain_text', false, $email_notification_key );
	}
}
