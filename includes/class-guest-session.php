<?php
/**
 * File containing the class Guest_Session.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

/**
 * Manage the guest user session.
 *
 * @since 2.2.0
 */
class Guest_Session {

	use Singleton;

	const COOKIE_NAME   = 'jm-guest-user';
	const COOKIE_EXPIRY = 1 * DAY_IN_SECONDS;
	const QUERY_VAR     = 'jmtoken';

	/**
	 * The current guest user.
	 *
	 * @var Guest_User|false
	 */
	private $user = false;

	/**
	 * Whether the session has been initialized.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Get the guest user for the current session.
	 *
	 * @return Guest_User|false
	 */
	public static function get_current_guest() {
		$session = self::instance();
		$session->init();

		return $session->user;
	}

	/**
	 * Checks if the current guest user has an account.
	 *
	 * @return bool
	 */
	public static function current_guest_has_account() : bool {
		$user = self::get_current_guest();

		if ( false === $user ) {
			return false;
		}

		return (bool) get_user_by( 'email', $user->user_email );
	}

	/**
	 * Initialize the guest session based on URL token or session cookie.
	 * If only a URL token is present, it will be set as a session cookie.
	 *
	 * @return void
	 */
	private function init() {

		if ( $this->initialized ) {
			return;
		}

		$this->initialized = true;

		$token  = $this->get_url_token();
		$cookie = $this->get_cookie();
		$token  = $token ?? $cookie;

		if ( ! $token ) {
			return;
		}

		$guest = Guest_User::get_by_token( $token );

		$this->user = $guest;

		if ( $guest && $cookie !== $token ) {
			$this->set_cookie( $token );
		}
	}

	/**
	 * Get the token from a session cookie.
	 *
	 * @return string|null
	 */
	private function get_cookie() {
		return isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ) : null;
	}

	/**
	 * Set the token as a session cookie.
	 *
	 * @param string $token
	 */
	private function set_cookie( $token ) {
		$secure = 'https' === wp_parse_url( get_option( 'home' ), PHP_URL_SCHEME );
		$expire = time() + self::COOKIE_EXPIRY;
		setcookie( self::COOKIE_NAME, $token, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure, true );
	}

	/**
	 * Get the token from the URL query string.
	 *
	 * @return string|null
	 */
	private static function get_url_token() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL token-based login action.
		return isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : null;
	}

}
