<?php
/**
 * @group helper
 * @group helper-language-packs
 */
class WP_Test_WP_Job_Manager_Helper_Language_Packs extends WPJM_BaseTest {
	public function tear_down() {
		parent::tear_down();
		remove_all_filters( 'pre_http_request' );
	}

	public function testAddUpdatedTranslations_NoPluginVersions_ReturnsEmpty() {
		// Arrange.
		$this->setUpHttpMock( [] );
		$language_packs = new WP_Job_Manager_Helper_Language_Packs( [], [ 'es_ES' ] );
		$transient      = new stdClass();
		$transient->translations = null;

		// Act.
		$language_packs->add_updated_translations( $transient );

		// Assert.
		$this->assertEquals( [], $transient->translations );
	}

	public function testAddUpdatedTranslations_NoLocales_ReturnsEmpty() {
		// Arrange.
		$this->setUpHttpMock( [] );
		$language_packs = new WP_Job_Manager_Helper_Language_Packs( [ 'test-plugin' => '1.0.0' ], [] );
		$transient      = new stdClass();
		$transient->translations = null;

		// Act.
		$language_packs->add_updated_translations( $transient );

		// Assert.
		$this->assertEquals( [], $transient->translations );
	}

	public function testAddUpdatedTranslations_HasTranslations_ReturnsTranslations() {
		// Arrange.
		$sample_data = $this->getSampleData();
		$this->setUpHttpMock($sample_data);
		$language_packs = new WP_Job_Manager_Helper_Language_Packs( [ 'test-plugin' => '1.0.0' ], [ 'es_ES' ] );
		$transient      = new stdClass();
		$transient->translations = null;

		// Act.
		$language_packs->add_updated_translations( $transient );

		// Assert.
		$this->assertNotEmpty( $transient->translations );
		$this->assertArrayHasKey( 'type', $transient->translations[0] );
		$this->assertEquals( 'plugin', $transient->translations[0]['type'] );

		$this->assertArrayHasKey( 'slug', $transient->translations[0] );
		$this->assertEquals( 'test-plugin', $transient->translations[0]['slug'] );

		$this->assertArrayHasKey( 'language', $transient->translations[0] );
		$this->assertEquals( $sample_data['data']['test-plugin'][0]['wp_locale'], $transient->translations[0]['language'] );

		$this->assertArrayHasKey( 'version', $transient->translations[0] );
		$this->assertEquals( $sample_data['data']['test-plugin'][0]['version'], $transient->translations[0]['version'] );

		$this->assertArrayHasKey( 'updated', $transient->translations[0] );
		$this->assertEquals( $sample_data['data']['test-plugin'][0]['last_modified'], $transient->translations[0]['updated'] );

		$this->assertArrayHasKey( 'package', $transient->translations[0] );
		$this->assertEquals( $sample_data['data']['test-plugin'][0]['package'], $transient->translations[0]['package'] );

		$this->assertArrayHasKey( 'autoupdate', $transient->translations[0] );
		$this->assertEquals( 1, $transient->translations[0]['autoupdate'] );
	}

	private function getSampleData() {
		return [
			'data' => [
				'test-plugin' => [
					[
						'wp_locale' => 'es_ES',
						'version'  => '1.0.0',
						'last_modified'  => '2100-01-01 00:00:00',
						'package'  => 'https://translate.wordpress.com/test-plugin/es_ES/1.0.0.zip',
					],
				],
			],
		];
	}

	/**
	 * Set up the HTTP mock.
	 */
	private function setUpHttpMock( $response, $response_code = 200, $expect_call = true ) {
		if ( ! $expect_call ) {
			throw new \Exception( 'No HTTP call was expected' );
		}

		add_filter(
			'pre_http_request',
			function() use ( $response, $response_code ) {
				return [
					'body'     => wp_json_encode( $response ),
					'response' => [
						'code' => $response_code,
					],
				];
			}
		);
	}
}
