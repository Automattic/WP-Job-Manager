<?php
/**
 * Reusable Usage Tracking library. For sending plugin usage data and events to
 * Tracks.
 *
 * @package wp-job-manager
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Usage Tracking class. Please update the prefix to something unique to your
 * plugin.
 */
abstract class WP_Job_Manager_Usage_Tracking_Base {
	const PLUGIN_PREFIX = 'plugin_';

	const DISPLAY_ONCE_OPTION = 'job_manager_display_usage_tracking_once';

	/*
	 * Instance variables.
	 */

	/**
	 * The name of the option for hiding the Usage Tracking opt-in dialog.
	 *
	 * @var string
	 **/
	protected $hide_tracking_opt_in_option_name;

	/**
	 * The name of the cron job action for regularly logging usage data.
	 *
	 * @var string
	 **/
	private $job_name;

	/**
	 * Callback function for the usage tracking job.
	 *
	 * @var array
	 **/
	private $callback;


	/*
	 * Class variables.
	 */

	/**
	 * Subclass instances.
	 *
	 * @var array
	 **/
	private static $instances = [];

	/**
	 * Gets the singleton instance of this class. Subclasses should implement
	 * this as follows:
	 *
	 * ```
	 * public static function get_instance() {
	 *   return self::get_instance_for_subclass( get_class() );
	 * }
	 * ```
	 *
	 * This function cannot be abstract (because it is static) but it *must* be
	 * implemented by subclasses.
	 *
	 * @throws Exception When get_instance is not implemented.
	 */
	public static function get_instance() {
		throw new Exception( 'Usage Tracking subclasses must implement get_instance. See class-wp-job-manager-usage-tracking-base.php' );
	}


	/*
	 * Abstract methods.
	 */


	/**
	 * Get prefix for actions and strings. Should be unique to this plugin.
	 *
	 * @return string The prefix string.
	 **/
	abstract protected function get_prefix();

	/**
	 * Determine whether usage tracking is enabled.
	 *
	 * @return bool true if usage tracking is enabled, false otherwise.
	 **/
	abstract protected function get_tracking_enabled();

	/**
	 * Set whether usage tracking is enabled.
	 *
	 * @param bool $enable true if usage tracking should be enabled, false if
	 * it should be disabled.
	 **/
	abstract protected function set_tracking_enabled( $enable );

	/**
	 * Determine whether current user can manage the tracking options.
	 *
	 * @return bool true if the current user is allowed to manage the tracking.
	 * options, false otherwise.
	 **/
	abstract protected function current_user_can_manage_tracking();

	/**
	 * Get the text to display in the opt-in dialog for users to enable
	 * tracking. This text should include a link to a page indicating what data
	 * is being tracked.
	 *
	 * @return string the text to display in the opt-in dialog.
	 **/
	abstract protected function opt_in_dialog_text();

	/**
	 * Gets the base data returned with system information.
	 *
	 * @return array
	 */
	protected function get_base_system_data() {
		return [];
	}

	/*
	 * Initialization.
	 */

	/**
	 * Subclasses may override this to add plugin-specific initialization code.
	 * However, this constructor must be called by the subclass in order to
	 * properly initialize the Usage Tracking system.
	 *
	 * This class is meant to be a singleton, and assumes that the subclass is
	 * implemented as such. If multiple instances are instantiated, the results
	 * are undefined.
	 **/
	protected function __construct() {
		// Init instance vars.
		$this->hide_tracking_opt_in_option_name = $this->get_prefix() . '_usage_tracking_opt_in_hide';
		$this->job_name                         = $this->get_prefix() . '_usage_tracking_send_usage_data';

		// Set up the opt-in dialog.
		add_action( 'wpjm_admin_notices', [ $this, 'maybe_display_tracking_opt_in' ] );
		add_action( 'admin_action_' . $this->get_prefix() . '_tracking_opt_in', [ $this, 'handle_tracking_opt_in' ] );
		add_action( 'wp_job_manager_notice_dismissed', [ $this, 'handle_tracking_opt_out' ], 10, 2 );

		// Set up schedule and action needed for cron job.
		add_filter( 'cron_schedules', [ $this, 'add_usage_tracking_two_week_schedule' ] );
		add_action( $this->job_name, [ $this, 'send_usage_data' ] );
	}

