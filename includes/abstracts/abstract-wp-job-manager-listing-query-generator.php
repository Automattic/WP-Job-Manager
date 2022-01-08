<?php
/**
 * File containing the class WP_Job_Manager_Listing_Query_Generator.
 *
 * @package wp-job-manager
 * @since 1.35.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Generates the query used to fetch posts for Job Manager and extensions.
 */
abstract class WP_Job_Manager_Listing_Query_Generator {
	/**
	 * Query arguments used to create the `WP_Query` object.
	 *
	 * @var array
	 */
	private $query_args;

	/**
	 * Arguments used to generate the query. Set on instantiation.
	 *
	 * @var array
	 */
	private $args;

	/**
	 * Whether we should check the cache and then save the results. Set on instantiation.
	 *
	 * @var bool
	 */
	private $use_cache;

	/**
	 * Stores if we actually used the cache.
	 *
	 * @var bool
	 */
	private $did_use_cache;

	/**
	 * Query generated.
	 *
	 * @var WP_Query
	 */
	private $query;

	/**
	 * WP_Job_Manager_Listing_Query_Generator constructor.
	 *
	 * @param array $args      Arguments used to generate teh query.
	 * @param bool  $use_cache True if we should use the cache.
	 */
	public function __construct( $args = [], $use_cache = true ) {
		$this->args      = self::parse_args( $args );
		$this->use_cache = $use_cache;

		$this->init();
	}

	/**
	 * Salt used for the cache key.
	 *
	 * @return string
	 */
	abstract protected function get_cache_key_salt();

	/**
	 * Builds the query arguments used for `WP_Query`.
	 *
	 * @return array
	 */
	abstract protected function build_query_args();

	/**
	 * Get the default arguments used to generate the query.
	 *
	 * @abstract
	 * @return array
	 */
	protected static function get_default_args() {
		_doing_it_wrong( __METHOD__, __( 'This method should be implemented in the class.', 'wp-job-manager' ), '1.35.0' );

		return [];
	}

	/**
	 * Get the query arguments used for `WP_Query`.
	 *
	 * @return array
	 */
	protected function get_query_args() {
		if ( null === $this->query_args ) {
			$this->query_args = $this->clean_query_args( $this->build_query_args() );
		}

		return $this->query_args;
	}

	/**
	 * Run any tasks that need to be done right away.
	 */
	protected function init() {
	}

	/**
	 * Get the arguments used to generate the query.
	 *
	 * @return array
	 */
	public function get_args() {
		return $this->args;
	}

	/**
	 * Fires before the query is generated. Can prevent normal generation of `WP_Query` by returning one of its own.
	 *
	 * @return null|WP_Query
	 */
	protected function before_query() {
		return null;
	}

	/**
	 * Fires after the query is generated.
	 */
	protected function after_query() {
	}

	/**
	 * Helper function to generate the `orderby` query argument.
	 *
	 * @return array|string
	 */
	protected function parse_orderby() {
		$args = $this->get_args();

		if ( ! empty( $args['orderby'] ) ) {
			if ( 'featured' === $args['orderby'] ) {
				return [
					'menu_order' => 'ASC',
					'date'       => 'DESC',
					'ID'         => 'DESC',
				];
			}

			if ( 'rand_featured' === $args['orderby'] ) {
				return [
					'menu_order' => 'ASC',
					'rand'       => 'ASC',
				];
			}

			return $args['orderby'];
		}

		// By default, order by date and then ID.
		return [
			'date' => 'DESC',
			'ID'   => 'DESC',
		];
	}

	/**
	 * Returns the default order direction. By default, this will be ignored
	 *
	 * @return string
	 */
	protected function parse_order() {
		$args = $this->get_args();

		if ( isset( $args['order'] ) ) {
			return $args['order'];
		}

		return 'DESC';
	}

	/**
	 * Parses the `offset` argument used in the query.
	 *
	 * @return int
	 */
	protected function parse_offset() {
		$args = $this->get_args();

		if ( isset( $args['offset'] ) ) {
			return absint( $args['offset'] );
		}

		return 0;
	}

	/**
	 * Parses the `posts_per_page` argument used in the query.
	 *
	 * @return int
	 */
	protected function parse_posts_per_page() {
		$args = $this->get_args();

		if ( isset( $args['posts_per_page'] ) ) {
			return intval( $args['posts_per_page'] );
		}

		return 20;
	}

	/**
	 * Parses the `no_found_rows` argument used in the query.
	 *
	 * @return bool|null
	 */
	protected function parse_no_found_rows() {
		$args = $this->get_args();

		if ( $args['posts_per_page'] < 0 ) {
			return true;
		}

		return null;
	}

	/**
	 * Parses the `fields` argument used in the query.
	 *
	 * @return string
	 */
	protected function parse_fields() {
		$args = $this->get_args();

		if ( ! empty( $args['fields'] ) ) {
			return $args['fields'];
		}

		return 'all';
	}

