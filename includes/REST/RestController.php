<?php

declare(strict_types=1);
/**
 * REST API controller for the AI Agent.
 *
 * This is now a thin orchestrator. Domain-specific routes are handled by:
 *   - SessionController    — sessions, jobs, process, run, site-builder
 *   - SettingsController   — settings, providers, budget, usage, roles, alerts
 *   - MemoryController     — memories
 *   - SkillController      — skills
 *   - AutomationController — automations, event-automations, logs, triggers
 *   - KnowledgeController  — knowledge collections, sources, search, stats
 *   - ToolController       — custom-tools, tool-profiles, abilities
 *   - ChangesController    — changes, modified-plugins, download
 *   - AgentController      — agents, conversation-templates
 *
 * This class retains:
 *   - The /stream SSE endpoint (stateful, requires static context)
 *   - Session title generation helpers (used by SessionController)
 *   - Shared constants (NAMESPACE, JOB_PREFIX, JOB_TTL)
 *   - sanitize_page_context() (used by route args in SessionController)
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

use GratisAiAgent\Core\AgentLoop;
use GratisAiAgent\Core\CostCalculator;
use GratisAiAgent\Core\Database;
use GratisAiAgent\Core\RolePermissions;
use GratisAiAgent\Core\Settings;
use GratisAiAgent\Models\Agent;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RestController {

	use PermissionTrait;

	const NAMESPACE = 'gratis-ai-agent/v1';

	/**
	 * Transient prefix for job data.
	 */
	const JOB_PREFIX = 'gratis_ai_agent_job_';

	/**
	 * How long job data persists (seconds).
	 */
	const JOB_TTL = 600;

	/** @var Settings Injected settings dependency. */
	private Settings $settings;

	/** @var Database Injected database dependency. */
	private Database $database;

	/**
	 * Constructor — accepts injected dependencies for testability.
	 *
	 * @param Settings|null $settings  Settings service (defaults to new Settings()).
	 * @param Database|null $database  Database service (defaults to new Database()).
	 */
	public function __construct( ?Settings $settings = null, ?Database $database = null ) {
		$this->settings = $settings ?? new Settings();
		$this->database = $database ?? new Database();
	}

	/**
	 * Register REST routes.
	 *
	 * Delegates to domain controllers and registers the /stream endpoint here.
	 */
	public static function register_routes(): void {
		// MCP (Model Context Protocol) endpoint.
		McpController::register_routes();

		// Webhook API endpoints.
		WebhookController::register_routes();

		// Resale API endpoints.
		ResaleApiController::register_routes();

		// Domain controllers.
		SessionController::register_routes();
		SettingsController::register_routes();
		MemoryController::register_routes();
		SkillController::register_routes();
		AutomationController::register_routes();
		KnowledgeController::register_routes();
		ToolController::register_routes();
		ChangesController::register_routes();
		AgentController::register_routes();

		$instance = new self();

		// SSE streaming endpoint — kept here because it uses static context and exits.
		register_rest_route(
			self::NAMESPACE,
			'/stream',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_stream' ),
				'permission_callback' => array( $instance, 'check_chat_permission' ),
				'args'                => array(
					'message'            => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'session_id'         => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'abilities'          => array(
						'required' => false,
						'type'     => 'array',
						'default'  => array(),
					),
					'system_instruction' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'max_iterations'     => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
					'provider_id'        => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'model_id'           => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page_context'       => array(
						'required'          => false,
						'type'              => array( 'object', 'string' ),
						'default'           => array(),
						'sanitize_callback' => array( __CLASS__, 'sanitize_page_context' ),
					),
					'agent_id'           => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'attachments'        => array(
						'required' => false,
						'type'     => 'array',
						'default'  => array(),
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'name'     => array( 'type' => 'string' ),
								'type'     => array( 'type' => 'string' ),
								'data_url' => array( 'type' => 'string' ),
								'is_image' => array( 'type' => 'boolean' ),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Sanitize the page_context parameter.
	 *
	 * Accepts either an object (associative array) or a string and always
	 * returns an associative array.
	 *
	 * @param mixed $value Raw parameter value.
	 * @return array<string, mixed> Normalised page context.
	 */
	public static function sanitize_page_context( $value ): array {
		if ( is_array( $value ) ) {
			/** @var array<string, mixed> $value */
			return $value;
		}

		if ( is_string( $value ) && $value !== '' ) {
			return array( 'summary' => sanitize_textarea_field( $value ) );
		}

		return array();
	}

	/**
	 * Upload base64-encoded image attachments to the WordPress media library.
	 *
	 * @param array<int, array{name: string, type: string, data_url: string, is_image: bool}> $attachments Raw attachment objects from the REST request.
	 * @return array<int, array{name: string, type: string, data_url: string, is_image: bool, attachment_id?: int, url?: string}> Enriched attachment objects.
	 */
	private static function upload_attachments_to_media_library( array $attachments ): array {
		if ( empty( $attachments ) ) {
			return array();
		}

		// Ensure media-handling functions are available outside the admin context.
		if ( ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$processed = array();

		foreach ( $attachments as $att ) {
			$name     = sanitize_file_name( $att['name'] ?? 'upload' );
			$type     = $att['type'] ?? '';
			$data_url = $att['data_url'] ?? '';
			$is_image = ! empty( $att['is_image'] );

			// Only upload images to the media library; pass other files through.
			if ( ! $is_image || empty( $data_url ) ) {
				$processed[] = $att;
				continue;
			}

			// Decode the base64 data URL.
			if ( ! preg_match( '/^data:([^;]+);base64,(.+)$/s', $data_url, $matches ) ) {
				$processed[] = $att;
				continue;
			}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding image data URLs from user uploads, not obfuscating code.
			$decoded = base64_decode( $matches[2], true );
			if ( false === $decoded ) {
				$processed[] = $att;
				continue;
			}

			// Write to a temporary file.
			$tmp_file = wp_tempnam( $name );
			if ( ! $tmp_file ) {
				$processed[] = $att;
				continue;
			}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			if ( false === file_put_contents( $tmp_file, $decoded ) ) {
				wp_delete_file( $tmp_file );
				$processed[] = $att;
				continue;
			}

			$file_array = array(
				'name'     => $name,
				'type'     => $type,
				'tmp_name' => $tmp_file,
				'error'    => '0',
				'size'     => (string) strlen( $decoded ),
			);

			$attachment_id = media_handle_sideload( $file_array, 0, null );

			wp_delete_file( $tmp_file );

			if ( is_wp_error( $attachment_id ) ) {
				// Fall back to passing the raw data URL on upload failure.
				$processed[] = $att;
				continue;
			}

			$url = wp_get_attachment_url( $attachment_id );

			$processed[] = array_merge(
				$att,
				array(
					'attachment_id' => $attachment_id,
					'url'           => ( $url ? $url : $data_url ),
				)
			);
		}

		return $processed;
	}

	/**
	 * Handle POST /stream — run the agent loop and stream tokens via SSE.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return void This method exits after streaming; it never returns a WP_REST_Response.
	 */
	public static function handle_stream( WP_REST_Request $request ): void {
		$streamer = new SseStreamer();
		$streamer->start();

		$session_id      = self::get_int_param( $request, 'session_id' );
		$raw_attachments = $request->get_param( 'attachments' ) ?? array();
		/** @var array<int, array{name: string, type: string, data_url: string, is_image: bool}> $raw_attachments_typed */
		$raw_attachments_typed = is_array( $raw_attachments ) ? $raw_attachments : array();
		$attachments           = self::upload_attachments_to_media_library( $raw_attachments_typed );

		$params = array(
			// @phpstan-ignore-next-line
			'message'            => (string) $request->get_param( 'message' ),
			'abilities'          => $request->get_param( 'abilities' ) ?? array(),
			'system_instruction' => $request->get_param( 'system_instruction' ),
			'max_iterations'     => $request->get_param( 'max_iterations' ) ?? 10,
			'provider_id'        => $request->get_param( 'provider_id' ),
			'model_id'           => $request->get_param( 'model_id' ),
			'page_context'       => $request->get_param( 'page_context' ) ?? array(),
			'agent_id'           => $request->get_param( 'agent_id' ),
			'attachments'        => $attachments,
		);

		// Load conversation history from session.
		$history = array();
		if ( $session_id ) {
			$session = Database::get_session( $session_id );
			if ( $session ) {
				$session_messages = json_decode( $session->messages, true ) ?: array();
				if ( ! empty( $session_messages ) ) {
					try {
						// @phpstan-ignore-next-line
						$history = AgentLoop::deserialize_history( $session_messages );
					} catch ( \Exception $e ) {
						$history = array();
					}
				}
			}
		}

		$options = array(
			'max_iterations' => $params['max_iterations'],
		);

		if ( ! empty( $params['system_instruction'] ) ) {
			$options['system_instruction'] = $params['system_instruction'];
		}
		if ( ! empty( $params['provider_id'] ) ) {
			$options['provider_id'] = $params['provider_id'];
		}
		if ( ! empty( $params['model_id'] ) ) {
			$options['model_id'] = $params['model_id'];
		}
		if ( ! empty( $params['page_context'] ) ) {
			$options['page_context'] = $params['page_context'];
		}

		// Apply agent overrides (agent_id takes precedence over individual params).
		if ( ! empty( $params['agent_id'] ) ) {
			// @phpstan-ignore-next-line
			$agent_options = Agent::get_loop_options( (int) $params['agent_id'] );
			$options       = array_merge( $options, $agent_options );
		}

		// Pass image attachments to AgentLoop for vision model support.
		if ( ! empty( $params['attachments'] ) ) {
			$options['attachments'] = $params['attachments'];
		}

		// Attach the SSE streamer so AgentLoop can emit tokens as they arrive.
		$options['sse_streamer'] = $streamer;

		$abilities_param = $params['abilities'];
		// @phpstan-ignore-next-line
		$loop   = new AgentLoop( $params['message'], is_array( $abilities_param ) ? $abilities_param : array(), $history, $options );
		$result = $loop->run();

		if ( is_wp_error( $result ) ) {
			$streamer->send_error( $result->get_error_message(), (string) $result->get_error_code() );
			exit;
		}

		/** @var array<string, mixed> $result */

		// Handle tool confirmation pause.
		if ( ! empty( $result['awaiting_confirmation'] ) ) {
			$job_id = wp_generate_uuid4();
			$token  = wp_generate_password( 40, false );

			$job = array(
				'status'             => 'awaiting_confirmation',
				'token'              => $token,
				'user_id'            => get_current_user_id(),
				'pending_tools'      => $result['pending_tools'] ?? array(),
				'confirmation_state' => array(
					'history'              => $result['history'] ?? array(),
					'tool_call_log'        => $result['tool_call_log'] ?? array(),
					'token_usage'          => $result['token_usage'] ?? array(
						'prompt'     => 0,
						'completion' => 0,
					),
					'iterations_remaining' => $result['iterations_remaining'] ?? 5,
				),
				'params'             => $params,
			);

			set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );

			/** @var list<array<string, mixed>> $pending_tools_stream */
			$pending_tools_stream = $result['pending_tools'] ?? array();
			$streamer->send_confirmation_required( $job_id, $pending_tools_stream );
			exit;
		}

		// Persist to session.
		$generated_title = null;
		if ( $session_id && ! empty( $result ) ) {
			$session        = Database::get_session( $session_id );
			$existing_count = 0;
			if ( $session ) {
				$existing_messages = json_decode( $session->messages, true ) ?: array();
				// @phpstan-ignore-next-line
				$existing_count = count( $existing_messages );
			}

			$full_history = $result['history'] ?? array();
			/** @var array<mixed> $full_history */
			$appended = array_slice( $full_history, $existing_count );
			/** @var list<array<string, mixed>> $tool_calls_stream */
			$tool_calls_stream = $result['tool_calls'] ?? array();
			Database::append_to_session( $session_id, array_values( $appended ), $tool_calls_stream );

			$token_usage = $result['token_usage'] ?? array();
			/** @var array<string, mixed> $token_usage */
			if ( ! empty( $token_usage ) ) {
				Database::update_session_tokens(
					$session_id,
					// @phpstan-ignore-next-line
					(int) ( $token_usage['prompt'] ?? 0 ),
					// @phpstan-ignore-next-line
					(int) ( $token_usage['completion'] ?? 0 )
				);
			}

			// Log usage.
			// @phpstan-ignore-next-line
			$prompt_t = (int) ( $token_usage['prompt'] ?? 0 );
			// @phpstan-ignore-next-line
			$completion_t = (int) ( $token_usage['completion'] ?? 0 );
			if ( $prompt_t > 0 || $completion_t > 0 ) {
				// @phpstan-ignore-next-line
				$model_id = (string) ( $options['model_id'] ?? $params['model_id'] ?? '' );
				$cost     = CostCalculator::calculate_cost( $model_id, $prompt_t, $completion_t );
				Database::log_usage(
					array(
						'user_id'           => get_current_user_id(),
						'session_id'        => $session_id,
						// @phpstan-ignore-next-line
						'provider_id'       => (string) ( $options['provider_id'] ?? $params['provider_id'] ?? '' ),
						'model_id'          => $model_id,
						'prompt_tokens'     => $prompt_t,
						'completion_tokens' => $completion_t,
						'cost_usd'          => $cost,
					)
				);
			}

			// Auto-generate title.
			$generated_title = null;
			if ( $session && empty( $session->title ) ) {
				$generated_title = self::generate_session_title(
					$params['message'],
					// @phpstan-ignore-next-line
					(string) ( $result['reply'] ?? '' ),
					// @phpstan-ignore-next-line
					(string) ( $options['provider_id'] ?? $params['provider_id'] ?? '' ),
					// @phpstan-ignore-next-line
					(string) ( $options['model_id'] ?? $params['model_id'] ?? '' )
				);
				Database::update_session( $session_id, array( 'title' => $generated_title ) );
			}
		}

		$token_usage = $result['token_usage'] ?? array(
			'prompt'     => 0,
			'completion' => 0,
		);
		$model_id    = $result['model_id'] ?? ( $params['model_id'] ?? '' );

		$done_payload = array(
			'session_id'      => $session_id ?: null,
			'token_usage'     => $token_usage,
			'model_id'        => $model_id,
			'iterations_used' => $result['iterations_used'] ?? 0,
			'cost_estimate'   => CostCalculator::calculate_cost(
				// @phpstan-ignore-next-line
				$model_id,
				// @phpstan-ignore-next-line
				(int) ( $token_usage['prompt'] ?? 0 ),
				// @phpstan-ignore-next-line
				(int) ( $token_usage['completion'] ?? 0 )
			),
			'tool_calls'      => $result['tool_calls'] ?? array(),
		);

		if ( null !== $generated_title ) {
			$done_payload['generated_title'] = $generated_title;
		}

		$streamer->send_done( $done_payload );

		exit;
	}

	// ─── Session Title Generation ─────────────────────────────────────────────

	/**
	 * Generate a short 3-5 word session title from the first user message and AI reply.
	 *
	 * @param string $user_message The first user message.
	 * @param string $ai_reply     The first AI reply.
	 * @param string $provider_id  Provider identifier (e.g. 'openai', 'anthropic').
	 * @param string $model_id     Model identifier.
	 * @return string A short title (3-5 words, no quotes, no punctuation at end).
	 */
	public static function generate_session_title( string $user_message, string $ai_reply, string $provider_id, string $model_id ): string {
		$fallback = self::title_fallback( $user_message );

		if ( empty( $user_message ) ) {
			return $fallback;
		}

		// Build a minimal prompt asking for a 3-5 word title.
		$prompt_text = sprintf(
			'Generate a short 3-5 word title for this conversation. Reply with ONLY the title — no quotes, no punctuation at the end, no explanation.

User: %s
Assistant: %s',
			mb_substr( $user_message, 0, 500 ),
			mb_substr( $ai_reply, 0, 500 )
		);

		$messages = array(
			array(
				'role'    => 'user',
				'content' => $prompt_text,
			),
		);

		$request_body = array(
			'messages'   => $messages,
			'max_tokens' => 20,
			'stream'     => false,
		);

		$raw_title = self::call_provider_for_title( $provider_id, $model_id, $request_body );

		if ( null === $raw_title ) {
			return $fallback;
		}

		// Sanitize: strip surrounding quotes, trim whitespace, limit length.
		$title = trim( $raw_title, " \t\n\r\0\x0B\"'" );
		$title = wp_strip_all_tags( $title );
		$title = mb_substr( $title, 0, 100 );

		return '' !== $title ? $title : $fallback;
	}

	/**
	 * Make a minimal API call to the configured provider to generate a title.
	 *
	 * @param string               $provider_id  Provider identifier.
	 * @param string               $model_id     Model identifier.
	 * @param array<string, mixed> $request_body Base request body (messages, max_tokens, stream).
	 * @return string|null Raw response text, or null on failure.
	 */
	private static function call_provider_for_title( string $provider_id, string $model_id, array $request_body ): ?string {
		// Determine which provider to use.
		$effective_provider = $provider_id;
		if ( empty( $effective_provider ) ) {
			$settings = Settings::get();
			// @phpstan-ignore-next-line
			$effective_provider = $settings['default_provider'] ?? '';
		}

		switch ( $effective_provider ) {
			case 'openai':
				return self::call_openai_for_title( $model_id, $request_body );

			case 'anthropic':
				return self::call_anthropic_for_title( $model_id, $request_body );

			case 'google':
				return self::call_google_for_title( $model_id, $request_body );

			default:
				// Try OpenAI-compatible proxy.
				return self::call_openai_compat_for_title( $model_id, $request_body );
		}
	}

	/**
	 * Call OpenAI to generate a title.
	 *
	 * @param string               $model_id     Model identifier.
	 * @param array<string, mixed> $request_body Base request body.
	 * @return string|null
	 */
	private static function call_openai_for_title( string $model_id, array $request_body ): ?string {
		$api_key = Settings::get_provider_key( 'openai' );
		if ( '' === $api_key ) {
			return null;
		}

		$request_body['model'] = $model_id ?: Settings::DIRECT_PROVIDERS['openai']['default_model'];

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			[
				'timeout' => 15,
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				],
				'body'    => (string) wp_json_encode( $request_body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		// @phpstan-ignore-next-line
		return $data['choices'][0]['message']['content'] ?? null;
	}

	/**
	 * Call Anthropic to generate a title.
	 *
	 * @param string               $model_id     Model identifier.
	 * @param array<string, mixed> $request_body Base request body.
	 * @return string|null
	 */
	private static function call_anthropic_for_title( string $model_id, array $request_body ): ?string {
		$api_key = Settings::get_provider_key( 'anthropic' );
		if ( '' === $api_key ) {
			return null;
		}

		// Anthropic uses a different format.
		$anthropic_body = [
			'model'      => $model_id ?: Settings::DIRECT_PROVIDERS['anthropic']['default_model'],
			'max_tokens' => 20,
			'messages'   => $request_body['messages'],
		];

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			[
				'timeout' => 15,
				'headers' => [
					'Content-Type'      => 'application/json',
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
				],
				'body'    => (string) wp_json_encode( $anthropic_body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		// @phpstan-ignore-next-line
		return $data['content'][0]['text'] ?? null;
	}

	/**
	 * Call Google Gemini to generate a title.
	 *
	 * @param string               $model_id     Model identifier.
	 * @param array<string, mixed> $request_body Base request body.
	 * @return string|null
	 */
	private static function call_google_for_title( string $model_id, array $request_body ): ?string {
		$api_key = Settings::get_provider_key( 'google' );
		if ( '' === $api_key ) {
			return null;
		}

		$effective_model = $model_id ?: Settings::DIRECT_PROVIDERS['google']['default_model'];

		// Google uses OpenAI-compatible endpoint via their REST API.
		$google_body = [
			'model'      => $effective_model,
			'max_tokens' => 20,
			'messages'   => $request_body['messages'],
			'stream'     => false,
		];

		$response = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
			[
				'timeout' => 15,
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				],
				'body'    => (string) wp_json_encode( $google_body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		// @phpstan-ignore-next-line
		return $data['choices'][0]['message']['content'] ?? null;
	}

	/**
	 * Call an OpenAI-compatible proxy to generate a title.
	 *
	 * @param string               $model_id     Model identifier.
	 * @param array<string, mixed> $request_body Base request body.
	 * @return string|null
	 */
	private static function call_openai_compat_for_title( string $model_id, array $request_body ): ?string {
		$endpoint_url = \GratisAiAgent\Core\CredentialResolver::getOpenAiCompatEndpointUrl();
		if ( '' === $endpoint_url ) {
			return null;
		}

		$api_key = \GratisAiAgent\Core\CredentialResolver::getOpenAiCompatApiKey();

		$effective_model = $model_id;
		if ( empty( $effective_model ) ) {
			if ( function_exists( 'OpenAiCompatibleConnector\\get_default_model' ) ) {
				$effective_model = \OpenAiCompatibleConnector\get_default_model();
			}
			if ( empty( $effective_model ) ) {
				$effective_model = Settings::get_default_model();
			}
		}

		$request_body['model'] = $effective_model;

		$response = wp_remote_post(
			rtrim( $endpoint_url, '/' ) . '/chat/completions',
			[
				'timeout' => 15,
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . ( $api_key ?: 'no-key' ),
				],
				'body'    => (string) wp_json_encode( $request_body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		// @phpstan-ignore-next-line
		return $data['choices'][0]['message']['content'] ?? null;
	}

	/**
	 * Fallback title: truncate the user message to 60 characters.
	 *
	 * @param string $user_message The user message.
	 * @return string Truncated title.
	 */
	private static function title_fallback( string $user_message ): string {
		$title = mb_substr( $user_message, 0, 60 );
		if ( mb_strlen( $user_message ) > 60 ) {
			$title .= '...';
		}
		return $title;
	}
}
