<?php
/**
 * Frontend redirect messages.
 *
 * @package wp-job-manager
 * @since 2.2.0
 */

namespace WP_Job_Manager\UI;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Redirect after an action is finished and make sure a message can displayed on the next page load.
 *
 * Stores the messages in a transient with an ID, and passes along the ID as a query string to the redirected URL.
 *
 * @since 2.2.0
 *
 * @internal This API is still under development and subject to change.
 * @access private
 */
class Redirect_Message {

	const DEFAULT_QUERY_VAR = 'message';
	const TRANSIENT_PREFIX  = 'wpjm_message_';

	/**
	 * Redirect to a URL with a message to be displayed on the next page load.
	 *
	 * @param string $url URL to redirect to.
	 * @param string $message Optional message to be displayed on the next page load.
	 * @param string $query_var A query variable added to the redirect URL, referencing the message transient.
	 */
	public static function redirect( $url, $message = null, $query_var = '' ) {
		if ( empty( $query_var ) ) {
			$query_var = self::DEFAULT_QUERY_VAR;
		}

		if ( ! empty( $message ) ) {
			$url = add_query_arg( [ $query_var => self::set_transient_message( $message ) ], $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Get a message set during the previous redirect.
	 *
	 * @param string $query_var Optional namespace for the message.
	 *
	 * @return string|null Message content if there is one.
	 */
	public static function get_message( $query_var = '' ) {

		if ( empty( $query_var ) ) {
			$query_var = self::DEFAULT_QUERY_VAR;
		}

		//phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No action is performed.
		$key = sanitize_key( wp_unslash( $_GET[ $query_var ] ?? null ) );

		if ( ! $key ) {
			return null;
		}

		$content = self::get_transient_message( $key );

		if ( ! $content ) {
			return null;
		}

		$content .= self::add_inline_script( $query_var );

		UI::ensure_styles();

		return $content;
	}

	/**
	 * Add a message for the next page load.
	 *
	 * @param string $message Message to be displayed on the next page load.
	 *
	 * @return string Transient key.
	 */
	private static function set_transient_message( $message ) {
		$key = uniqid();

		set_transient( self::TRANSIENT_PREFIX . $key, $message, 10 * MINUTE_IN_SECONDS );

		return $key;
	}

	/**
	 * Get a message for the given transient key, and optionally delete the transient.
	 *
	 * @param string $key Transient key.
	 * @param bool   $delete Whether to delete the message after retrieving it.
	 *
	 * @return string|null Message to be displayed on the next page load.
	 */
	private static function get_transient_message( $key, $delete = true ) {
		$content = get_transient( self::TRANSIENT_PREFIX . $key );

		if ( ! is_string( $content ) ) {
			return null;
		}

		if ( $delete ) {
			delete_transient( self::TRANSIENT_PREFIX . $key );
		}

		return $content;
	}

	/**
	 * Add a small script to remove the redirect message query var from the URL upon page load.
	 *
	 * @param string $query_var Query var to remove.
	 *
	 * @return string Script HTML.
	 */
	private static function add_inline_script( $query_var ) {
		$query_var = esc_js( $query_var );
		return <<<HTML
			<script>
				const url = new URL( location.href );
				url.searchParams.delete('{$query_var}');
				history.replaceState(null, '', url)

			</script>
HTML;
	}

}
