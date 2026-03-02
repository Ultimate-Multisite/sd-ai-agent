<?php
/**
 * REST API controller for the AI Agent.
 *
 * Uses an async job + polling pattern so that long-running LLM inference
 * does not block the browser->nginx connection.
 *
 * @package AiAgent
 */

namespace AiAgent;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class Rest_Controller {

	const NAMESPACE = 'ai-agent/v1';

	/**
	 * Transient prefix for job data.
	 */
	const JOB_PREFIX = 'ai_agent_job_';

	/**
	 * How long job data persists (seconds).
	 */
	const JOB_TTL = 600;

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/run',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle_run' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'message'            => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'history'            => [
						'required' => false,
						'type'     => 'array',
						'default'  => [],
					],
					'abilities'          => [
						'required' => false,
						'type'     => 'array',
						'default'  => [],
					],
					'system_instruction' => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					],
					'max_iterations'     => [
						'required'          => false,
						'type'              => 'integer',
						'default'           => 10,
						'sanitize_callback' => 'absint',
					],
					'session_id'         => [
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'provider_id'        => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'model_id'           => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/job/(?P<id>[a-f0-9-]+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_job_status' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/process',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle_process' ],
				'permission_callback' => [ __CLASS__, 'check_process_permission' ],
				'args'                => [
					'job_id' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'token'  => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/abilities',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_abilities' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);

		// Providers endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/providers',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_providers' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);

		// Settings endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_get_settings' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_update_settings' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
			]
		);

		// Memory endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/memory',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_list_memory' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_create_memory' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'category' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'content'  => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/memory/(?P<id>\d+)',
			[
				[
					'methods'             => 'PATCH',
					'callback'            => [ __CLASS__, 'handle_update_memory' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id'       => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
						'category' => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'content'  => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ __CLASS__, 'handle_delete_memory' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		// Skills endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/skills',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_list_skills' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_create_skill' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'slug'        => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_title',
						],
						'name'        => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'description' => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'content'     => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/skills/(?P<id>\d+)',
			[
				[
					'methods'             => 'PATCH',
					'callback'            => [ __CLASS__, 'handle_update_skill' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id'          => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
						'name'        => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'description' => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'content'     => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
						],
						'enabled'     => [
							'required' => false,
							'type'     => 'boolean',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ __CLASS__, 'handle_delete_skill' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/skills/(?P<id>\d+)/reset',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_reset_skill' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		// Sessions endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/sessions',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_list_sessions' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_create_session' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'title'       => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'provider_id' => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'model_id'    => [
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_get_session' ],
					'permission_callback' => [ __CLASS__, 'check_session_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
				[
					'methods'             => 'PATCH',
					'callback'            => [ __CLASS__, 'handle_update_session' ],
					'permission_callback' => [ __CLASS__, 'check_session_permission' ],
					'args'                => [
						'id'    => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
						'title' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ __CLASS__, 'handle_delete_session' ],
					'permission_callback' => [ __CLASS__, 'check_session_permission' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);
	}

	/**
	 * Permission check — admin only.
	 */
	public static function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for session-specific endpoints.
	 *
	 * Verifies manage_options + session ownership.
	 */
	public static function check_session_permission( WP_REST_Request $request ): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$session_id = absint( $request->get_param( 'id' ) );
		$session    = Database::get_session( $session_id );

		if ( ! $session ) {
			return false;
		}

		return (int) $session->user_id === get_current_user_id();
	}

	/**
	 * Permission check for the internal /process endpoint.
	 *
	 * Validates a one-time token stored in the job transient instead of
	 * requiring cookie-based auth (the loopback request has no session).
	 */
	public static function check_process_permission( WP_REST_Request $request ): bool {
		$job_id = $request->get_param( 'job_id' );
		$token  = $request->get_param( 'token' );

		if ( empty( $job_id ) || empty( $token ) ) {
			return false;
		}

		$job = get_transient( self::JOB_PREFIX . $job_id );

		if ( ! is_array( $job ) || empty( $job['token'] ) ) {
			return false;
		}

		return hash_equals( $job['token'], $token );
	}

	/**
	 * Handle the /run endpoint.
	 *
	 * Creates a job, spawns a background worker, and returns immediately.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_run( WP_REST_Request $request ) {
		$job_id = wp_generate_uuid4();
		$token  = wp_generate_password( 40, false );

		$job = [
			'status'  => 'processing',
			'token'   => $token,
			'user_id' => get_current_user_id(),
			'params'  => [
				'message'            => $request->get_param( 'message' ),
				'history'            => $request->get_param( 'history' ),
				'abilities'          => $request->get_param( 'abilities' ),
				'system_instruction' => $request->get_param( 'system_instruction' ),
				'max_iterations'     => $request->get_param( 'max_iterations' ),
				'session_id'         => $request->get_param( 'session_id' ),
				'provider_id'        => $request->get_param( 'provider_id' ),
				'model_id'           => $request->get_param( 'model_id' ),
			],
		];

		set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );

		// Spawn background worker via non-blocking loopback.
		wp_remote_post(
			rest_url( self::NAMESPACE . '/process' ),
			[
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => false,
				'body'      => wp_json_encode( [
					'job_id' => $job_id,
					'token'  => $token,
				] ),
				'headers'   => [
					'Content-Type' => 'application/json',
				],
			]
		);

		return new WP_REST_Response(
			[
				'job_id' => $job_id,
				'status' => 'processing',
			],
			202
		);
	}

	/**
	 * Handle the /job/{id} polling endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_job_status( WP_REST_Request $request ) {
		$job_id = $request->get_param( 'id' );
		$job    = get_transient( self::JOB_PREFIX . $job_id );

		if ( false === $job || ! is_array( $job ) ) {
			return new WP_Error(
				'ai_agent_job_not_found',
				__( 'Job not found or expired.', 'ai-agent' ),
				[ 'status' => 404 ]
			);
		}

		$response = [ 'status' => $job['status'] ];

		if ( 'complete' === $job['status'] && isset( $job['result'] ) ) {
			$response['reply']       = $job['result']['reply'] ?? '';
			$response['history']     = $job['result']['history'] ?? [];
			$response['tool_calls']  = $job['result']['tool_calls'] ?? [];
			$response['session_id']  = $job['result']['session_id'] ?? null;
			$response['token_usage'] = $job['result']['token_usage'] ?? [ 'prompt' => 0, 'completion' => 0 ];

			// Clean up — result has been delivered.
			delete_transient( self::JOB_PREFIX . $job_id );
		}

		if ( 'error' === $job['status'] && isset( $job['error'] ) ) {
			$response['message'] = $job['error'];

			// Clean up.
			delete_transient( self::JOB_PREFIX . $job_id );
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Handle the internal /process endpoint (background worker).
	 *
	 * Runs the Agent_Loop and stores the result in the job transient.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public static function handle_process( WP_REST_Request $request ): WP_REST_Response {
		ignore_user_abort( true );
		set_time_limit( 600 );

		$job_id = $request->get_param( 'job_id' );
		$job    = get_transient( self::JOB_PREFIX . $job_id );

		if ( ! is_array( $job ) || empty( $job['params'] ) ) {
			return new WP_REST_Response( [ 'ok' => false ], 200 );
		}

		// Restore the user context — the loopback request has no cookies,
		// but the AI Client needs a user for provider auth binding.
		if ( ! empty( $job['user_id'] ) ) {
			wp_set_current_user( $job['user_id'] );
		}

		$params     = $job['params'];
		$session_id = ! empty( $params['session_id'] ) ? (int) $params['session_id'] : 0;

		// Load history from session if session_id is provided.
		$history = [];
		if ( $session_id ) {
			$session = Database::get_session( $session_id );
			if ( $session ) {
				$session_messages = json_decode( $session->messages, true ) ?: [];
				if ( ! empty( $session_messages ) ) {
					try {
						$history = Agent_Loop::deserialize_history( $session_messages );
					} catch ( \Exception $e ) {
						$history = [];
					}
				}
			}
		} elseif ( ! empty( $params['history'] ) && is_array( $params['history'] ) ) {
			try {
				$history = Agent_Loop::deserialize_history( $params['history'] );
			} catch ( \Exception $e ) {
				$job['status'] = 'error';
				$job['error']  = __( 'Invalid conversation history format.', 'ai-agent' );
				unset( $job['token'] );
				set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );
				return new WP_REST_Response( [ 'ok' => false ], 200 );
			}
		}

		$options = [
			'max_iterations' => $params['max_iterations'] ?? 10,
		];

		if ( ! empty( $params['system_instruction'] ) ) {
			$options['system_instruction'] = $params['system_instruction'];
		}

		if ( ! empty( $params['provider_id'] ) ) {
			$options['provider_id'] = $params['provider_id'];
		}

		if ( ! empty( $params['model_id'] ) ) {
			$options['model_id'] = $params['model_id'];
		}

		$loop   = new Agent_Loop( $params['message'], $params['abilities'] ?? [], $history, $options );
		$result = $loop->run();

		if ( is_wp_error( $result ) ) {
			$job['status'] = 'error';
			$job['error']  = $result->get_error_message();
		} else {
			$job['status'] = 'complete';
			$job['result'] = $result;

			// Persist to session if session_id is provided.
			if ( $session_id ) {
				$job['result']['session_id'] = $session_id;

				// The full history from the loop includes existing + new messages.
				// Slice off only the new ones to append.
				$session        = Database::get_session( $session_id );
				$existing_count = 0;
				if ( $session ) {
					$existing_messages = json_decode( $session->messages, true ) ?: [];
					$existing_count    = count( $existing_messages );
				}

				$full_history = $result['history'] ?? [];
				$appended     = array_slice( $full_history, $existing_count );

				Database::append_to_session( $session_id, $appended, $result['tool_calls'] ?? [] );

				// Persist token usage.
				$token_usage = $result['token_usage'] ?? [];
				if ( ! empty( $token_usage ) ) {
					Database::update_session_tokens(
						$session_id,
						$token_usage['prompt'] ?? 0,
						$token_usage['completion'] ?? 0
					);
				}

				// Auto-generate title from first user message if empty.
				if ( $session && empty( $session->title ) ) {
					$title = mb_substr( $params['message'], 0, 60 );
					if ( mb_strlen( $params['message'] ) > 60 ) {
						$title .= '...';
					}
					Database::update_session( $session_id, [ 'title' => $title ] );
				}
			}
		}

		// Clear the token — no longer needed.
		unset( $job['token'] );
		set_transient( self::JOB_PREFIX . $job_id, $job, self::JOB_TTL );

		return new WP_REST_Response( [ 'ok' => true ], 200 );
	}

	/**
	 * Handle the /abilities endpoint — list available abilities.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_abilities(): WP_REST_Response {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return new WP_REST_Response( [], 200 );
		}

		$abilities = wp_get_abilities();
		$list      = [];

		foreach ( $abilities as $ability ) {
			$list[] = [
				'name'        => $ability->get_name(),
				'label'       => $ability->get_label(),
				'description' => $ability->get_description(),
				'category'    => $ability->get_category(),
			];
		}

		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle the /providers endpoint — list registered AI providers and models.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_providers(): WP_REST_Response {
		if ( ! class_exists( '\\WordPress\\AiClient\\AiClient' ) ) {
			return new WP_REST_Response( [], 200 );
		}

		try {
			$registry     = \WordPress\AiClient\AiClient::defaultRegistry();
			$provider_ids = $registry->getRegisteredProviderIds();
		} catch ( \Throwable $e ) {
			return new WP_REST_Response( [], 200 );
		}

		// Ensure credentials are loaded (same logic the agent loop uses).
		Agent_Loop::ensure_provider_credentials_static();

		$providers = [];

		foreach ( $provider_ids as $provider_id ) {
			try {
				$class = $registry->getProviderClassName( $provider_id );

				// Only include providers that have authentication set.
				$auth = $registry->getProviderRequestAuthentication( $provider_id );
				if ( null === $auth ) {
					continue;
				}

				$metadata = $class::metadata();
				$models   = [];

				try {
					$directory      = $class::modelMetadataDirectory();
					$model_metadata = $directory->listModelMetadata();

					foreach ( $model_metadata as $model_meta ) {
						$models[] = [
							'id'   => $model_meta->getId(),
							'name' => $model_meta->getName(),
						];
					}
				} catch ( \Throwable $e ) {
					// Model listing failed — still include the provider.
				}

				$providers[] = [
					'id'         => $provider_id,
					'name'       => $metadata->getName(),
					'type'       => (string) $metadata->getType(),
					'configured' => true,
					'models'     => $models,
				];
			} catch ( \Throwable $e ) {
				continue;
			}
		}

		return new WP_REST_Response( $providers, 200 );
	}

	/**
	 * Handle GET /sessions — list sessions for current user.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_list_sessions(): WP_REST_Response {
		$sessions = Database::list_sessions( get_current_user_id() );

		return new WP_REST_Response( $sessions, 200 );
	}

	/**
	 * Handle GET /sessions/{id} — get full session with messages.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_get_session( WP_REST_Request $request ) {
		$session_id = absint( $request->get_param( 'id' ) );
		$session    = Database::get_session( $session_id );

		if ( ! $session ) {
			return new WP_Error(
				'ai_agent_session_not_found',
				__( 'Session not found.', 'ai-agent' ),
				[ 'status' => 404 ]
			);
		}

		return new WP_REST_Response(
			[
				'id'          => (int) $session->id,
				'title'       => $session->title,
				'provider_id' => $session->provider_id,
				'model_id'    => $session->model_id,
				'messages'    => json_decode( $session->messages, true ) ?: [],
				'tool_calls'  => json_decode( $session->tool_calls, true ) ?: [],
				'token_usage' => [
					'prompt'     => (int) ( $session->prompt_tokens ?? 0 ),
					'completion' => (int) ( $session->completion_tokens ?? 0 ),
				],
				'created_at'  => $session->created_at,
				'updated_at'  => $session->updated_at,
			],
			200
		);
	}

	/**
	 * Handle POST /sessions — create a new session.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_create_session( WP_REST_Request $request ) {
		$session_id = Database::create_session( [
			'user_id'     => get_current_user_id(),
			'title'       => $request->get_param( 'title' ),
			'provider_id' => $request->get_param( 'provider_id' ),
			'model_id'    => $request->get_param( 'model_id' ),
		] );

		if ( ! $session_id ) {
			return new WP_Error(
				'ai_agent_session_create_failed',
				__( 'Failed to create session.', 'ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		$session = Database::get_session( $session_id );

		return new WP_REST_Response(
			[
				'id'          => (int) $session->id,
				'title'       => $session->title,
				'provider_id' => $session->provider_id,
				'model_id'    => $session->model_id,
				'messages'    => [],
				'tool_calls'  => [],
				'created_at'  => $session->created_at,
				'updated_at'  => $session->updated_at,
			],
			201
		);
	}

	/**
	 * Handle PATCH /sessions/{id} — update session title.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_update_session( WP_REST_Request $request ) {
		$session_id = absint( $request->get_param( 'id' ) );

		$updated = Database::update_session( $session_id, [
			'title' => $request->get_param( 'title' ),
		] );

		if ( ! $updated ) {
			return new WP_Error(
				'ai_agent_session_update_failed',
				__( 'Failed to update session.', 'ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		$session = Database::get_session( $session_id );

		return new WP_REST_Response(
			[
				'id'          => (int) $session->id,
				'title'       => $session->title,
				'provider_id' => $session->provider_id,
				'model_id'    => $session->model_id,
				'created_at'  => $session->created_at,
				'updated_at'  => $session->updated_at,
			],
			200
		);
	}

	/**
	 * Handle DELETE /sessions/{id} — delete a session.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_delete_session( WP_REST_Request $request ) {
		$session_id = absint( $request->get_param( 'id' ) );

		$deleted = Database::delete_session( $session_id );

		if ( ! $deleted ) {
			return new WP_Error(
				'ai_agent_session_delete_failed',
				__( 'Failed to delete session.', 'ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	// ─── Skills ─────────────────────────────────────────────────────

	/**
	 * Handle GET /skills — list all skills.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_list_skills(): WP_REST_Response {
		$skills = Skill::get_all();

		$list = array_map( function ( $s ) {
			return [
				'id'          => (int) $s->id,
				'slug'        => $s->slug,
				'name'        => $s->name,
				'description' => $s->description,
				'content'     => $s->content,
				'is_builtin'  => (bool) (int) $s->is_builtin,
				'enabled'     => (bool) (int) $s->enabled,
				'word_count'  => str_word_count( $s->content ),
				'created_at'  => $s->created_at,
				'updated_at'  => $s->updated_at,
			];
		}, $skills );

		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle POST /skills — create a custom skill.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_create_skill( WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );

		// Check for duplicate slug.
		$existing = Skill::get_by_slug( $slug );
		if ( $existing ) {
			return new WP_Error(
				'ai_agent_skill_slug_exists',
				__( 'A skill with this slug already exists.', 'ai-agent' ),
				[ 'status' => 409 ]
			);
		}

		$id = Skill::create( [
			'slug'        => $slug,
			'name'        => $request->get_param( 'name' ),
			'description' => $request->get_param( 'description' ),
			'content'     => $request->get_param( 'content' ),
			'is_builtin'  => false,
			'enabled'     => true,
		] );

		if ( false === $id ) {
			return new WP_Error(
				'ai_agent_skill_create_failed',
				__( 'Failed to create skill.', 'ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		$skill = Skill::get( $id );

		return new WP_REST_Response( [
			'id'          => (int) $skill->id,
			'slug'        => $skill->slug,
			'name'        => $skill->name,
			'description' => $skill->description,
			'content'     => $skill->content,
			'is_builtin'  => false,
			'enabled'     => true,
			'word_count'  => str_word_count( $skill->content ),
			'created_at'  => $skill->created_at,
			'updated_at'  => $skill->updated_at,
		], 201 );
	}

	/**
	 * Handle PATCH /skills/{id} — update a skill.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_update_skill( WP_REST_Request $request ) {
		$id   = absint( $request->get_param( 'id' ) );
		$data = [];

		if ( $request->has_param( 'name' ) ) {
			$data['name'] = $request->get_param( 'name' );
		}
		if ( $request->has_param( 'description' ) ) {
			$data['description'] = $request->get_param( 'description' );
		}
		if ( $request->has_param( 'content' ) ) {
			$data['content'] = $request->get_param( 'content' );
		}
		if ( $request->has_param( 'enabled' ) ) {
			$data['enabled'] = $request->get_param( 'enabled' );
		}

		$updated = Skill::update( $id, $data );

		if ( ! $updated ) {
			return new WP_Error(
				'ai_agent_skill_update_failed',
				__( 'Failed to update skill.', 'ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		$skill = Skill::get( $id );

		return new WP_REST_Response( [
			'id'          => (int) $skill->id,
			'slug'        => $skill->slug,
			'name'        => $skill->name,
			'description' => $skill->description,
			'content'     => $skill->content,
			'is_builtin'  => (bool) (int) $skill->is_builtin,
			'enabled'     => (bool) (int) $skill->enabled,
			'word_count'  => str_word_count( $skill->content ),
			'created_at'  => $skill->created_at,
			'updated_at'  => $skill->updated_at,
		], 200 );
	}

	/**
	 * Handle DELETE /skills/{id} — delete a custom skill (refuses built-in).
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_delete_skill( WP_REST_Request $request ) {
		$id     = absint( $request->get_param( 'id' ) );
		$result = Skill::delete( $id );

		if ( $result === 'builtin' ) {
			return new WP_Error(
				'ai_agent_skill_builtin_delete',
				__( 'Built-in skills cannot be deleted. You can disable them instead.', 'ai-agent' ),
				[ 'status' => 403 ]
			);
		}

		if ( ! $result ) {
			return new WP_Error(
				'ai_agent_skill_delete_failed',
				__( 'Failed to delete skill or skill not found.', 'ai-agent' ),
				[ 'status' => 500 ]
			);
		}

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	/**
	 * Handle POST /skills/{id}/reset — reset a built-in skill to defaults.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_reset_skill( WP_REST_Request $request ) {
		$id    = absint( $request->get_param( 'id' ) );
		$reset = Skill::reset_builtin( $id );

		if ( ! $reset ) {
			return new WP_Error(
				'ai_agent_skill_reset_failed',
				__( 'Failed to reset skill. Only built-in skills can be reset.', 'ai-agent' ),
				[ 'status' => 400 ]
			);
		}

		$skill = Skill::get( $id );

		return new WP_REST_Response( [
			'id'          => (int) $skill->id,
			'slug'        => $skill->slug,
			'name'        => $skill->name,
			'description' => $skill->description,
			'content'     => $skill->content,
			'is_builtin'  => (bool) (int) $skill->is_builtin,
			'enabled'     => (bool) (int) $skill->enabled,
			'word_count'  => str_word_count( $skill->content ),
			'created_at'  => $skill->created_at,
			'updated_at'  => $skill->updated_at,
		], 200 );
	}

	// ─── Settings ────────────────────────────────────────────────────

	/**
	 * Handle GET /settings.
	 */
	public static function handle_get_settings(): WP_REST_Response {
		$settings = Settings::get();

		// Include built-in defaults so the UI can show them as placeholders.
		$settings['_defaults'] = [
			'system_prompt'   => Agent_Loop::get_default_system_prompt(),
			'greeting_message' => __( 'Send a message to start a conversation.', 'ai-agent' ),
		];

		return new WP_REST_Response( $settings, 200 );
	}

	/**
	 * Handle POST /settings — partial update.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_update_settings( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();

		if ( empty( $data ) || ! is_array( $data ) ) {
			return new WP_REST_Response( [ 'error' => 'No data provided.' ], 400 );
		}

		Settings::update( $data );

		return new WP_REST_Response( Settings::get(), 200 );
	}

	// ─── Memory ──────────────────────────────────────────────────────

	/**
	 * Handle GET /memory — list memories.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_list_memory( WP_REST_Request $request ): WP_REST_Response {
		$category = $request->get_param( 'category' );
		$memories = Memory::get_all( $category ?: null );

		$list = array_map( function ( $m ) {
			return [
				'id'         => (int) $m->id,
				'category'   => $m->category,
				'content'    => $m->content,
				'created_at' => $m->created_at,
				'updated_at' => $m->updated_at,
			];
		}, $memories );

		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle POST /memory — create a memory.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_create_memory( WP_REST_Request $request ) {
		$category = $request->get_param( 'category' );
		$content  = $request->get_param( 'content' );

		$id = Memory::create( $category, $content );

		if ( false === $id ) {
			return new WP_Error( 'ai_agent_memory_create_failed', __( 'Failed to create memory.', 'ai-agent' ), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [
			'id'       => $id,
			'category' => $category,
			'content'  => $content,
		], 201 );
	}

	/**
	 * Handle PATCH /memory/{id} — update a memory.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_update_memory( WP_REST_Request $request ) {
		$id   = absint( $request->get_param( 'id' ) );
		$data = [];

		if ( $request->has_param( 'category' ) ) {
			$data['category'] = $request->get_param( 'category' );
		}
		if ( $request->has_param( 'content' ) ) {
			$data['content'] = $request->get_param( 'content' );
		}

		$updated = Memory::update( $id, $data );

		if ( ! $updated ) {
			return new WP_Error( 'ai_agent_memory_update_failed', __( 'Failed to update memory.', 'ai-agent' ), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [ 'updated' => true, 'id' => $id ], 200 );
	}

	/**
	 * Handle DELETE /memory/{id} — delete a memory.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public static function handle_delete_memory( WP_REST_Request $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$deleted = Memory::delete( $id );

		if ( ! $deleted ) {
			return new WP_Error( 'ai_agent_memory_delete_failed', __( 'Failed to delete memory.', 'ai-agent' ), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}
}
