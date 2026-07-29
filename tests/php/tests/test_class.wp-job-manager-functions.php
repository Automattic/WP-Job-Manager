	/**
	 * @since 2.4.3
	 * @covers ::job_manager_dropdown_categories
	 */
	public function test_dropdown_categories_output_params_share_cache_key() {
		wp_create_term( 'cat-alpha', \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY );
		wp_create_term( 'cat-beta', \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY );

		$baseline = [
			'taxonomy'   => \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY,
			'orderby'    => 'id',
			'order'      => 'ASC',
			'hide_empty' => 0,
			'echo'       => 0,
			'selected'   => 0,
		];

		// First call populates the transient cache.
		$first = job_manager_dropdown_categories( $baseline );

		// Vary only output-only args; the cached term result is reused, so option entries must be identical.
		$output_variants = [
			[ 'selected' => 999 ],
			[ 'name' => 'different-name' ],
			[ 'id' => 'different-id' ],
			[ 'class' => 'different-class' ],
			[ 'placeholder' => 'Pick one' ],
			[ 'multiple' => false ],
		];

		foreach ( $output_variants as $variant ) {
			$args    = array_merge( $baseline, $variant );
			$output  = job_manager_dropdown_categories( $args );
			// Extract <option value="X">name</option> entries from both calls.
			preg_match_all( '#<option[^>]*value="(\d+)"[^>]*>(.*?)</option>#', $first, $first_options );
			preg_match_all( '#<option[^>]*value="(\d+)"[^>]*>(.*?)</option>#', $output, $variant_options );
			$this->assertSame(
				$first_options,
				$variant_options,
				sprintf( 'Output-only args should not affect cached term options (variant: %s).', wp_json_encode( $variant ) )
			);
		}
	}

	/**
	 * @since 2.4.3
	 * @covers ::job_manager_dropdown_categories
	 */
	public function test_dropdown_categories_query_params_vary_cache_key() {
		wp_create_term( 'cat-alpha', \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY );
		wp_create_term( 'cat-beta', \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY );

		$baseline = [
			'taxonomy'   => \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY,
			'orderby'    => 'id',
			'order'      => 'ASC',
			'hide_empty' => 0,
			'echo'       => 0,
		];

		$first = job_manager_dropdown_categories( $baseline );

		// Exclude the first term — result set must differ from baseline.
		$all_term_ids  = get_terms(
			[
				'taxonomy'   => \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY,
				'fields'     => 'ids',
				'hide_empty' => false,
			]
		);
		$first_term_id = current( $all_term_ids );
		$excluded      = job_manager_dropdown_categories( array_merge( $baseline, [ 'exclude' => [ $first_term_id ] ] ) );
		preg_match_all( '#<option[^>]*value="(\d+)"[^>]*>(.*?)</option>#', $excluded, $excluded_options );
		$this->assertNotEquals(
			wp_json_encode( $this->extract_options( $first ) ),
			wp_json_encode( $this->extract_options( $excluded ) ),
			'Excluding a term via query args must change the cached result.'
		);
	}

	/**
	 * @since 2.4.3
	 * @covers ::job_manager_dropdown_categories
	 */
	public function test_dropdown_categories_slug_permutations_share_cache_key() {
		$cat_a = wp_create_term( 'cat-a', \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY );
		$cat_b = wp_create_term( 'cat-b', \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY );

		$args_a = [
			'taxonomy'              => \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY,
			'orderby'               => 'id',
			'order'                 => 'ASC',
			'hide_empty'            => 0,
			'echo'                  => 0,
			'search_category_slugs' => [ 'cat-a', 'cat-b' ],
		];
		$args_b = array_merge( $args_a, [ 'search_category_slugs' => [ 'cat-b', 'cat-a' ] ] );

		$output_a = job_manager_dropdown_categories( $args_a );
		$output_b = job_manager_dropdown_categories( $args_b );

		$this->assertSame(
			$this->extract_options( $output_a ),
			$this->extract_options( $output_b ),
			'Slug order permutations should produce the same cached term options.'
		);
	}

	/**
	 * Extract ordered list of [value, label] pairs from a dropdown HTML string.
	 *
	 * @since 2.4.3
	 *
	 * @param string $html Dropdown markup.
	 * @return array[]
	 */
	private function extract_options( $html ) {
		preg_match_all( '#<option[^>]*value="(\d+)"[^>]*>(.*?)</option>#', $html, $matches, PREG_SET_ORDER );

		return array_map(
			function ( $m ) {
				return [ (int) $m[1], $m[2] ];
			},
			$matches
		);
	}

	/**
	 * @since $$next-version$$
	 * @covers ::job_manager_get_accept_file_types
	 */
	public function test_get_accept_file_types_prefixes_extensions_with_a_dot() {
		$this->assertSame(
			'.jpg,.jpeg,.png',
			job_manager_get_accept_file_types(
				[
					'jpg'  => 'image/jpeg',
					'jpeg' => 'image/jpeg',
					'png'  => 'image/png',
				]
			)
		);
	}

	/**
	 * Pipe-separated extension groups, as used by job_manager_get_allowed_mime_types(), are split into one token each.
	 *
	 * @since $$next-version$$
	 * @covers ::job_manager_get_accept_file_types
	 */
	public function test_get_accept_file_types_splits_pipe_separated_groups() {
		$this->assertSame(
			'.jpg,.jpeg,.jpe,.gif',
			job_manager_get_accept_file_types(
				[
					'jpg|jpeg|jpe' => 'image/jpeg',
					'gif'          => 'image/gif',
				]
			)
		);
	}

	/**
	 * @since $$next-version$$
	 * @covers ::job_manager_get_accept_file_types
	 */
	public function test_get_accept_file_types_deduplicates_and_normalizes_leading_dots() {
		$this->assertSame(
			'.jpg,.png',
			job_manager_get_accept_file_types(
				[
					'.jpg'    => 'image/jpeg',
					'jpg|png' => 'image/png',
				]
			)
		);
	}

	/**
	 * Fields may pass a plain list of mime types, or a mime-type-keyed map, instead of an extension-keyed map. The
	 * `accept` attribute takes mime types too, so pass them through rather than emitting nonsense like `.0`.
	 *
	 * @since $$next-version$$
	 * @covers ::job_manager_get_accept_file_types
	 */
	public function test_get_accept_file_types_passes_through_mime_types() {
		$this->assertSame( 'image/jpeg,image/png', job_manager_get_accept_file_types( [ 'image/jpeg', 'image/png' ] ) );
		$this->assertSame( 'application/pdf', job_manager_get_accept_file_types( [ 'application/pdf' => 'application/pdf' ] ) );
	}

	/**
	 * @since $$next-version$$
	 * @covers ::job_manager_get_accept_file_types
	 */
	public function test_get_accept_file_types_empty_when_nothing_is_allowed() {
		$this->assertSame( '', job_manager_get_accept_file_types( [] ) );
	}
}
