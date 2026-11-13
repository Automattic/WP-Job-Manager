<?php
/**
 * Checks every field of the job submission form at once, on every template that prints a
 * listing, for listings whose author is not trusted.
 *
 * The fields are read from the form, so a field added later is covered without anyone
 * remembering to add a test for it. Each field is filled with text that would be processed
 * if a template printed it without preparing it for display:
 *
 * - a shortcode, which must be shown as text and never run;
 * - encoded markup, which must never end up in an attribute of a media element or frame,
 *   where a player or script reads the decoded value back.
 *
 * @package wp-job-manager/tests
 */

class Tests_Listing_Field_Sweep extends WPJM_BaseTest {

	const SHORTCODE_PREFIX = 'wpjm_sweep_';

	/**
	 * Shortcodes that ran, by field.
	 *
	 * @var string[]
	 */
	private $ran = [];

	/**
	 * Shortcodes registered by the test.
	 *
	 * @var string[]
	 */
	private $shortcodes = [];

	public function setUp(): void {
		parent::setUp();

		update_option( 'job_manager_enable_salary', '1' );
		update_option( 'job_manager_enable_salary_currency', '1' );
		update_option( 'job_manager_enable_salary_unit', '1' );
		update_option( 'job_manager_enable_remote_position', '1' );

		if ( ! class_exists( 'WP_Job_Manager_Form' ) ) {
			include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		}
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';
	}

	public function tearDown(): void {
		foreach ( $this->shortcodes as $shortcode ) {
			remove_shortcode( $shortcode );
		}

		// Leave the form to build its fields again for whichever test uses it next.
		$fields_property = new ReflectionProperty( WP_Job_Manager_Form_Submit_Job::instance(), 'fields' );
		if ( PHP_VERSION_ID < 80100 ) {
			$fields_property->setAccessible( true );
		}
		$fields_property->setValue( WP_Job_Manager_Form_Submit_Job::instance(), [] );

		parent::tearDown();
	}

	public function data_untrusted_authors() {
		return [
			'guest'      => [ false ],
			'subscriber' => [ 'subscriber' ],
			'employer'   => [ 'employer' ],
		];
	}

	/**
	 * @dataProvider data_untrusted_authors
	 */
	public function test_no_submitted_field_is_processed_on_any_template( $role ) {
		$meta = $this->get_submitted_field_values();

		// The sweep is only worth something if it reaches the fields. These are the ones the
		// form has today; the list may grow, and must not silently shrink.
		foreach ( [ '_job_location', '_application', '_job_salary', '_job_salary_currency', '_company_name', '_company_website', '_company_tagline', '_company_video', '_company_twitter' ] as $expected ) {
			$this->assertArrayHasKey( $expected, $meta, 'The sweep should cover this field.' );
			$this->assertNotSame( '', $meta[ $expected ], 'The field should keep some of the submitted text, or the sweep proves nothing about it.' );
		}

		$job_id = $this->factory->job_listing->create(
			[
				'post_author'  => $role ? $this->factory->user->create( [ 'role' => $role ] ) : 0,
				'post_content' => 'A description [' . $this->register_shortcode( 'job_description' ) . '] with brackets.',
			]
		);
		foreach ( $meta as $meta_key => $value ) {
			update_post_meta( $job_id, $meta_key, wp_slash( $value ) );
		}

		$outputs = $this->render_everywhere( $job_id );

		foreach ( $outputs as $where => $output ) {
			$this->assertNotSame( '', trim( $output ), "Nothing was rendered for: $where" );

			// Templates are printed inside content that WordPress processes afterwards, and themes
			// and page builders often process it more than once. Whatever a template printed must
			// survive that without any field's text being run.
			do_shortcode( do_shortcode( $output ) );

			$this->assertSame( [], $this->ran, "A field's text was run as a shortcode in: $where" );
			$this->assertSame( [], $this->find_markup_in_media_attributes( $output ), "A field's text reached a media element as markup in: $where" );
		}

		$this->assertStringContainsString( 'T-company_name', $outputs['single listing page'], 'The listing fields should have been printed.' );
	}

