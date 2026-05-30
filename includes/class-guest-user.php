<?php
/**
 * File containing the class Guest_User.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * A guest user is a site visitors without a full account, identified by their email address.
 *
 * @since 2.2.0
 */
class Guest_User {
	const TOKEN_EXPIRY    = 35 * DAY_IN_SECONDS;
	const TOKEN_PREFIX    = 'jm';
	const TOKEN_SEPARATOR = '-';

	/**
	 * The post object for the guest user.
	 *
	 * @var \WP_Post
	 */
	private $post;

	/**
	 * The post ID.
	 *
	 * @readonly
	 * @var int
	 */
	public int $ID;

	/**
	 * The user email address.
	 *
	 * @readonly
	 * @var string
	 */
	public string $user_email;

	/**
	 * Constructor.
	 *
	 * @param \WP_Post $guest The guest user post object.
	 */
	public function __construct( \WP_Post $guest ) {
		$this->post       = $guest;
		$this->ID         = $guest->ID;
		$this->user_email = $guest->post_title;
	}

	/**
	 * The post object for the guest user.
	 *
	 * @param int|string $guest_id Post ID of guest user, or their email.
	 *
	 * @return self|false
	 */
	public static function load( $guest_id ) {

		if ( is_numeric( $guest_id ) ) {
			$guest = get_post( $guest_id );
		} elseif ( is_email( $guest_id ) ) {
			$email = sanitize_email( $guest_id );
			$guest = get_posts(
				[
					'post_type' => \WP_Job_Manager_Post_Types::PT_GUEST_USER,
					'title'     => $email,
				]
			);
			$guest = empty( $guest ) ? false : $guest[0];
		}

		if ( empty( $guest ) ) {
			return false;
		}

		return new self( $guest );
	}

	/**
	 * Create a new guest user for the given email. Returns existing guest user if one exists for the email.
	 *
	 * @param string $email
	 *
	 * @return self|false
	 */
	public static function create( string $email ) {

		$email = sanitize_email( $email );

		if ( ! is_email( $email ) ) {
			return false;
		}

		$existing = self::load( $email );

		if ( $existing ) {
			return $existing;
		}

		$guest_id = wp_insert_post(
			[
				'post_title'  => $email,
				'post_type'   => \WP_Job_Manager_Post_Types::PT_GUEST_USER,
				'post_status' => 'publish',
			]
		);

		return self::load( $guest_id );

	}

	/**
	 * Get a URL token for the guest user. Includes the guest user ID and an access token.
	 *
	 * @return string
	 */
	public function create_token() {

		$at           = new Access_Token( [ 'guest_id' => $this->ID ] );
		$access_token = $at->create( time() + self::TOKEN_EXPIRY );

		return implode( self::TOKEN_SEPARATOR, [ self::TOKEN_PREFIX, $this->ID, $access_token ] );

	}

	/**
	 * Verify a URL token and return the guest user if valid.
	 *
	 * @param string $token URL token.
	 *
	 * @return Guest_User|false
	 */
	public static function get_by_token( $token ) {

		if ( ! is_string( $token ) ) {
			return false;
		}

		$token_parts = explode( self::TOKEN_SEPARATOR, $token );
		if ( count( $token_parts ) !== 3 ) {
			return false;
		}

		[ $prefix, $guest_id, $access_token ] = $token_parts;

		if ( self::TOKEN_PREFIX !== $prefix ) {
			return false;
		}

		$at = new Access_Token( [ 'guest_id' => (int) $guest_id ] );

		if ( ! $at->verify( $access_token ) ) {
			return false;
		}

		return self::load( $guest_id );
	}
}
