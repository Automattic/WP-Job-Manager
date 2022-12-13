<?php
/**
 * File containing the class WP_Job_Manager_Helper_Language_Packs.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Helper_Language_Packs
 */
class WP_Job_Manager_Helper_Language_Packs {
	const TRANSLATION_UPDATES_URL  = 'https://translate.wordpress.com/api/translations-updates/wp-job-manager';
	const REMOTE_PACKAGE_TRANSIENT = 'wp-job-manager-translations-';

	/**
	 * The plugin versions, keyed with the plugin slug.
	 *
	 * @var string[]
	 */
	private $plugin_versions;

	/**
	 * Locales to manage language packs for.
	 *
	 * @var string[]
	 */
	private $locales;

	/**
	 * Request cache of the language pack updates available.
	 *
	 * @var array|null
	 */
	private $language_pack_updates_cache;

	/**
	 * Class constructor.
	 *
	 * @param array[]  $plugin_versions Plugin versions, keyed by plugin slug.
	 * @param string[] $locales         Locales to manage language packs for.
	 */
	public function __construct( array $plugin_versions, array $locales ) {
		$this->plugin_versions = $plugin_versions;
		$this->locales         = $locales;

		if ( empty( $plugin_versions ) || empty( $locales ) ) {
			return;
		}

		add_filter( 'site_transient_update_plugins', [ $this, 'add_updated_translations' ] );
	}

	/**
	 * Adds the plugin's language pack updates to the `update_plugins` transient.
	 *
	 * @internal
	 *
	 * @param \stdClass $transient Current value of `update_plugins` transient.
	 * @return \stdClass
	 */
	public function add_updated_translations( $transient ) {
		if ( empty( $transient ) ) {
			return $transient;
		}

		/**
		 * Allows for disabling language packs downloading.
		 *
		 * @since 1.39.0
		 *
		 * @param bool $disable_language_packs Whether to disable language packs. Default false.
		 */
		if ( apply_filters( 'wp_job_manager_helper_disable_language_packs', false ) ) {
			return $transient;
		}

		$translations            = $this->get_language_pack_updates();
		$transient->translations = array_merge( $transient->translations ?? [], $translations );

		return $transient;
	}

	/**
	 * Get translations updates from our translation pack server.
	 *
	 * @return array Update data {plugin_slug => data}
	 */
	private function get_language_pack_updates() {
		if ( ! is_null( $this->language_pack_updates_cache ) ) {
			return $this->language_pack_updates_cache;
		}

		if ( empty( $this->plugin_versions ) || empty( $this->locales ) ) {
			return [];
		}

		// Check if we've cached this in the last day.
		$transient_key = self::REMOTE_PACKAGE_TRANSIENT . md5( wp_json_encode( [ $this->plugin_versions, $this->locales ] ) );
		$data          = get_site_transient( $transient_key );
		if ( false !== $data && is_array( $data ) ) {
			return $this->parse_language_pack_translations( $data );
		}

		// Set the timeout for the request.
		$timeout = 5;
		if ( wp_doing_cron() ) {
			$timeout = 30;
		}

		$plugins = [];
		foreach ( $this->plugin_versions as $slug => $plugin_version ) {
			$plugins[ $slug ] = [ 'version' => $plugin_version ];
		}

		$request_body = [
			'locales' => $this->locales,
			'plugins' => $plugins,
		];

		$raw_response = wp_remote_post(
			self::TRANSLATION_UPDATES_URL,
			[
				'body'    => wp_json_encode( $request_body ),
				'headers' => [ 'Content-Type: application/json' ],
				'timeout' => $timeout,
			]
		);

		// Something unexpected happened on the translation server side.
		$response_code = wp_remote_retrieve_response_code( $raw_response );
		if ( 200 !== $response_code ) {
			return [];
		}

		$response = json_decode( wp_remote_retrieve_body( $raw_response ), true );
		// API error, api returned but something was wrong.
		if ( array_key_exists( 'success', $response ) && false === $response['success'] ) {
			return [];
		}

		$this->language_pack_updates_cache = $this->parse_language_pack_translations( $response['data'] );
		set_site_transient( $transient_key, $response['data'], DAY_IN_SECONDS );

		return $this->language_pack_updates_cache;
	}

	/**
	 * Parse the language pack translations.
	 *
	 * @param array $update_data Update data from translate.wordpress.com.
	 *
	 * @return array
	 */
	private function parse_language_pack_translations( $update_data ) {
		$installed_translations = wp_get_installed_translations( 'plugins' );
		$translations           = [];

		foreach ( $update_data as $plugin_name => $language_packs ) {
			foreach ( $language_packs as $language_pack ) {
				// Maybe we have this language pack already installed so lets check revision date.
				if ( array_key_exists( $plugin_name, $installed_translations ) && array_key_exists( $language_pack['wp_locale'], $installed_translations[ $plugin_name ] ) ) {
					$installed_translation_revision_time = new \DateTime( $installed_translations[ $plugin_name ][ $language_pack['wp_locale'] ]['PO-Revision-Date'] );
					$new_translation_revision_time       = new \DateTime( $language_pack['last_modified'] );

					// Skip if translation language pack is not newer than what is installed already.
					if ( $new_translation_revision_time <= $installed_translation_revision_time ) {
						continue;
					}
				}

				$translations[] = [
					'type'       => 'plugin',
					'slug'       => $plugin_name,
					'language'   => $language_pack['wp_locale'],
					'version'    => $language_pack['version'],
					'updated'    => $language_pack['last_modified'],
					'package'    => $language_pack['package'],
					'autoupdate' => true,
				];
			}
		}

		return $translations;
	}
}
