<?php
/**
 * File containing the class WP_Job_Manager_Email.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class for an email notification.
 *
 * Do not rely on WordPress global variables or functions that rely on global variables such as `wp_get_current_user()`.
 * Email might be generated when no longer in scope. Instead, pass the values on as an argument when initiating the email
 * notification.
 *
 * Additionally, inside of plugins and themes, load email notification files based on this class inside the
 * `job_manager_email_init` hook. This will prevent unnecessary loading and won't include the files if this abstract
 * class isn't available.
 *
 * Example:
 * ```
 * add_action( 'job_manager_email_init', 'custom_plugin_include_emails' );
 * function custom_plugin_include_emails() {
 *     include_once 'emails/custom-plugin-sent-resume.php`;
 * }
 * ```
 *
 * @since 1.31.0
 */
abstract class WP_Job_Manager_Email {
	/**
	 * Arguments used in an instance of an email notification.
	 *
	 * @var array
	 */
	private $args = [];

	/**
	 * Settings for this email notification.
	 *
	 * @var array
	 */
	private $settings = [];

	/**
	 * WP_Job_Manager_Email constructor.
	 *
	 * @param array $args     Arguments used in forming email notification.
	 * @param array $settings Settings for this notification.
	 */
	final public function __construct( $args, $settings ) {
		$this->args     = $this->prepare_args( (array) $args );
		$this->settings = (array) $settings;
	}

	/**
	 * Get the unique email notification key.
	 *
	 * @type abstract
	 *
	 * @return string
	 */
	public static function get_key() {
		return false;
	}

	/**
	 * Get the friendly name for this email notification.
	 *
	 * @type abstract
	 * @return string
	 */
	public static function get_name() {
		return false;
	}

	/**
	 * Get the description for this email notification.
	 *
	 * @type abstract
	 * @return string
	 */
	public static function get_description() {
		return '';
	}

	/**
	 * Get the context for where this email notification is used. Used to direct which admin settings to show.
	 *
	 * @type abstract
	 * @return string
	 */
	public static function get_context() {
		return 'job_manager';
	}

	/**
	 * Get the email subject.
	 *
	 * @return string
	 */
	abstract public function get_subject();

	/**
	 * Get `From:` address header value. Can be simple email or formatted `Firstname Lastname <email@example.com>`.
	 *
	 * @return string|bool Email from value or false to use WordPress' default.
	 */
	abstract public function get_from();

	/**
	 * Get array or comma-separated list of email addresses to send message.
	 *
	 * @return string|array
	 */
	abstract public function get_to();

	/**
	 * Get the rich text version of the email content.
	 *
	 * @return string
	 */
	abstract public function get_rich_content();

	/**
	 * Expand arguments as necessary for the generation of the email.
	 *
	 * @param array $args Arguments used to generate the email.
	 * @return array
	 */
	protected function prepare_args( $args ) {
		if ( isset( $args['job_id'] ) ) {
			$job = get_post( $args['job_id'] );
			if ( $job instanceof WP_Post ) {
				$args['job'] = $job;
			}
		}
		if ( isset( $args['job'] ) && $args['job'] instanceof WP_Post ) {
			$author = get_user_by( 'ID', $args['job']->post_author );
			if ( $author instanceof WP_User ) {
				$args['author'] = $author;
			}
		}

		return $args;
	}

	/**
	 * Checks the arguments and returns whether the email notification is properly set up.
	 *
	 * @return bool
	 */
	abstract public function is_valid();

	/**
	 * Returns the list of file paths to attach to an email.
	 *
	 * @return array
	 */
	public function get_attachments() {
		return [];
	}

	/**
	 * Returns the value of the CC header, if needed.
	 *
	 * @return string|null
	 */
	public function get_cc() {
		return null;
	}

	/**
	 * Get the base headers for the email. No need to add CC or From headers. Content-type is added when sending rich-text.
	 *
	 * @return array
	 */
	public function get_headers() {
		return [];
	}

	/**
	 * Get the plaintext version of the email content.
	 *
	 * @return string
	 */
	public function get_plain_content() {
		return normalize_whitespace( wp_strip_all_tags( $this->get_rich_content() ) );
	}

	/**
	 * Get the settings for this email notifications.
	 *
	 * @return array
	 */
	public static function get_setting_fields() {
		return [];
	}

	/**
	 * Is this email notification enabled by default?
	 *
	 * @return bool
	 */
	public static function is_default_enabled() {
		return true;
	}

	/**
	 * Force the email notification to be enabled or disabled.
	 *
	 * @return bool|null True to force enabled; False to force disabled; Null to not force a value.
	 */
	public static function get_enabled_force_value() {
		return null;
	}

	/**
	 * Returns the args that the email notification was sent with.
	 *
	 * @return array
	 */
	final protected function get_args() {
		return $this->args;
	}

	/**
	 * Returns the settings values.
	 *
	 * @return array
	 */
	final protected function get_settings() {
		return $this->settings;
	}
}
