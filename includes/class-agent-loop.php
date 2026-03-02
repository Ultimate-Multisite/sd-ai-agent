<?php
/**
 * Core agentic loop orchestration.
 *
 * Sends a prompt, checks for tool calls, executes them,
 * feeds results back, and repeats until the model is done.
 *
 * @package AiAgent
 */

namespace AiAgent;

use WP_AI_Client_Ability_Function_Resolver;
use WP_Error;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;

class Agent_Loop {

	/** @var string */
	private $user_message;

	/** @var string[] Ability names to enable. */
	private $abilities;

	/** @var Message[] Conversation history. */
	private $history;

	/** @var string */
	private $system_instruction;

	/** @var int */
	private $max_iterations;

	/** @var string AI provider ID. */
	private $provider_id;

	/** @var string AI model ID. */
	private $model_id;

	/** @var array Logged tool call activity. */
	private $tool_call_log = [];

	/** @var float */
	private $temperature;

	/** @var int */
	private $max_output_tokens;

	/** @var array Token usage accumulator. */
	private $token_usage = [
		'prompt'     => 0,
		'completion' => 0,
	];

	/**
	 * @param string   $user_message The user's prompt.
	 * @param string[] $abilities    Ability names to enable (empty = all).
	 * @param Message[] $history     Prior messages for multi-turn.
	 * @param array    $options      Optional overrides: system_instruction, max_iterations, provider_id, model_id, temperature, max_output_tokens.
	 */
	public function __construct( string $user_message, array $abilities = [], array $history = [], array $options = [] ) {
		$this->user_message = $user_message;
		$this->abilities    = $abilities;
		$this->history      = $history;

		// Merge explicit options with saved settings as fallbacks.
		$settings = Settings::get();

		$this->provider_id        = $options['provider_id'] ?? ( $settings['default_provider'] ?: '' );
		$this->model_id           = $options['model_id'] ?? ( $settings['default_model'] ?: '' );
		$this->max_iterations     = $options['max_iterations'] ?? ( $settings['max_iterations'] ?: 10 );
		$this->temperature        = $options['temperature'] ?? ( $settings['temperature'] ?? 0.7 );
		$this->max_output_tokens  = $options['max_output_tokens'] ?? ( $settings['max_output_tokens'] ?? 4096 );

		$this->system_instruction = $options['system_instruction'] ?? $this->build_system_instruction( $settings );
	}

