<?php

declare(strict_types=1);
/**
 * Test case for OpenAIProxy class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Core;

use GratisAiAgent\Core\OpenAIProxy;
use WP_UnitTestCase;

/**
 * Test OpenAIProxy functionality.
 *
 * Note: Tests that require a live OpenAI endpoint are not included here.
 * This suite focuses on request building, error handling, and response parsing
 * using WP HTTP API mocking via the pre_http_request filter.
 */
class OpenAIProxyTest extends WP_UnitTestCase {

	/**
	 * Clean up all HTTP and plugin filters after each test to prevent state pollution.
	 */
	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'gratis_ai_agent_max_tools' );
		parent::tear_down();
	}

	/**
	 * Build a minimal OpenAIProxy instance for testing.
	 *
	 * @param array<string, mixed> $overrides Constructor argument overrides.
	 * @return OpenAIProxy
	 */
	private function make_proxy( array $overrides = [] ): OpenAIProxy {
		$defaults = [
			'endpoint_url'      => 'https://api.openai.com/v1',
			'api_key'           => 'test-key',
			'timeout'           => 30,
			'model_id'          => 'gpt-4o',
			'temperature'       => 0.7,
			'max_output_tokens' => 1000,
			'system_instruction' => 'You are a helpful assistant.',
			'history'           => [],
			'abilities'         => [],
		];

		$args = array_merge( $defaults, $overrides );

		return new OpenAIProxy(
			$args['endpoint_url'],
			$args['api_key'],
			$args['timeout'],
			$args['model_id'],
			$args['temperature'],
			$args['max_output_tokens'],
			$args['system_instruction'],
			$args['history'],
			$args['abilities']
		);
	}

	// ── send — empty endpoint ─────────────────────────────────────────────

	/**
	 * send() returns WP_Error when endpoint_url is empty.
	 */
	public function test_send_returns_wp_error_when_endpoint_empty(): void {
		$proxy  = $this->make_proxy( [ 'endpoint_url' => '' ] );
		$result = $proxy->send();

		$this->assertWPError( $result );
		$this->assertSame( 'ai_agent_no_endpoint', $result->get_error_code() );
	}

	// ── send — HTTP error ─────────────────────────────────────────────────

	/**
	 * send() returns WP_Error when wp_remote_post returns WP_Error.
	 */
	public function test_send_returns_wp_error_on_http_failure(): void {
		add_filter(
			'pre_http_request',
			function () {
				return new \WP_Error( 'http_request_failed', 'Connection refused' );
			}
		);

		$proxy  = $this->make_proxy();
		$result = $proxy->send();

		$this->assertWPError( $result );
	}

	// ── send — non-200 response ───────────────────────────────────────────

	/**
	 * send() returns WP_Error for non-200 HTTP response.
	 */
	public function test_send_returns_wp_error_for_non_200_response(): void {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'headers'  => [],
					'body'     => wp_json_encode( [
						'error' => [ 'message' => 'Invalid API key' ],
					] ),
					'response' => [ 'code' => 401, 'message' => 'Unauthorized' ],
					'cookies'  => [],
					'filename' => '',
				];
			}
		);

		$proxy  = $this->make_proxy();
		$result = $proxy->send();

		$this->assertWPError( $result );
		$this->assertSame( 'ai_agent_proxy_error', $result->get_error_code() );
	}

	/**
	 * send() includes error message from API response in WP_Error.
	 */
	public function test_send_includes_api_error_message(): void {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'headers'  => [],
					'body'     => wp_json_encode( [
						'error' => [ 'message' => 'Rate limit exceeded' ],
					] ),
					'response' => [ 'code' => 429, 'message' => 'Too Many Requests' ],
					'cookies'  => [],
					'filename' => '',
				];
			}
		);

		$proxy  = $this->make_proxy();
		$result = $proxy->send();

		$this->assertWPError( $result );
		$this->assertStringContainsString( 'Rate limit exceeded', $result->get_error_message() );
	}

	// ── send — successful response ────────────────────────────────────────

	/**
	 * send() returns SimpleAiResult on successful 200 response.
	 */
	public function test_send_returns_simple_ai_result_on_success(): void {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'headers'  => [],
					'body'     => wp_json_encode( [
						'choices' => [
							[
								'message' => [
									'role'    => 'assistant',
									'content' => 'Hello! How can I help you?',
								],
							],
						],
						'usage'   => [
							'prompt_tokens'     => 10,
							'completion_tokens' => 8,
							'total_tokens'      => 18,
						],
					] ),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'cookies'  => [],
					'filename' => '',
				];
			}
		);

		$proxy  = $this->make_proxy();
		$result = $proxy->send();

		$this->assertInstanceOf( \GratisAiAgent\Core\SimpleAiResult::class, $result );
		$this->assertSame( 'Hello! How can I help you?', $result->toText() );
	}

	// ── send — request body ───────────────────────────────────────────────

	/**
	 * send() sends the correct model ID in the request body.
	 */
	public function test_send_includes_model_id_in_request(): void {
		$captured_body = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args ) use ( &$captured_body ) {
				$captured_body = json_decode( $args['body'], true );
				return [
					'headers'  => [],
					'body'     => wp_json_encode( [
						'choices' => [ [ 'message' => [ 'content' => 'ok' ] ] ],
					] ),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'cookies'  => [],
					'filename' => '',
				];
			},
			10,
			2
		);

		$proxy = $this->make_proxy( [ 'model_id' => 'gpt-4o-mini' ] );
		$proxy->send();

		$this->assertNotNull( $captured_body );
		$this->assertSame( 'gpt-4o-mini', $captured_body['model'] );
	}

	/**
	 * send() includes system instruction as first message.
	 */
	public function test_send_includes_system_instruction(): void {
		$captured_body = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args ) use ( &$captured_body ) {
				$captured_body = json_decode( $args['body'], true );
				return [
					'headers'  => [],
					'body'     => wp_json_encode( [
						'choices' => [ [ 'message' => [ 'content' => 'ok' ] ] ],
					] ),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'cookies'  => [],
					'filename' => '',
				];
			},
			10,
			2
		);

		$proxy = $this->make_proxy( [ 'system_instruction' => 'You are a WordPress expert.' ] );
		$proxy->send();

		$this->assertNotNull( $captured_body );
		$messages = $captured_body['messages'];
		$this->assertSame( 'system', $messages[0]['role'] );
		$this->assertSame( 'You are a WordPress expert.', $messages[0]['content'] );
	}

	/**
	 * send() does not include system message when system_instruction is empty.
	 */
	public function test_send_omits_system_message_when_empty(): void {
		$captured_body = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args ) use ( &$captured_body ) {
				$captured_body = json_decode( $args['body'], true );
				return [
					'headers'  => [],
					'body'     => wp_json_encode( [
						'choices' => [ [ 'message' => [ 'content' => 'ok' ] ] ],
					] ),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'cookies'  => [],
					'filename' => '',
				];
			},
			10,
			2
		);

		$proxy = $this->make_proxy( [ 'system_instruction' => '' ] );
		$proxy->send();

		$this->assertNotNull( $captured_body );
		$messages = $captured_body['messages'];
		// No system message should be present.
		foreach ( $messages as $msg ) {
			$this->assertNotSame( 'system', $msg['role'] );
		}
	}

	/**
	 * send() sets stream=false in request body.
	 */
	public function test_send_sets_stream_false(): void {
		$captured_body = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args ) use ( &$captured_body ) {
				$captured_body = json_decode( $args['body'], true );
				return [
					'headers'  => [],
					'body'     => wp_json_encode( [
						'choices' => [ [ 'message' => [ 'content' => 'ok' ] ] ],
					] ),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'cookies'  => [],
					'filename' => '',
				];
			},
			10,
			2
		);

		$proxy = $this->make_proxy();
		$proxy->send();

		$this->assertNotNull( $captured_body );
		$this->assertFalse( $captured_body['stream'] );
	}

	/**
	 * send() sends Authorization header with API key.
	 */
	public function test_send_includes_authorization_header(): void {
		$captured_headers = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args ) use ( &$captured_headers ) {
				$captured_headers = $args['headers'];
				return [
					'headers'  => [],
					'body'     => wp_json_encode( [
						'choices' => [ [ 'message' => [ 'content' => 'ok' ] ] ],
					] ),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'cookies'  => [],
					'filename' => '',
				];
			},
			10,
			2
		);

		$proxy = $this->make_proxy( [ 'api_key' => 'sk-test-12345' ] );
		$proxy->send();

		$this->assertNotNull( $captured_headers );
		$this->assertStringContainsString( 'sk-test-12345', $captured_headers['Authorization'] );
	}

	/**
	 * send() sends to /chat/completions endpoint.
	 */
	public function test_send_posts_to_chat_completions_endpoint(): void {
		$captured_url = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_url ) {
				$captured_url = $url;
				return [
					'headers'  => [],
					'body'     => wp_json_encode( [
						'choices' => [ [ 'message' => [ 'content' => 'ok' ] ] ],
					] ),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'cookies'  => [],
					'filename' => '',
				];
			},
			10,
			3
		);

		$proxy = $this->make_proxy( [ 'endpoint_url' => 'https://api.openai.com/v1' ] );
		$proxy->send();

		$this->assertNotNull( $captured_url );
		$this->assertStringEndsWith( '/chat/completions', $captured_url );
	}

	/**
	 * send() strips trailing slash from endpoint URL.
	 */
	public function test_send_strips_trailing_slash_from_endpoint(): void {
		$captured_url = null;

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_url ) {
				$captured_url = $url;
				return [
					'headers'  => [],
					'body'     => wp_json_encode( [
						'choices' => [ [ 'message' => [ 'content' => 'ok' ] ] ],
					] ),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'cookies'  => [],
					'filename' => '',
				];
			},
			10,
			3
		);

		// Endpoint with trailing slash.
		$proxy = $this->make_proxy( [ 'endpoint_url' => 'https://api.openai.com/v1/' ] );
		$proxy->send();

		$this->assertNotNull( $captured_url );
		// Should not have double slash.
		$this->assertStringNotContainsString( '//chat', $captured_url );
	}

	// ── gratis_ai_agent_max_tools filter ──────────────────────────────────

	/**
	 * send() respects gratis_ai_agent_max_tools filter.
	 */
	public function test_send_respects_max_tools_filter(): void {
		$captured_body = null;

		add_filter( 'gratis_ai_agent_max_tools', fn() => 0 );

		add_filter(
			'pre_http_request',
			function ( $preempt, $args ) use ( &$captured_body ) {
				$captured_body = json_decode( $args['body'], true );
				return [
					'headers'  => [],
					'body'     => wp_json_encode( [
						'choices' => [ [ 'message' => [ 'content' => 'ok' ] ] ],
					] ),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'cookies'  => [],
					'filename' => '',
				];
			},
			10,
			2
		);

		$proxy = $this->make_proxy();
		$proxy->send();

		$this->assertNotNull( $captured_body );
		// With max_tools=0, no tools key should be present.
		$this->assertArrayNotHasKey( 'tools', $captured_body );
	}
}
