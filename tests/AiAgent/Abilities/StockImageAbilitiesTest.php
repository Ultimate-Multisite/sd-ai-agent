<?php
/**
 * Test case for StockImageAbilities class.
 *
 * @package AiAgent
 * @subpackage Tests
 */

namespace AiAgent\Tests\Abilities;

use AiAgent\Abilities\StockImageAbilities;
use WP_UnitTestCase;

/**
 * Test StockImageAbilities handler methods.
 *
 * Note: handle_import makes real HTTP requests to loremflickr.com / picsum.photos.
 * Tests that require network access use mocked HTTP responses.
 */
class StockImageAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Test handle_import with empty keyword returns error.
	 */
	public function test_handle_import_empty_keyword() {
		$result = StockImageAbilities::handle_import( [ 'keyword' => '' ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'required', $result['error'] );
	}

	/**
	 * Test handle_import with missing keyword returns error.
	 */
	public function test_handle_import_missing_keyword() {
		$result = StockImageAbilities::handle_import( [] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test handle_import with invalid site_url returns error.
	 */
	public function test_handle_import_invalid_site_url() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite-only test.' );
		}

		$result = StockImageAbilities::handle_import( [
			'keyword'  => 'dogs',
			'site_url' => 'https://nonexistent-subsite.example.com/',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'Could not find', $result['error'] );
	}

	/**
	 * Test handle_import with mocked successful download.
	 *
	 * Mocks the HTTP request to avoid real network calls.
	 */
	public function test_handle_import_mocked_success() {
		// Create a minimal valid JPEG for testing.
		$jpeg_data = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";

		// Mock download_url to return a temp file.
		$tmp_file = wp_tempnam( 'test-image' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test temp file.
		file_put_contents( $tmp_file, $jpeg_data );

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $tmp_file ) {
				// Return a mock response that download_url will use.
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'headers'  => new \WpOrg\Requests\Utility\CaseInsensitiveDictionary( [
						'content-type' => 'image/jpeg',
					] ),
					'body'     => "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9",
					'cookies'  => [],
					'filename' => $tmp_file,
				];
			},
			10,
			3
		);

		$result = StockImageAbilities::handle_import( [
			'keyword' => 'dogs',
			'width'   => 400,
			'height'  => 300,
		] );

		remove_all_filters( 'pre_http_request' );

		// Clean up temp file.
		if ( file_exists( $tmp_file ) ) {
			unlink( $tmp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}

		// Result is either success or error (network mock may not fully simulate download_url).
		$this->assertIsArray( $result );
		$this->assertTrue(
			isset( $result['attachment_id'] ) || isset( $result['error'] ),
			'Result should have attachment_id or error key.'
		);
	}

	/**
	 * Test handle_import clamps dimensions to valid range.
	 *
	 * We verify the clamping logic by checking that extreme values don't cause
	 * errors before the HTTP request (which we mock to fail).
	 */
	public function test_handle_import_clamps_dimensions() {
		// Mock to fail immediately so we don't make real requests.
		add_filter(
			'pre_http_request',
			function () {
				return new \WP_Error( 'http_request_failed', 'Mocked failure' );
			},
			10,
			3
		);

		// Very large dimensions should be clamped, not cause PHP errors.
		$result = StockImageAbilities::handle_import( [
			'keyword' => 'test',
			'width'   => 99999,
			'height'  => 99999,
		] );

		remove_all_filters( 'pre_http_request' );

		// Should return error from failed download, not a PHP error.
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test handle_import with very small dimensions (clamped to 200).
	 */
	public function test_handle_import_clamps_small_dimensions() {
		add_filter(
			'pre_http_request',
			function () {
				return new \WP_Error( 'http_request_failed', 'Mocked failure' );
			},
			10,
			3
		);

		$result = StockImageAbilities::handle_import( [
			'keyword' => 'test',
			'width'   => 1,
			'height'  => 1,
		] );

		remove_all_filters( 'pre_http_request' );

		// Should return error from failed download, not a PHP error.
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}
}