	/**
	 * Builds a value for every text and URL field of the submission form, sanitized the way
	 * the form sanitizes a submission.
	 *
	 * @return array Meta values, keyed by meta key.
	 */
	private function get_submitted_field_values() {
		$form            = WP_Job_Manager_Form_Submit_Job::instance();
		$fields_property = new ReflectionProperty( $form, 'fields' );
		$sanitize        = new ReflectionMethod( $form, 'sanitize_posted_field' );
		if ( PHP_VERSION_ID < 80100 ) {
			$fields_property->setAccessible( true );
			$sanitize->setAccessible( true );
		}

		// The form keeps the fields it built first, which may have been with other settings.
		$fields_property->setValue( $form, [] );
		$form->init_fields();

		$meta = [];
		foreach ( $fields_property->getValue( $form ) as $group_fields ) {
			foreach ( $group_fields as $key => $field ) {
				// The title and description are post fields with their own tests, and the other
				// types don't take free text.
				if ( 'text' !== $field['type'] || 'job_title' === $key ) {
					continue;
				}

				$shortcode = $this->register_shortcode( $key );

				$sanitizer = isset( $field['sanitizer'] ) ? $field['sanitizer'] : null;
				$is_url    = in_array( $sanitizer, [ 'url', 'url_or_email' ], true );
				$submitted = $is_url
					? "http://example.com/&lt;wpjm-sweep-$key&gt;&#60;wpjm-sweep-$key&#62;[$shortcode]?x#.mp4"
					: "T-$key [$shortcode] &lt;wpjm-sweep-$key&gt; &#60;wpjm-sweep-$key&#62;";

				$meta[ '_' . $key ] = $sanitize->invoke( $form, wp_slash( $submitted ), $sanitizer );
			}
		}

		return $meta;
	}

	/**
	 * Registers a shortcode that records the field it was found in when it runs.
	 *
	 * @param string $key Field key.
	 * @return string Shortcode name.
	 */
	private function register_shortcode( $key ) {
		$shortcode          = self::SHORTCODE_PREFIX . $key;
		$this->shortcodes[] = $shortcode;
		add_shortcode(
			$shortcode,
			function () use ( $key ) {
				$this->ran[] = $key;

				return '';
			}
		);

		return $shortcode;
	}

	/**
	 * Renders the listing through each template that prints listing fields.
	 *
	 * @param int $job_id Job listing ID.
	 * @return string[] Output, keyed by a description of where it came from.
	 */
	private function render_everywhere( $job_id ) {
		global $post, $wp_query;

		$outputs = [];

		$this->login_as_admin();
		$wp_query = new WP_Query( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			[
				'p'         => $job_id,
				'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING,
			]
		);
		$outputs['single listing page'] = '';
		while ( $wp_query->have_posts() ) {
			$wp_query->the_post();
			ob_start();
			the_content();
			$outputs['single listing page'] .= ob_get_clean();
		}
		wp_reset_postdata();

		$templates = [
			'listing in a list'    => [ 'content', 'job_listing' ],
			'listing in a widget'  => [ 'content-widget', 'job_listing' ],
			'listing summary'      => [ 'content-summary', 'job_listing' ],
			'single listing alone' => [ 'content-single', 'job_listing' ],
		];
		foreach ( $templates as $where => $template ) {
			$post = get_post( $job_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			setup_postdata( $post );
			ob_start();
			get_job_manager_template_part( $template[0], $template[1] );
			$outputs[ $where ] = ob_get_clean();
		}

		$post = get_post( $job_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );
		ob_start();
		get_job_manager_template( 'job-preview.php', [ 'form' => WP_Job_Manager_Form_Submit_Job::instance() ] );
		$outputs['submission preview'] = ob_get_clean();

		wp_reset_postdata();
		$outputs['[job] shortcode'] = do_shortcode( '[job id="' . $job_id . '"]' );

		return $outputs;
	}

	/**
	 * Finds attributes of media elements and frames whose decoded value has the sweep's markup.
	 *
	 * @param string $html Rendered output.
	 * @return string[] Descriptions of what was found.
	 */
	private function find_markup_in_media_attributes( $html ) {
		$found = [];
		$tags  = new WP_HTML_Tag_Processor( $html );

		while ( $tags->next_tag() ) {
			if ( ! in_array( $tags->get_tag(), [ 'VIDEO', 'AUDIO', 'SOURCE', 'TRACK', 'EMBED', 'OBJECT', 'IFRAME' ], true ) ) {
				continue;
			}

			foreach ( (array) $tags->get_attribute_names_with_prefix( '' ) as $name ) {
				$value = $tags->get_attribute( $name );
				if ( is_string( $value ) && false !== strpos( $value, '<wpjm-sweep-' ) ) {
					$found[] = strtolower( $tags->get_tag() ) . ' ' . $name . ': ' . $value;
				}
			}
		}

		return $found;
	}
}
