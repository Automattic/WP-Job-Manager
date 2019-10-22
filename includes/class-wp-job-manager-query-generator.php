<?php
/**
 * File containing the class WP_Resume_Manager_Query_Generator.
 *
 * @package wp-job-manager
 * @since 1.34.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Generates the query used to fetch job listings.
 */
class WP_Job_Manager_Query_Generator extends WP_Job_Manager_Listing_Query_Generator {
	/**
	 * Salt used for the cache key.
	 *
	 * @return string
	 */
	protected function get_cache_key_salt() {
		return JOB_MANAGER_VERSION;
	}

	/**
	 * Run any tasks that need to be done right away.
	 */
	protected function init() {
		parent::init();

		$args = $this->get_args();

		/**
		 * Perform actions that need to be done prior to the start of the job listings query.
		 *
		 * @since 1.26.0
		 *
		 * @param array $args Arguments used to retrieve job listings.
		 */
		do_action( 'get_job_listings_init', $args );
	}

	/**
	 * Get the query arguments used for `WP_Query`.
	 *
	 * @return array
	 */
	protected function build_query_args() {
		$args       = $this->get_args();
		$query_args = self::get_default_query_args();

		// Common arguments.
		$query_args['order']          = $this->parse_order();
		$query_args['orderby']        = $this->parse_orderby();
		$query_args['offset']         = $this->parse_offset();
		$query_args['posts_per_page'] = $this->parse_posts_per_page();
		$query_args['fields']         = $this->parse_fields();
		$query_args['no_found_rows']  = $this->parse_no_found_rows();

		// Job listing specific arguments.
		$query_args['s']           = $this->parse_keyword();
		$query_args['post_status'] = $this->parse_post_status();
		$query_args['meta_query']  = $this->generate_meta_query();
		$query_args['tax_query']   = $this->generate_tax_query();

		/** This filter is documented in wp-job-manager.php */
		$query_args['lang'] = apply_filters( 'wpjm_lang', null );

		/**
		 * Filter the query arguments used.
		 *
		 * @param array $query_args Query arguments used for `WP_Query`.
		 * @param array $args       Arguments used for generating the current query arguments.
		 */
		$query_args = apply_filters( 'job_manager_get_listings', $query_args, $args );

		return $query_args;
	}

	/**
	 * Adds join and where query for keywords.
	 *
	 * @since 1.35.0
	 *
	 * @param string $search
	 * @return string
	 */
	public function get_job_listings_keyword_search( $search ) {
		global $wpdb;

		$query_args          = $this->get_query_args();
		$job_manager_keyword = $query_args['s'];

		// Searchable Meta Keys: set to empty to search all meta keys.
		$searchable_meta_keys = [
			'_job_location',
			'_company_name',
			'_application',
			'_company_name',
			'_company_tagline',
			'_company_website',
			'_company_twitter',
		];

		$searchable_meta_keys = apply_filters( 'job_listing_searchable_meta_keys', $searchable_meta_keys );

		// Set Search DB Conditions.
		$conditions = [];

		// Search Post Meta.
		if ( apply_filters( 'job_listing_search_post_meta', true ) ) {

			// Only selected meta keys.
			if ( $searchable_meta_keys ) {
				$conditions[] = "{$wpdb->posts}.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ( '" . implode( "','", array_map( 'esc_sql', $searchable_meta_keys ) ) . "' ) AND meta_value LIKE '%" . esc_sql( $job_manager_keyword ) . "%' )";
			} else {
				// No meta keys defined, search all post meta value.
				$conditions[] = "{$wpdb->posts}.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value LIKE '%" . esc_sql( $job_manager_keyword ) . "%' )";
			}
		}

		// Search taxonomy.
		$conditions[] = "{$wpdb->posts}.ID IN ( SELECT object_id FROM {$wpdb->term_relationships} AS tr LEFT JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id LEFT JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id WHERE t.name LIKE '%" . esc_sql( $job_manager_keyword ) . "%' )";

		/**
		 * Filters the conditions to use when querying job listings. Resulting array is joined with OR statements.
		 *
		 * @since 1.26.0
		 *
		 * @param array  $conditions          Conditions to join by OR when querying job listings.
		 * @param string $job_manager_keyword Search query.
		 */
		$conditions = apply_filters( 'job_listing_search_conditions', $conditions, $job_manager_keyword );
		if ( empty( $conditions ) ) {
			return $search;
		}

		$conditions_str = implode( ' OR ', $conditions );

		if ( ! empty( $search ) ) {
			$search = preg_replace( '/^ AND /', '', $search );
			$search = " AND ( {$search} OR ( {$conditions_str} ) )";
		} else {
			$search = " AND ( {$conditions_str} )";
		}

		return $search;
	}

