<?php /** @noinspection PhpCSValidationInspection */

/**
 * WPJM Unit Tests Bootstrap
 *
 * @since 1.26.0
 */
class WPJM_Unit_Tests_Bootstrap {
	/** @var \WPJM_Unit_Tests_Bootstrap instance */
	protected static $instance = null;

	/** @var string directory where wordpress-tests-lib is installed */
	public $wp_tests_dir;

	/** @var string testing includes directory */
	public $includes_dir;

	/** @var string testing directory */
	public $tests_dir;

	/** @var string plugin directory */
	public $plugin_dir;

	/**
	 * Setup the unit testing environment.
	 *
	 * @since 1.26.0
	 */
	public function __construct() {
		define( 'DOING_AJAX', true );
		define( 'WPJM_REST_API_ENABLED', true );
		ini_set( 'display_errors', 'on' );

		error_reporting( E_ALL );
		set_error_handler( array( $this, 'convert_to_exception' ), E_ALL );

		$this->tests_dir    = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'tests';
		$this->includes_dir = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'includes';
		$this->plugin_dir   = dirname( dirname( dirname( $this->tests_dir ) ) );
		$this->wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib';

		// load test function so tests_add_filter() is available.
		require_once $this->wp_tests_dir . '/includes/functions.php';

		// load WPJM.
		tests_add_filter( 'muplugins_loaded', array( $this, 'load_plugin' ) );

		// install WPJM.
		tests_add_filter( 'setup_theme', array( $this, 'install_plugin' ) );

		// load the WP testing environment.
		require_once $this->wp_tests_dir . '/includes/bootstrap.php';

		// load WPJM testing framework.
		$this->includes();
		restore_error_handler();
	}

	/**
	 * Load WPJM.
	 *
	 * @since 1.26.0
	 */
	public function load_plugin() {
		error_reporting( E_ALL );
		update_option( 'job_manager_enable_types', true );
		update_option( 'job_manager_enable_categories', true );

		require_once $this->plugin_dir . '/wp-job-manager.php';
	}

	/**
	 * Install WPJM after the test environment and WPJM have been loaded.
	 *
	 * @since 1.26.0
	 */
	public function install_plugin() {
		global $wp_version;

		// reload capabilities after install, see https://core.trac.wordpress.org/ticket/28374.
		if ( version_compare( $wp_version, '4.7.0' ) >= 0 ) {
			$GLOBALS['wp_roles'] = new WP_Roles();
		} else {
			$GLOBALS['wp_roles']->reinit();
		}
	}

	/**
	 * Load WPJM-specific test cases and framework.
	 *
	 * @since 1.26.0
	 */
	public function includes() {
		// framework.
		require_once $this->includes_dir . '/factories/class-wpjm-factory.php';
		require_once $this->includes_dir . '/class-wpjm-base-test.php';
		require_once $this->includes_dir . '/class-wpjm-helper-base-test.php';
		require_once $this->includes_dir . '/class-wpjm-rest-testcase.php';
	}

	/**
	 * Get the single class instance.
	 *
	 * @since 1.26.0
	 * @return WPJM_Unit_Tests_Bootstrap
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Converts all errors to exceptions during plugin loading.
	 *
	 * @param int    $errno
	 * @param string $errstr
	 * @param string $errfile
	 * @param int    $errline
	 *
	 * @throws Exception
	 */
	public function convert_to_exception( $errno, $errstr, $errfile, $errline ) {
		if ( ! defined( 'E_DEPRECATED' ) ) {
			define( 'E_DEPRECATED', 8192 );
		}

		$error_descriptions = array(
			E_WARNING    => 'Warning',
			E_ERROR      => 'Error',
			E_PARSE      => 'Parse Error',
			E_NOTICE     => 'Notice',
			E_STRICT     => 'Strict Notice',
			E_DEPRECATED => 'PHP Deprecated',
		);
		if ( in_array( $errno, array( E_RECOVERABLE_ERROR ) ) ) {
			return;
		}
		$description = 'Unknown Error: ';
		if ( isset( $error_descriptions[ $errno ] ) ) {
			$description = $error_descriptions[ $errno ] . ': ';
		}
		$description .= $errstr . " in {$errfile} on line {$errline}";

		// PHP 5.2 doesn't show the error from Exceptions.
		if ( version_compare( phpversion(), '5.3.0', '<' ) ) {
			echo 'Error (' . esc_html( $errno ) . '( - ' . esc_html( $description ) . "\n";
		}

		throw new Exception( $description );
	}
}
WPJM_Unit_Tests_Bootstrap::instance();