	/**
	 * Run the agentic loop.
	 *
	 * @return array{reply: string, history: array, tool_calls: array}|WP_Error
	 */
	public function run() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'ai_agent_missing_client',
				__( 'wp_ai_client_prompt() is not available. WordPress 7.0 or the AI Experiments plugin is required.', 'ai-agent' )
			);
		}

		// Ensure provider auth is available (critical for loopback requests).
		self::ensure_provider_credentials_static();

		// Append the new user message to history.
		$this->history[] = new UserMessage( [ new MessagePart( $this->user_message ) ] );

		$iterations = $this->max_iterations;

		while ( $iterations > 0 ) {
			$iterations--;

			$result = $this->send_prompt();

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			/** @var \WordPress\AiClient\Results\DTO\GenerativeAiResult $result */
			$assistant_message = $result->toMessage();
			$this->history[]   = $assistant_message;

			// Accumulate token usage if available.
			$this->accumulate_tokens( $result );

			// Check if the model wants to call tools.
			if ( ! WP_AI_Client_Ability_Function_Resolver::has_ability_calls( $assistant_message ) ) {
				// No tool calls — we're done.
				$reply = '';

				try {
					$reply = $result->toText();
				} catch ( \RuntimeException $e ) {
					// Model returned no text (unusual), return empty.
					$reply = '';
				}

				return [
					'reply'       => $reply,
					'history'     => $this->serialize_history(),
					'tool_calls'  => $this->tool_call_log,
					'token_usage' => $this->token_usage,
				];
			}

			// Execute the ability calls and get the function response message.
			$this->log_tool_calls( $assistant_message );
			$response_message = WP_AI_Client_Ability_Function_Resolver::execute_abilities( $assistant_message );
			$this->history[]  = $response_message;
			$this->log_tool_responses( $response_message );
		}

		// Exhausted iterations — return what we have.
		return new WP_Error(
			'ai_agent_max_iterations',
			sprintf(
				/* translators: %d: max iterations */
				__( 'Agent reached the maximum of %d iterations without completing.', 'ai-agent' ),
				$this->max_iterations
			)
		);
	}

	/**
	 * Build and send a single prompt with the current history.
	 *
	 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult|WP_Error
	 */
	private function send_prompt() {
		$builder = wp_ai_client_prompt();

		$builder->using_system_instruction( $this->system_instruction );

		// Configure the model via the builder's own API so the SDK handles
		// all dependency binding (auth, HTTP transporter) internally.
		$this->configure_model( $builder );

		// Apply temperature and max tokens if the builder supports them.
		if ( method_exists( $builder, 'using_temperature' ) ) {
			$builder->using_temperature( (float) $this->temperature );
		}

		if ( method_exists( $builder, 'using_max_tokens' ) ) {
			$builder->using_max_tokens( (int) $this->max_output_tokens );
		}

		// Register abilities.
		$abilities = $this->resolve_abilities();
		if ( ! empty( $abilities ) ) {
			$builder->using_abilities( ...$abilities );
		}

		// Pass full conversation history.
		if ( ! empty( $this->history ) ) {
			$builder->with_history( ...$this->history );
		}

		return $builder->generate_text_result();
	}

	/**
	 * Configure the PromptBuilder with the correct provider and model.
	 *
	 * Uses the builder's own provider/preference API so that the SDK
	 * handles model creation and dependency injection (auth, transporter)
	 * through ProviderRegistry::getProviderModel(). This avoids creating
	 * model instances outside the registry which can miss auth binding.
	 *
	 * @param \WP_AI_Client_Prompt_Builder $builder The prompt builder.
	 */
	private function configure_model( $builder ): void {
		$provider_id = $this->provider_id;
		$model_id    = $this->model_id;

		// Resolve provider — fall back to the OpenAI-compatible connector.
		if ( empty( $provider_id ) ) {
			$provider_id = 'ai-provider-for-any-openai-compatible';
		}

		// Resolve model — fall back to the connector's configured default.
		if ( empty( $model_id ) && function_exists( 'OpenAiCompatibleConnector\\get_default_model' ) ) {
			$model_id = \OpenAiCompatibleConnector\get_default_model();
		}

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();

			if ( ! $registry->hasProvider( $provider_id ) ) {
				return;
			}

			if ( ! empty( $model_id ) ) {
				// Directly create the model instance via the registry.
				// This bypasses the SDK's model-listing HTTP call which
				// can fail for OpenAI-compatible endpoints.
				$model = $registry->getProviderModel( $provider_id, $model_id );
				$builder->using_model( $model );
			} else {
				$builder->using_provider( $provider_id );
			}
		} catch ( \Throwable $e ) {
			// Last resort: just set the provider and hope for the best.
			try {
				$builder->using_provider( $provider_id );
			} catch ( \Throwable $e2 ) {
				// Both approaches failed — builder will use default.
			}
		}
	}

	/**
	 * Ensure AI provider credentials are loaded from the database.
	 *
	 * In loopback/background requests the AI Experiments plugin's init
	 * chain may not fully pass credentials to the registry. This method
	 * reads the stored credentials option and sets auth on any provider
	 * that doesn't already have it configured.
	 */
	public static function ensure_provider_credentials_static(): void {
		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
		} catch ( \Throwable $e ) {
			return;
		}

		$auth_class = '\\WordPress\\AiClient\\Providers\\Http\\DTO\\ApiKeyRequestAuthentication';

		if ( ! class_exists( $auth_class ) ) {
			return;
		}

		// Source 1: WordPress 7.0 Connectors API (connectors_ai_*_api_key options).
		if ( function_exists( '_wp_connectors_get_provider_settings' ) ) {
			foreach ( _wp_connectors_get_provider_settings() as $setting_name => $config ) {
				$api_key = _wp_connectors_get_real_api_key( $setting_name, $config['mask'] );

				if ( '' === $api_key || ! $registry->hasProvider( $config['provider'] ) ) {
					continue;
				}

				$registry->setProviderRequestAuthentication(
					$config['provider'],
					new $auth_class( $api_key )
				);
			}
		}

		// Source 2: AI Experiments plugin credentials option.
		$credentials = get_option( 'wp_ai_client_provider_credentials', [] );

		if ( is_array( $credentials ) && ! empty( $credentials ) ) {
			foreach ( $credentials as $provider_id => $api_key ) {
				if ( ! is_string( $api_key ) || '' === $api_key ) {
					continue;
				}

				if ( ! $registry->hasProvider( $provider_id ) ) {
					continue;
				}

				$registry->setProviderRequestAuthentication(
					$provider_id,
					new $auth_class( $api_key )
				);
			}
		}

		// Source 3: OpenAI-compatible connector plugin.
		$compat_provider = 'ai-provider-for-any-openai-compatible';

		if ( $registry->hasProvider( $compat_provider ) && null === $registry->getProviderRequestAuthentication( $compat_provider ) ) {
			$api_key = get_option( 'openai_compat_api_key', '' );

			if ( empty( $api_key ) ) {
				$api_key = 'no-key';
			}

			$registry->setProviderRequestAuthentication(
				$compat_provider,
				new $auth_class( $api_key )
			);
		}
	}

	/**
	 * Resolve ability names to WP_Ability objects.
	 *
	 * @return \WP_Ability[]
	 */
	private function resolve_abilities(): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return [];
		}

		$all = wp_get_abilities();

		// Filter out globally disabled abilities.
		$disabled = Settings::get( 'disabled_abilities' );
		if ( ! empty( $disabled ) && is_array( $disabled ) ) {
			$all = array_filter( $all, function ( $ability ) use ( $disabled ) {
				return ! in_array( $ability->get_name(), $disabled, true );
			} );
		}

		if ( empty( $this->abilities ) ) {
			return array_values( $all );
		}

		$resolved = [];
		foreach ( $this->abilities as $name ) {
			if ( isset( $all[ $name ] ) ) {
				$resolved[] = $all[ $name ];
			}
		}

		return $resolved;
	}

	/**
	 * Log tool calls from an assistant message for transparency.
	 */
	private function log_tool_calls( Message $message ): void {
		foreach ( $message->getParts() as $part ) {
			$call = $part->getFunctionCall();
			if ( $call ) {
				$this->tool_call_log[] = [
					'type' => 'call',
					'id'   => $call->getId(),
					'name' => $call->getName(),
					'args' => $call->getArgs(),
				];
			}
		}
	}

	/**
	 * Log tool responses for transparency.
	 */
	private function log_tool_responses( Message $message ): void {
		foreach ( $message->getParts() as $part ) {
			$response = $part->getFunctionResponse();
			if ( $response ) {
				$this->tool_call_log[] = [
					'type'     => 'response',
					'id'       => $response->getId(),
					'name'     => $response->getName(),
					'response' => $response->getResponse(),
				];
			}
		}
	}

	/**
	 * Serialize conversation history to transportable arrays.
	 *
	 * @return array
	 */
	private function serialize_history(): array {
		return array_map(
			function ( Message $msg ) {
				return $msg->toArray();
			},
			$this->history
		);
	}

	/**
	 * Deserialize conversation history from arrays back to Message objects.
	 *
	 * @param array $data Serialized history arrays.
	 * @return Message[]
	 */
	public static function deserialize_history( array $data ): array {
		return array_map(
			function ( $item ) {
				return Message::fromArray( $item );
			},
			$data
		);
	}

	/**
	 * Build the system instruction, incorporating custom prompt and memories.
	 *
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	private function build_system_instruction( array $settings ): string {
		// Use custom system prompt if set, otherwise the built-in default.
		$custom = $settings['system_prompt'] ?? '';
		$base   = ! empty( $custom ) ? $custom : self::default_system_instruction();

		// Append memory section if memories exist.
		$memory_text = Memory::get_formatted_for_prompt();
		if ( ! empty( $memory_text ) ) {
			$base .= "\n\n" . $memory_text;
		}

		// Append skill index if skills are available.
		$skill_index = Skill::get_index_for_prompt();
		if ( ! empty( $skill_index ) ) {
			$base .= "\n\n" . $skill_index;
		}

		// If auto-memory is enabled, tell the agent about memory abilities.
		$auto_memory = $settings['auto_memory'] ?? true;
		if ( $auto_memory ) {
			$base .= "\n\n## Memory Instructions\n"
				. "You have access to persistent memory tools. Use them proactively:\n"
				. "- Use **ai-agent/memory-save** to remember important information the user tells you (preferences, site details, workflows).\n"
				. "- Use **ai-agent/memory-list** to recall what you've previously stored.\n"
				. "- Use **ai-agent/memory-delete** to remove outdated memories.\n"
				. "Save memories when the user shares reusable facts, preferences, or context that would be valuable in future conversations.";
		}

		return $base;
	}

	/**
	 * Default system instruction for the agent.
	 *
	 * @return string
	 */
	public static function get_default_system_prompt(): string {
		return self::default_system_instruction();
	}

	/**
	 * Internal default system instruction builder.
	 *
	 * @return string
	 */
	private static function default_system_instruction(): string {
		$wp_path  = ABSPATH;
		$site_url = get_site_url();

		return "You are a helpful WordPress assistant with access to tools that can interact with this WordPress site.\n\n"
			. "## WordPress Environment\n"
			. "- WordPress path: {$wp_path}\n"
			. "- Site URL: {$site_url}\n"
			. "- WP-CLI is available at: wp\n\n"
			. "## How to interact with WordPress\n"
			. "Use the available tools to help the user accomplish their goals. "
			. "When you need information from the site, call the appropriate tool rather than guessing. "
			. "After using tools, summarize the results clearly for the user.";
	}

	/**
	 * Accumulate token usage from an AI result.
	 *
	 * @param mixed $result The AI result object.
	 */
	private function accumulate_tokens( $result ): void {
		try {
			if ( method_exists( $result, 'getUsage' ) ) {
				$usage = $result->getUsage();
				if ( $usage ) {
					if ( method_exists( $usage, 'getPromptTokens' ) ) {
						$this->token_usage['prompt'] += (int) $usage->getPromptTokens();
					}
					if ( method_exists( $usage, 'getCompletionTokens' ) ) {
						$this->token_usage['completion'] += (int) $usage->getCompletionTokens();
					}
				}
			}
		} catch ( \Throwable $e ) {
			// Token tracking is best-effort.
		}
	}
}