	/**
	 * Generates the meta query used by `WP_Query`.
	 *
	 * @return array
	 */
	private function generate_meta_query() {
		$args       = $this->get_args();
		$meta_query = [];

		if ( ! empty( $args['search_location'] ) ) {
			$location_meta_keys = [ 'geolocation_formatted_address', '_job_location', 'geolocation_state_long' ];
			$location_search    = [ 'relation' => 'OR' ];
			foreach ( $location_meta_keys as $meta_key ) {
				$location_search[] = [
					'key'     => $meta_key,
					'value'   => $args['search_location'],
					'compare' => 'like',
				];
			}
			$meta_query[] = $location_search;
		}

		if ( ! is_null( $args['featured'] ) ) {
			$meta_query[] = [
				'key'     => '_featured',
				'value'   => '1',
				'compare' => $args['featured'] ? '=' : '!=',
			];
		}

		if ( ! is_null( $args['filled'] ) || 1 === absint( get_option( 'job_manager_hide_filled_positions' ) ) ) {
			$meta_query[] = [
				'key'     => '_filled',
				'value'   => '1',
				'compare' => $args['filled'] ? '=' : '!=',
			];
		}

		return $meta_query;
	}

	/**
	 * Generates the taxonomy query used by `WP_Query`.
	 *
	 * @return array
	 */
	private function generate_tax_query() {
		$args      = $this->get_args();
		$tax_query = [];

		if ( ! empty( $args['job_types'] ) ) {
			$tax_query[] = [
				'taxonomy' => 'job_listing_type',
				'field'    => 'slug',
				'terms'    => $args['job_types'],
			];
		}

		if ( ! empty( $args['search_categories'] ) ) {
			$field       = is_numeric( $args['search_categories'][0] ) ? 'term_id' : 'slug';
			$operator    = 'all' === get_option( 'job_manager_category_filter_type', 'all' ) && count( $args['search_categories'] ) > 1 ? 'AND' : 'IN';
			$tax_query[] = [
				'taxonomy'         => 'job_listing_category',
				'field'            => $field,
				'terms'            => array_values( $args['search_categories'] ),
				'include_children' => 'AND' !== $operator,
				'operator'         => $operator,
			];
		}

		return $tax_query;
	}

	/**
	 * Parses the `s` argument used in the query.
	 *
	 * @return string|null
	 */
	private function parse_keyword() {
		$args                = $this->get_args();
		$job_manager_keyword = sanitize_text_field( $args['search_keywords'] );

		if ( ! empty( $job_manager_keyword ) && strlen( $job_manager_keyword ) >= apply_filters( 'job_manager_get_listings_keyword_length_threshold', 2 ) ) {
			return $job_manager_keyword;
		}

		return null;
	}

	/**
	 * Parses the `post_status` argument used in the query.
	 *
	 * @return string|array
	 */
	private function parse_post_status() {
		$args = $this->get_args();

		if ( ! empty( $args['post_status'] ) ) {
			return $args['post_status'];
		} elseif ( 0 === intval( get_option( 'job_manager_hide_expired', get_option( 'job_manager_hide_expired_content', 1 ) ) ) ) {
			return [ 'publish', 'expired' ];
		}

		return 'publish';
	}

	/**
	 * Fires before the query is generated. Can prevent normal generation of `WP_Query` by returning one of its own.
	 *
	 * @return void|WP_Query
	 */
	protected function before_query() {
		$args       = $this->get_args();
		$query_args = $this->get_query_args();

		if ( ! empty( $query_args['s'] ) ) {
			add_filter( 'posts_search', [ $this, 'get_job_listings_keyword_search' ] );
		}

		/**
		 * Runs right before job listings are retrieved.
		 *
		 * @since 1.11.0
		 * @since 1.35.0 Added query generator object parameter.
		 *
		 * @param array                          $query_args      Query arguments used for `WP_Query`.
		 * @param array                          $args            Arguments used for generating the current query arguments.
		 * @param WP_Job_Manager_Query_Generator $query_generator Query generator object used to generate this `WP_Query`.
		 */
		do_action( 'before_get_job_listings', $query_args, $args, $this );
	}


	/**
	 * Fires after the query is generated.
	 */
	protected function after_query() {
		$args       = $this->get_args();
		$query_args = $this->get_query_args();

		remove_filter( 'posts_search', [ $this, 'get_job_listings_keyword_search' ] );

		/**
		 * Runs right before job listings are retrieved.
		 *
		 * @since 1.11.0
		 * @since 1.35.0 Added query generator object parameter.
		 *
		 * @param array                          $query_args      Query arguments used for `WP_Query`.
		 * @param array                          $args            Arguments used for generating the current query arguments.
		 * @param WP_Job_Manager_Query_Generator $query_generator Query generator object used to generate this `WP_Query`.
		 */
		do_action( 'after_get_job_listings', $query_args, $args, $this );
	}

	/**
	 * Get the default query arguments used for this `WP_Query`.
	 *
	 * @return array
	 */
	private static function get_default_query_args() {
		return [
			'post_type'              => 'job_listing',
			'ignore_sticky_posts'    => 1,
			'tax_query'              => [],
			'meta_query'             => [],
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'cache_results'          => false,
			'fields'                 => 'all',
		];
	}

	/**
	 * Get the default arguments used to generate the query.
	 *
	 * @return array
	 */
	protected static function get_default_args() {
		return [
			'search_location'   => '',
			'search_keywords'   => '',
			'search_categories' => [],
			'job_types'         => [],
			'post_status'       => [],
			'offset'            => 0,
			'posts_per_page'    => 20,
			'orderby'           => 'date',
			'order'             => 'DESC',
			'featured'          => null,
			'filled'            => null,
			'fields'            => 'all',
		];
	}
}
