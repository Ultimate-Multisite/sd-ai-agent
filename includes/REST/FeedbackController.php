<?php

declare(strict_types=1);
/**
 * REST API controller for the feedback-report send endpoint.
 *
 * Accepts POST /gratis-ai-agent/v1/feedback/send, builds a sanitized payload
 * (including surrounding message context for thumbs_down reports), and forwards
 * it to the operator-configured feedback receiver endpoint.
 *
 * If no endpoint URL is configured or feedback is disabled, the request is
 * acknowledged silently so the UI never shows an error to the user.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

use GratisAiAgent\Core\Database;
use GratisAiAgent\Core\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class FeedbackController {

	use PermissionTrait;

	const NAMESPACE = 'gratis-ai-agent/v1';

	/** Number of messages to include before/after the flagged message. */
	const CONTEXT_WINDOW = 2;

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		$instance = new self();

		register_rest_route(
			self::NAMESPACE,
			'/feedback/send',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_send' ),
				'permission_callback' => array( $instance, 'check_chat_permission' ),
				'args'                => array(
					'report_type'      => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'user_description' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'session_id'       => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 0,
					),
					'message_index'    => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => -1,
					),
				),
			)
		);
	}

	/**
	 * Handle POST /feedback/send.
	 *
	 * Builds a report payload and forwards it to the configured receiver endpoint.
	 * Returns HTTP 200 in all non-fatal cases — the UI should never show a hard
	 * error for a voluntary feedback submission.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response
	 */
	public function handle_send( WP_REST_Request $request ): WP_REST_Response {
		/** @var array<string, mixed> $settings */
		$settings = Settings::get();

		// Graceful degradation: acknowledge silently when feedback is disabled or
		// the operator has not configured a receiver endpoint.
		$feedback_enabled      = ! empty( $settings['feedback_enabled'] );
		$feedback_endpoint_url = isset( $settings['feedback_endpoint_url'] ) ? (string) $settings['feedback_endpoint_url'] : '';

		if ( ! $feedback_enabled || '' === $feedback_endpoint_url ) {
			return new WP_REST_Response( array( 'success' => true ), 200 );
		}

		$report_type      = self::get_string_param( $request, 'report_type' );
		$user_description = self::get_string_param( $request, 'user_description' );
		$session_id       = self::get_int_param( $request, 'session_id' );
		$message_index    = (int) $request->get_param( 'message_index' );

		// Retrieve surrounding message context when a session and message index
		// are provided (thumbs_down reports anchor to a specific message).
		$context_messages = array();
		if ( $session_id > 0 && $message_index >= 0 ) {
			$context_messages = $this->extract_message_context( $session_id, $message_index );
		}

		$payload = array(
			'report_type'      => $report_type,
			'user_description' => $user_description,
			'context_messages' => $context_messages,
			'environment'      => array(
				'plugin_version' => defined( 'GRATIS_AI_AGENT_VERSION' ) ? GRATIS_AI_AGENT_VERSION : '',
				'wp_version'     => get_bloginfo( 'version' ),
				'php_version'    => PHP_VERSION,
				'is_multisite'   => is_multisite(),
			),
		);

		$api_key = Settings::get_feedback_api_key();
		$headers = array( 'Content-Type' => 'application/json' );
		if ( '' !== $api_key ) {
			$headers['X-Feedback-Api-Key'] = $api_key;
		}

		$response = wp_remote_post(
			esc_url_raw( $feedback_endpoint_url ),
			array(
				'headers' => $headers,
				'body'    => (string) wp_json_encode( $payload ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			// Log internally but return 200 to the browser — feedback failures are
			// non-critical and should not interrupt the user's workflow.
			error_log( 'GratisAiAgent: feedback send failed: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new WP_REST_Response( array( 'success' => true ), 200 );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			error_log( 'GratisAiAgent: feedback receiver returned HTTP ' . $code ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Extract a window of messages around the flagged message index.
	 *
	 * Only returns context when the session belongs to the current user, so a
	 * user cannot retrieve another user's conversation by supplying an arbitrary
	 * session_id.
	 *
	 * @param int $session_id    The session to read messages from.
	 * @param int $message_index The index of the flagged message.
	 * @return list<array<string, mixed>> Slice of messages (at most 2*CONTEXT_WINDOW+1 entries).
	 */
	private function extract_message_context( int $session_id, int $message_index ): array {
		$session = Database::get_session( $session_id );

		if ( ! $session ) {
			return array();
		}

		// Ownership guard — never expose another user's conversation.
		if ( (int) $session->user_id !== get_current_user_id() ) {
			return array();
		}

		/** @var list<array<string, mixed>> $all_messages */
		$all_messages = json_decode( (string) $session->messages, true );
		if ( ! is_array( $all_messages ) ) {
			return array();
		}

		$total = count( $all_messages );
		$start = max( 0, $message_index - self::CONTEXT_WINDOW );
		$end   = min( $total - 1, $message_index + self::CONTEXT_WINDOW );
		$slice = array_slice( $all_messages, $start, $end - $start + 1 );

		// Strip large tool-call result bodies to keep the payload small.
		return array_map( array( $this, 'sanitize_message_for_report' ), $slice );
	}

	/**
	 * Sanitize a single message for inclusion in a feedback report.
	 *
	 * Strips function-role result bodies (can contain large HTML/JSON blobs)
	 * and replaces them with a summary so the payload stays compact.
	 *
	 * @param array<string, mixed> $message Raw message from the session store.
	 * @return array<string, mixed> Sanitized message safe for external transmission.
	 */
	private function sanitize_message_for_report( array $message ): array {
		if ( ( $message['role'] ?? '' ) === 'function' ) {
			// Keep role and name for context; replace result with a size summary.
			$parts           = $message['parts'] ?? array();
			$sanitized_parts = array();
			foreach ( (array) $parts as $part ) {
				if ( isset( $part['functionResponse'] ) ) {
					$json_size         = strlen( (string) wp_json_encode( $part['functionResponse'] ) );
					$sanitized_parts[] = array(
						'functionResponse' => array(
							'__stripped' => true,
							'__bytes'    => $json_size,
						),
					);
				} else {
					$sanitized_parts[] = $part;
				}
			}
			$message['parts'] = $sanitized_parts;
		}

		return $message;
	}
}