	/**
	 * Clean generated query arguments.
	 *
	 * @param array $query_args Query arguments passed to `WP_Query`.
	 * @return array
	 */
	protected function clean_query_args( $query_args ) {
		// Cleanup.
		$remove_empty = [ 'meta_query', 'tax_query' ];
		$remove_null  = [ 'no_found_rows', 's' ];

		foreach ( $remove_empty as $query_key ) {
			if ( empty( $query_args[ $query_key ] ) ) {
				unset( $query_args[ $query_key ] );
			}
		}

		foreach ( $remove_null as $query_key ) {
			if ( null === $query_args[ $query_key ] ) {
				unset( $query_args[ $query_key ] );
			}
		}

		return $query_args;
	}

	/**
	 * Returns the query object with the result of the query.
	 *
	 * @return WP_Query
	 */
	public function get_query() {
		$this->query         = false;
		$query               = $this->before_query();
		$this->did_use_cache = false;

		if ( $query instanceof WP_Query ) {
			$this->query = $query;
		}elseif ( $this->use_cache ) {
			$this->query         = $this->get_cached_query();
			$this->did_use_cache = true;
		}

		if ( false === $this->query ) {
			$this->query = new WP_Query( $this->get_query_args() );
			$this->save_cache();
		}

		$this->after_query();

		return $this->query;
	}

	/**
	 * True if we actually used the cache when fetching the listing results.
	 *
	 * @return bool
	 */
	public function did_use_cache() {
		return $this->did_use_cache;
	}

	/**
	 * Perform tasks on cached query result. Can be extended by child classes.
	 *
	 * @param WP_Query $query Query that was just re-hydrated from a cached result.
	 * @return WP_Query
	 */
	protected function after_cache_hydration( WP_Query $query ) {
		$args = $this->get_args();

		// Random order is cached so shuffle them. Note: This doesn't really work with pagination.
		if ( 'rand_featured' === $args['orderby'] ) {
			usort(
				$query->posts,
				/**
				 * Helper function to maintain featured status when shuffling results.
				 *
				 * @param WP_Post $a
				 * @param WP_Post $b
				 *
				 * @return bool
				 */
				function ( $a, $b ) {
					if ( -1 === $a->menu_order || -1 === $b->menu_order ) {
						// Left is featured.
						if ( 0 === $b->menu_order ) {
							return -1;
						}
						// Right is featured.
						if ( 0 === $a->menu_order ) {
							return 1;
						}
					}
					return wp_rand( -1, 1 );
				}
			);
		} elseif ( 'rand' === $args['orderby'] ) {
			shuffle( $query->posts );
		}

		return $query;
	}

	/**
	 * Check for results in cache.
	 *
	 * @return bool|WP_Query
	 */
	private function get_cached_query() {
		$cache_key          = $this->get_cache_key();
		$query_args         = $this->get_query_args();
		$cached_query_posts = get_transient( $cache_key );

		if (
			$cached_query_posts
			&& is_object( $cached_query_posts )
			&& isset( $cached_query_posts->max_num_pages )
			&& isset( $cached_query_posts->found_posts )
			&& isset( $cached_query_posts->posts )
			&& is_array( $cached_query_posts->posts )
		) {
			if ( in_array( $query_args['fields'], [ 'ids', 'id=>parent' ], true ) ) {
				// For these special requests, just return the array of results as set.
				$posts = $cached_query_posts->posts;
			} else {
				$posts = array_map( 'get_post', $cached_query_posts->posts );
			}

			$result = new WP_Query();
			$result->parse_query( $query_args );
			$result->posts         = $posts;
			$result->found_posts   = intval( $cached_query_posts->found_posts );
			$result->max_num_pages = intval( $cached_query_posts->max_num_pages );
			$result->post_count    = count( $posts );

			$result = $this->after_cache_hydration( $result );

			return $result;
		}

		return false;
	}

	/**
	 * Save the query result to cache.
	 */
	private function save_cache() {
		if ( ! ( $this->query instanceof WP_Query ) || ! $this->use_cache ) {
			return;
		}

		$cacheable_result                  = [];
		$cacheable_result['posts']         = array_values( $this->query->posts );
		$cacheable_result['found_posts']   = $this->query->found_posts;
		$cacheable_result['max_num_pages'] = $this->query->max_num_pages;

		set_transient( $this->get_cache_key(), wp_json_encode( $cacheable_result ), DAY_IN_SECONDS );
	}

	/**
	 * Get the key to use for caching results.
	 *
	 * @return string
	 */
	private function get_cache_key() {
		return WP_Job_Manager_Cache_Helper::CACHE_PREFIX . md5( wp_json_encode( $this->get_query_args() ) . $this->get_cache_key_salt() );
	}

	/**
	 * Parses the arguments used to generated the query.
	 *
	 * @param array $args Arguments used to generate the query.
	 * @return array
	 */
	private static function parse_args( $args ) {
		return wp_parse_args( $args, static::get_default_args() );
	}

}
