<?php
/**
 * Reusable Usage Tracking library. For sending plugin usage data and events to
 * Tracks.
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
	private static $instances = array();

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
	 */
	public static function get_instance() {
		throw new Exception( 'Usage Tracking subclasses must implement get_instance. See class-usage-tracking-base.php' );
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
	 * Get the text domain used by this plugin. This class will add some
	 * strings to be translated.
	 *
	 * @return string The text domain string.
	 **/
	abstract protected function get_text_domain();

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
	 * Checks if we should send an activated plugin's installed version in the
	 * `system_log` event.
	 *
	 * @param string $plugin_slug the plugin slug to check.
	 *
	 * @return bool true if we send the version, false if not.
	 */
	abstract protected function do_track_plugin( $plugin_slug );


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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_script_deps' ) );
		add_action( 'admin_footer', array( $this, 'output_opt_in_js' ) );
		add_action( 'admin_notices', array( $this, 'maybe_display_tracking_opt_in' ) );
		add_action( 'wp_ajax_' . $this->get_prefix() . '_handle_tracking_opt_in', array( $this, 'handle_tracking_opt_in' ) );

		// Set up schedule and action needed for cron job.
		add_filter( 'cron_schedules', array( $this, 'add_usage_tracking_two_week_schedule' ) );
		add_action( $this->job_name, array( $this, 'send_usage_data' ) );
	}

	/**
	 * Create (if necessary) and return the singleton instance for the given
	 * subclass.
	 *
	 * @param string $subclass the name of the subclass.
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
	public function send_event( $event, $properties = array(), $event_timestamp = null ) {

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
		$p                 = array();

		foreach ( $properties as $key => $value ) {
			$p[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
		}

		$pixel   .= '?' . implode( '&', $p ) . '&_=_'; // EOF marker.
		$response = wp_remote_get(
			$pixel,
			array(
				'blocking'    => true,
				'timeout'     => 1,
				'redirection' => 2,
				'httpversion' => '1.1',
				'user-agent'  => $this->get_event_prefix() . '_usage_tracking',
			)
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
	 * @return array of $schedules.
	 **/
	public function add_usage_tracking_two_week_schedule( $schedules ) {
		$day_in_seconds = 86400;
		$schedules[ $this->get_prefix() . '_usage_tracking_two_weeks' ] = array(
			'interval' => 15 * $day_in_seconds,
			'display'  => esc_html__( 'Every Two Weeks', $this->get_text_domain() ),
		);

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

		$system_data                         = array();
		$system_data['wp_version']           = $wp_version;
		$system_data['php_version']          = PHP_VERSION;
		$system_data['locale']               = get_locale();
		$system_data['multisite']            = is_multisite() ? 1 : 0;
		$system_data['active_theme']         = $theme['Name'];
		$system_data['active_theme_version'] = $theme['Version'];

		$plugin_data = $this->get_plugin_data();
		foreach ( $plugin_data as $plugin_name => $plugin_version ) {
			if ( $this->do_track_plugin( $plugin_name ) ) {
				$plugin_friendly_name       = preg_replace( '/[^a-z0-9]/', '_', $plugin_name );
				$plugin_key                 = self::PLUGIN_PREFIX . $plugin_friendly_name;
				$system_data[ $plugin_key ] = $plugin_version;
			}
		}

		return $system_data;
	}

	/**
	 * Gets a list of activated plugins.
	 *
	 * @return array List of plugins. Index is friendly name, value is version.
	 */
	protected function get_plugin_data() {
		$plugins = array();
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
		return (bool) get_option( $this->hide_tracking_opt_in_option_name );
	}

	/**
	 * Allowed html tags, used by wp_kses, for the translated opt-in dialog
	 * text.
	 *
	 * @return array the html tags.
	 **/
	protected function opt_in_dialog_text_allowed_html() {
		return array(
			'a'      => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
			),
			'em'     => array(),
			'strong' => array(),
		);
	}

	/**
	 * If needed, display opt-in dialog to enable tracking. Should not be
	 * called externally.
	 **/
	public function maybe_display_tracking_opt_in() {
		$opt_in_hidden         = $this->is_opt_in_hidden();
		$user_tracking_enabled = $this->is_tracking_enabled();
		$can_manage_tracking   = $this->current_user_can_manage_tracking();

		if ( ! $user_tracking_enabled && ! $opt_in_hidden && $can_manage_tracking ) { ?>
			<div id="<?php echo esc_attr( $this->get_prefix() ); ?>-usage-tracking-notice" class="notice notice-info"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'tracking-opt-in' ) ); ?>">
				<p>
					<?php echo wp_kses( $this->opt_in_dialog_text(), $this->opt_in_dialog_text_allowed_html() ); ?>
				</p>
				<p>
					<button class="button button-primary" data-enable-tracking="yes">
						<?php esc_html_e( 'Enable Usage Tracking', $this->get_text_domain() ); ?>
					</button>
					<button class="button" data-enable-tracking="no">
						<?php esc_html_e( 'Disable Usage Tracking', $this->get_text_domain() ); ?>
					</button>
					<span id="progress" class="spinner alignleft"></span>
				</p>
			</div>
			<div id="<?php echo esc_attr( $this->get_prefix() ); ?>-usage-tracking-enable-success" class="notice notice-success hidden">
				<p><?php esc_html_e( 'Usage data enabled. Thank you!', $this->get_text_domain() ); ?></p>
			</div>
			<div id="<?php echo esc_attr( $this->get_prefix() ); ?>-usage-tracking-disable-success" class="notice notice-success hidden">
				<p><?php esc_html_e( 'Disabled usage tracking.', $this->get_text_domain() ); ?></p>
			</div>
			<div id="<?php echo esc_attr( $this->get_prefix() ); ?>-usage-tracking-failure" class="notice notice-error hidden">
				<p><?php esc_html_e( 'Something went wrong. Please try again later.', $this->get_text_domain() ); ?></p>
			</div>
		<?php
		}
	}

	/**
	 * Handle ajax request from the opt-in dialog. Should not be called
	 * externally.
	 **/
	public function handle_tracking_opt_in() {
		check_ajax_referer( 'tracking-opt-in', 'nonce' );

		if ( ! $this->current_user_can_manage_tracking() ) {
			wp_die( '', '', 403 );
		}

		$enable_tracking = isset( $_POST['enable_tracking'] ) && '1' === $_POST['enable_tracking'];
		$this->set_tracking_enabled( $enable_tracking );
		$this->hide_tracking_opt_in();
		$this->send_usage_data();
		wp_die();
	}

	/**
	 * Ensure that jQuery has been enqueued since the opt-in dialog JS depends
	 * on it. Should not be called externally.
	 **/
	public function enqueue_script_deps() {
		// Ensure jQuery is loaded.
		wp_enqueue_script(
			$this->get_prefix() . '_usage-tracking-notice', '',
			array( 'jquery' ), null, true
		);
	}

	/**
	 * Output the JS code to handle the opt-in dialog. Should not be called
	 * externally.
	 **/
	public function output_opt_in_js() {
?>
<script type="text/javascript">
	(function( prefix ) {
		jQuery( document ).ready( function() {
			function displayProgressIndicator() {
				jQuery( '#' + prefix + '-usage-tracking-notice #progress' ).addClass( 'is-active' );
			}

			function displaySuccess( enabledTracking ) {
				if ( enabledTracking ) {
					jQuery( '#' + prefix + '-usage-tracking-enable-success' ).show();
				} else {
					jQuery( '#' + prefix + '-usage-tracking-disable-success' ).show();
				}
				jQuery( '#' + prefix + '-usage-tracking-notice' ).hide();
			}

			function displayError() {
				jQuery( '#' + prefix + '-usage-tracking-failure' ).show();
				jQuery( '#' + prefix + '-usage-tracking-notice' ).hide();
			}

			// Handle button clicks.
			jQuery( '#' + prefix + '-usage-tracking-notice button' ).click( function( event ) {
				event.preventDefault();

				var enableTracking = jQuery( this ).data( 'enable-tracking' ) == 'yes';
				var nonce          = jQuery( '#' + prefix + '-usage-tracking-notice' ).data( 'nonce' );

				displayProgressIndicator();

				jQuery.ajax( {
					type: 'POST',
					url: ajaxurl,
					data: {
						action: prefix + '_handle_tracking_opt_in',
						enable_tracking: enableTracking ? 1 : 0,
						nonce: nonce,
					},
					success: function() {
						displaySuccess( enableTracking );
					},
					error: displayError,
				} );
			});
		});
	})( "<?php echo esc_js( $this->get_prefix() ); ?>" );
</script>
<?php
	}
}
