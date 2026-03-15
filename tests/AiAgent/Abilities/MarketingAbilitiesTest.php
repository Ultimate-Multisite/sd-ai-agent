<?php
/**
 * Test case for MarketingAbilities class.
 *
 * @package AiAgent
 * @subpackage Tests
 */

namespace AiAgent\Tests\Abilities;

use AiAgent\Abilities\MarketingAbilities;
use WP_UnitTestCase;

/**
 * Test MarketingAbilities handler methods.
 *
 * Note: handle_fetch_url and handle_analyze_headers make real HTTP requests.
 * Tests that require network access are marked to skip if network is unavailable.
 */
class MarketingAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Test handle_fetch_url with empty URL returns error.
	 */
	public function test_handle_fetch_url_empty_url() {
		$result = MarketingAbilities::handle_fetch_url( [ 'url' => '' ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test handle_fetch_url with missing URL returns error.
	 */
	public function test_handle_fetch_url_missing_url() {
		$result = MarketingAbilities::handle_fetch_url( [] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test handle_fetch_url result structure when URL is provided.
	 *
	 * Uses a mock filter to intercept wp_remote_get.
	 */
	public function test_handle_fetch_url_result_structure() {
		// Mock wp_remote_get to return a controlled response.
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'headers'  => new \WpOrg\Requests\Utility\CaseInsensitiveDictionary( [
						'content-type' => 'text/html',
						'server'       => 'nginx',
					] ),
					'body'     => '<html><head><title>Test Page</title><meta name="description" content="Test description"></head><body></body></html>',
					'cookies'  => [],
				];
			},
			10,
			3
		);

		$result = MarketingAbilities::handle_fetch_url( [
			'url' => 'https://example.com/',
		] );

		remove_all_filters( 'pre_http_request' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertArrayHasKey( 'status_code', $result );
		$this->assertArrayHasKey( 'headers', $result );
		$this->assertArrayHasKey( 'title', $result );
		$this->assertArrayHasKey( 'meta_description', $result );
		$this->assertArrayHasKey( 'generator', $result );
		$this->assertArrayHasKey( 'head_content', $result );
		$this->assertSame( 200, $result['status_code'] );
		$this->assertSame( 'Test Page', $result['title'] );
	}

	/**
	 * Test handle_analyze_headers with empty URL returns error.
	 */
	public function test_handle_analyze_headers_empty_url() {
		$result = MarketingAbilities::handle_analyze_headers( [ 'url' => '' ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test handle_analyze_headers with missing URL returns error.
	 */
	public function test_handle_analyze_headers_missing_url() {
		$result = MarketingAbilities::handle_analyze_headers( [] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test handle_analyze_headers result structure with mocked response.
	 */
	public function test_handle_analyze_headers_result_structure() {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'headers'  => new \WpOrg\Requests\Utility\CaseInsensitiveDictionary( [
						'strict-transport-security' => 'max-age=31536000',
						'x-content-type-options'    => 'nosniff',
						'cache-control'             => 'max-age=3600',
						'cf-ray'                    => '12345-LHR',
					] ),
					'body'     => '',
					'cookies'  => [],
				];
			},
			10,
			3
		);

		$result = MarketingAbilities::handle_analyze_headers( [
			'url' => 'https://example.com/',
		] );

		remove_all_filters( 'pre_http_request' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertArrayHasKey( 'status_code', $result );
		$this->assertArrayHasKey( 'security', $result );
		$this->assertArrayHasKey( 'performance', $result );
		$this->assertArrayHasKey( 'cdn', $result );
		$this->assertIsArray( $result['security'] );
		$this->assertIsArray( $result['performance'] );
		$this->assertIsArray( $result['cdn'] );
	}

	/**
	 * Test handle_analyze_headers security section structure.
	 */
	public function test_handle_analyze_headers_security_structure() {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'headers'  => new \WpOrg\Requests\Utility\CaseInsensitiveDictionary( [] ),
					'body'     => '',
					'cookies'  => [],
				];
			},
			10,
			3
		);

		$result = MarketingAbilities::handle_analyze_headers( [
			'url' => 'https://example.com/',
		] );

		remove_all_filters( 'pre_http_request' );

		$this->assertNotEmpty( $result['security'] );
		$security_item = $result['security'][0];
		$this->assertArrayHasKey( 'header', $security_item );
		$this->assertArrayHasKey( 'status', $security_item );
		$this->assertArrayHasKey( 'impact', $security_item );
	}

	/**
	 * Test handle_analyze_headers detects CDN from headers.
	 */
	public function test_handle_analyze_headers_detects_cdn() {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'headers'  => new \WpOrg\Requests\Utility\CaseInsensitiveDictionary( [
						'cf-ray' => '12345-LHR',
					] ),
					'body'     => '',
					'cookies'  => [],
				];
			},
			10,
			3
		);

		$result = MarketingAbilities::handle_analyze_headers( [
			'url' => 'https://example.com/',
		] );

		remove_all_filters( 'pre_http_request' );

		$cdn_providers = array_column( $result['cdn'], 'provider' );
		$this->assertContains( 'Cloudflare', $cdn_providers );
	}
}