	/**
	 * Create (if necessary) and return the singleton instance for the given
	 * subclass.
	 *
	 * @param string $subclass the name of the subclass.
	 *
	 * @return object Instance of $subclass.
	 */
	protected static function get_instance_for_subclass( $subclass ) {
		if ( ! isset( self::$instances[ $subclass ] ) ) {
			self::$instances[ $subclass ] = new $subclass();
		}

		return self::$instances[ $subclass ];
	}


	/*
	 * Public methods.
	 */

	/**
	 * Set the Usage Data Callback. This callback should return an array of
	 * data to be logged periodically to Tracks.
	 *
	 * @param callable $callback the callback returning the usage data to be logged.
	 **/
	public function set_callback( $callback ) {
		$this->callback = $callback;
	}

	/**
	 * Send an event to Tracks if tracking is enabled.
	 *
	 * @param string   $event The event name. The prefix string will be
	 *   automatically prepended to this, so please supply this string without a
	 *   prefix.
	 * @param array    $properties Event Properties.
	 * @param null|int $event_timestamp When the event occurred.
	 *
	 * @return null|WP_Error
	 **/
	public function send_event( $event, $properties = [], $event_timestamp = null ) {

		// Only continue if tracking is enabled.
		if ( ! $this->is_tracking_enabled() ) {
			return false;
		}

		$pixel      = 'http://pixel.wp.com/t.gif';
		$event_name = $this->get_event_prefix() . '_' . $event;
		$user       = wp_get_current_user();

		if ( null === $event_timestamp ) {
			$event_timestamp = time();
		}

		$properties['admin_email'] = get_option( 'admin_email' );
		$properties['_ut']         = $this->get_event_prefix() . ':site_url';
		// Use site URL as the userid to enable usage tracking at the site level.
		// Note that we would likely want to use site URL + user ID for userid if we were.
		// to ever add event tracking at the user level.
		$properties['_ui'] = site_url();
		$properties['_ul'] = $user->user_login;
		$properties['_en'] = $event_name;
		$properties['_ts'] = $event_timestamp . '000';
		$properties['_rt'] = round( microtime( true ) * 1000 );  // log time.
		$p                 = [];

		foreach ( $properties as $key => $value ) {
			$p[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
		}

		$pixel   .= '?' . implode( '&', $p ) . '&_=_'; // EOF marker.
		$response = wp_remote_get(
			$pixel,
			[
				'blocking'    => true,
				'timeout'     => 1,
				'redirection' => 2,
				'httpversion' => '1.1',
				'user-agent'  => $this->get_event_prefix() . '_usage_tracking',
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = isset( $response['response']['code'] ) ? $response['response']['code'] : 0;

		if ( 200 !== $code ) {
			return new WP_Error( 'request_failed', 'HTTP Request failed', $code );
		}

		return true;
	}

	/**
	 * Set up a regular cron job to send usage data. The job will only send
	 * the data if tracking is enabled, so it is safe to call this function,
	 * and schedule the job, before the user opts into tracking.
	 **/
	public function schedule_tracking_task() {
		if ( ! wp_next_scheduled( $this->job_name ) ) {
			wp_schedule_event( time(), $this->get_prefix() . '_usage_tracking_two_weeks', $this->job_name );
		}
	}

	/**
	 * Unschedule the job scheduled by schedule_tracking_task if any is
	 * scheduled. This should be called on plugin deactivation.
	 **/
	public function unschedule_tracking_task() {
		if ( wp_next_scheduled( $this->job_name ) ) {
			wp_clear_scheduled_hook( $this->job_name );
		}
	}

	/**
	 * Check if tracking is enabled.
	 *
	 * @return bool true if tracking is enabled, false otherwise.
	 **/
	public function is_tracking_enabled() {
		// Defer to the plugin-specific function.
		return $this->get_tracking_enabled();
	}

	/**
	 * Call the usage data callback and send the usage data to Tracks. Only
	 * sends data if tracking is enabled.
	 **/
	public function send_usage_data() {
		if ( ! self::is_tracking_enabled() || ! is_callable( $this->callback ) ) {
			return;
		}

		$usage_data = call_user_func( $this->callback );

		if ( ! is_array( $usage_data ) ) {
			return;
		}

		self::send_event( 'system_log', $this->get_system_data() );
		self::send_event( 'stats_log', $usage_data );
	}


	/**
	 * Internal methods.
	 */

	/**
	 * Get the prefix for the event-related values. By default, this is the
	 * same prefix used everywhere else, but plugins may override this if
	 * needed.
	 */
	protected function get_event_prefix() {
		return $this->get_prefix();
	}

	/**
	 * Add two week schedule to use for cron job. Should not be called
	 * externally.
	 *
	 * @param array $schedules the existing cron schedules.
	 *
	 * @return array of $schedules.
	 **/
	public function add_usage_tracking_two_week_schedule( $schedules ) {
		$schedules[ $this->get_prefix() . '_usage_tracking_two_weeks' ] = [
			'interval' => 15 * DAY_IN_SECONDS,
			'display'  => esc_html__( 'Every Two Weeks', 'wp-job-manager' ),
		];

		return $schedules;
	}

	/**
	 * Collect system data to track.
	 *
	 * @return array
	 */
	public function get_system_data() {
		global $wp_version;

		/**
		 * Current active theme.
		 *
		 * @var WP_Theme $theme
		 */
		$theme = wp_get_theme();

		$system_data                         = $this->get_base_system_data();
		$system_data['wp_version']           = $wp_version;
		$system_data['php_version']          = PHP_VERSION;
		$system_data['locale']               = get_locale();
		$system_data['multisite']            = is_multisite() ? 1 : 0;
		$system_data['active_theme']         = $theme['Name'];
		$system_data['active_theme_version'] = $theme['Version'];

		$plugin_data = $this->get_plugin_data();
		foreach ( $plugin_data as $plugin_name => $plugin_version ) {
			$plugin_friendly_name       = preg_replace( '/[^a-z0-9]/', '_', $plugin_name );
			$plugin_key                 = self::PLUGIN_PREFIX . $plugin_friendly_name;
			$system_data[ $plugin_key ] = $plugin_version;
		}

		return $system_data;
	}

	/**
	 * Gets a list of activated plugins.
	 *
	 * @return array List of plugins. Index is friendly name, value is version.
	 */
	protected function get_plugin_data() {
		$plugins = [];
		foreach ( $this->get_plugins() as $plugin_basename => $plugin ) {
			$plugin_name             = $this->get_plugin_name( $plugin_basename );
			$plugins[ $plugin_name ] = $plugin['Version'];
		}

		return $plugins;
	}

	/**
	 * Partial wrapper for for `get_plugins()` function. Filters out non-active plugins.
	 *
	 * @return array Key is the plugin file path and the value is an array of the plugin data.
	 */
	protected function get_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		foreach ( $plugins as $plugin_basename => $plugin_data ) {
			if ( ! is_plugin_active( $plugin_basename ) ) {
				unset( $plugins[ $plugin_basename ] );
			}
		}

		return $plugins;
	}

	/**
	 * Returns a friendly slug for a plugin.
	 *
	 * @param string $basename Plugin basename.
	 *
	 * @return string
	 */
	private function get_plugin_name( $basename ) {
		$basename = strtolower( $basename );
		if ( false === strpos( $basename, '/' ) ) {
			return basename( $basename, '.php' );
		}

		return dirname( $basename );
	}

	/**
	 * Hide the opt-in for enabling usage tracking.
	 **/
	protected function hide_tracking_opt_in() {
		update_option( $this->hide_tracking_opt_in_option_name, true );
	}

	/**
	 * Determine whether the opt-in for enabling usage tracking is hidden.
	 *
	 * @return bool true if the opt-in is hidden, false otherwise.
	 **/
	protected function is_opt_in_hidden() {
		$delayed_notice_timestamp = (int) get_option( self::DISPLAY_ONCE_OPTION );

		// The delay has passed, hide the notice if the user refused.
		if ( 0 === $delayed_notice_timestamp ) {
			return (bool) get_option( $this->hide_tracking_opt_in_option_name );
		}

		// When the delay passes, display the tracking notice regardless if the user refused to enable usage tracking in the past.
		if ( $delayed_notice_timestamp < time() ) {
			update_option( self::DISPLAY_ONCE_OPTION, 0 );
			update_option( $this->hide_tracking_opt_in_option_name, false );

			return false;
		}

		// The delay hasn't passed, hide the notice.
		return true;
	}

	/**
	 * Allowed html tags, used by wp_kses, for the translated opt-in dialog
	 * text.
	 *
	 * @return array the html tags.
	 **/
	protected function opt_in_dialog_text_allowed_html() {
		return [
			'a'      => [
				'href'   => [],
				'title'  => [],
				'target' => [],
			],
			'p'      => [],
			'br'     => [],
			'em'     => [],
			'strong' => [],
		];
	}

	/**
	 * If needed, display opt-in dialog to enable tracking. Should not be
	 * called externally.
	 *
	 * @param array $notices Current notices.
	 *
	 * @access private
	 **/
	public function maybe_display_tracking_opt_in( $notices ) {
		$opt_in_hidden         = $this->is_opt_in_hidden();
		$user_tracking_enabled = $this->is_tracking_enabled();
		$can_manage_tracking   = $this->current_user_can_manage_tracking();

		if ( ! $user_tracking_enabled && ! $opt_in_hidden && $can_manage_tracking ) {

			$action = $this->get_prefix() . '_tracking_opt_in';

			$notices['usage_tracking_opt_in'] = [
				'level'       => 'info',
				'dismissible' => true,
				'heading'     => __( 'Improve your experience', 'wp-job-manager' ),
				'message'     => wp_kses( $this->opt_in_dialog_text(), $this->opt_in_dialog_text_allowed_html() ),
				'actions'     => [
					[
						'label' => __( 'Enable Usage Tracking', 'wp-job-manager' ),
						'url'   => add_query_arg(
							[
								'action'   => $action,
								'_wpnonce' => wp_create_nonce( $action ),
							],
							admin_url( 'admin.php' )
						),
					],
				],
			];

		}

		return $notices;
	}

	/**
	 * Handle ajax request from the opt-in dialog. Should not be called
	 * externally.
	 *
	 * @access private
	 **/
	public function handle_tracking_opt_in() {
		check_admin_referer( $this->get_prefix() . '_tracking_opt_in' );

		if ( ! $this->current_user_can_manage_tracking() ) {
			wp_die( '', '', 403 );
		}

		$this->set_tracking_enabled( true );
		$this->hide_tracking_opt_in();
		$this->send_usage_data();

		wp_safe_redirect(
			add_query_arg(
				[
					'action'   => false,
					'_wpnonce' => false,
				],
				admin_url( 'edit.php?post_type=job_listing' )
			)
		);

	}

	/**
	 * Disable usage tracking when the notice is dismissed.
	 *
	 * @param array  $notice    Notice data.
	 * @param string $notice_id Notice ID.
	 *
	 * @access private
	 */
	public function handle_tracking_opt_out( $notice, $notice_id ) {

		if ( 'usage_tracking_opt_in' !== $notice_id ) {
			return;
		}

		$this->set_tracking_enabled( false );
		$this->hide_tracking_opt_in();
	}

	/**
	 * Ensure that jQuery has been enqueued since the opt-in dialog JS depends
	 * on it. Should not be called externally.
	 *
	 * @deprecated since 2.0.0
	 **/
	public function enqueue_script_deps() {
		_deprecated_function( __METHOD__, '$$next-version' );
	}

	/**
	 * Output the JS code to handle the opt-in dialog. Should not be called
	 * externally.
	 *
	 * @deprecated since 2.0.0
	 **/
	public function output_opt_in_js() {
		_deprecated_function( __METHOD__, '$$next-version' );
		?>
		<?php
	}
}
