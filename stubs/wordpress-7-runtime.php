<?php
/**
 * Stubs for WordPress 7.0+ runtime APIs not yet covered by php-stubs/wordpress-stubs,
 * the intelephense built-in WordPress stub set, or the Composer packages that
 * polyfill them on WP 6.9.
 *
 * Covers:
 *   - WP_AI_Client_Ability_Function_Resolver (WP 7.0 bridge class, wp-includes/ai-client/)
 *   - WP_AI_Client_Prompt_Builder / wp_ai_client_prompt() (WP 7.0 bridge, wp-includes/ai-client/)
 *   - OpenAiCompatibleConnector namespace functions (WP Connectors API)
 *   - _wp_connectors_get_* internal functions (WP Connectors API)
 *   - WP_CLI class and constant (WP-CLI)
 *
 * NOT covered here (provided by Composer packages in vendor/):
 *   - WordPress\AiClient\* SDK — from wordpress/php-ai-client package
 *   - WP_Ability, wp_register_ability(), wp_get_abilities() etc. — from wordpress/abilities-api package
 *
 * These stubs exist solely for LSP (intelephense) type resolution and are
 * never loaded at runtime.
 *
 * @package GratisAiAgent
 */

// phpcs:disable

namespace OpenAiCompatibleConnector {

	/**
	 * Get the default model ID for the OpenAI-compatible connector (stub).
	 *
	 * @return string
	 */
	function get_default_model(): string { return ''; }

	/**
	 * List available models via REST (stub).
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	function rest_list_models( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		return new \WP_REST_Response();
	}
}

namespace {

	/** WP-CLI is active (stub constant — false at analysis time). */
	const WP_CLI = false;

	/**
	 * WP-CLI framework class (stub).
	 */
	class WP_CLI {
		/**
		 * @param string          $name
		 * @param callable|string $callable
		 * @param array           $args
		 */
		public static function add_command( string $name, $callable, array $args = array() ): void {}

		/** @param string $message */
		public static function success( string $message ): void {}

		/**
		 * @param string $message
		 * @param bool   $exit
		 */
		public static function error( string $message, bool $exit = true ): void {}

		/** @param string $message */
		public static function log( string $message ): void {}

		/** @param string $message */
		public static function warning( string $message ): void {}
	}

	/**
	 * Get all registered connector provider settings.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function _wp_connectors_get_provider_settings(): array { return array(); }

	/**
	 * Get the real (unmasked) API key for a connector setting.
	 *
	 * @param string $setting_name Setting name.
	 * @param string $mask         Masked key value.
	 * @return string
	 */
	function _wp_connectors_get_real_api_key( string $setting_name, string $mask ): string { return ''; }

	/**
	 * Resolves between WP Ability names and AI function call names (stub).
	 *
	 * This is a WP 7.0 bridge class from wp-includes/ai-client/, NOT provided
	 * by the wordpress/php-ai-client or wordpress/abilities-api Composer packages.
	 *
	 * @since 7.0.0
	 */
	class WP_AI_Client_Ability_Function_Resolver {
		/**
		 * Constructor.
		 *
		 * Accepts ability objects or ability name strings.
		 *
		 * @param WP_Ability|string ...$abilities Abilities to register (objects or name strings).
		 */
		public function __construct( WP_Ability|string ...$abilities ) {}

		/** @param string $ability_name */
		public static function ability_name_to_function_name( string $ability_name ): string { return ''; }

		/** @param string $function_name */
		public static function function_name_to_ability_name( string $function_name ): string { return ''; }

		/** @return array<int, array<string, mixed>> */
		public function get_tools(): array { return array(); }

		/**
		 * Check whether a message contains ability (tool) calls.
		 *
		 * @param \WordPress\AiClient\Messages\DTO\Message $message
		 * @return bool
		 */
		public function has_ability_calls( \WordPress\AiClient\Messages\DTO\Message $message ): bool { return false; }

		/**
		 * Check whether a single function call is an ability call.
		 *
		 * @param \WordPress\AiClient\Tools\DTO\FunctionCall $call The function call to check.
		 * @return bool
		 */
		public function is_ability_call( \WordPress\AiClient\Tools\DTO\FunctionCall $call ): bool { return false; }

		/**
		 * Execute all ability calls in a message and return the response message.
		 *
		 * @param \WordPress\AiClient\Messages\DTO\Message $message
		 * @return \WordPress\AiClient\Messages\DTO\Message
		 */
		public function execute_abilities( \WordPress\AiClient\Messages\DTO\Message $message ): \WordPress\AiClient\Messages\DTO\Message {
			return new \WordPress\AiClient\Messages\DTO\UserMessage();
		}

		/**
		 * Execute a single ability call and return the function response.
		 *
		 * @param \WordPress\AiClient\Tools\DTO\FunctionCall $call The function call to execute.
		 * @return \WordPress\AiClient\Tools\DTO\FunctionResponse
		 */
		public function execute_ability( \WordPress\AiClient\Tools\DTO\FunctionCall $call ): \WordPress\AiClient\Tools\DTO\FunctionResponse {
			return new \WordPress\AiClient\Tools\DTO\FunctionResponse( '', '' );
		}
	}

	/**
	 * WordPress 7.0+ AI Client prompt builder (stub).
	 *
	 * This is a WP 7.0 bridge class from wp-includes/ai-client/, NOT provided
	 * by the wordpress/php-ai-client or wordpress/abilities-api Composer packages.
	 * Returned by wp_ai_client_prompt(). All configuration methods return
	 * `static` to support fluent chaining.
	 *
	 * @since 7.0.0
	 */
	class WP_AI_Client_Prompt_Builder {

		/**
		 * Constructor.
		 *
		 * @param string $prompt Initial prompt text.
		 */
		public function __construct( string $prompt = '' ) {}

		/**
		 * Set the system instruction for this prompt.
		 *
		 * @param string $instruction System instruction text.
		 * @return static
		 */
		public function using_system_instruction( string $instruction ): static { return $this; }

		/**
		 * Set the sampling temperature.
		 *
		 * @param float $temperature Temperature value (0.0–1.0).
		 * @return static
		 */
		public function using_temperature( float $temperature ): static { return $this; }

		/**
		 * Set the number of response candidates to generate.
		 *
		 * @param int $count Candidate count.
		 * @return static
		 */
		public function using_candidate_count( int $count ): static { return $this; }

		/**
		 * Set a model preference by model ID string.
		 *
		 * @param string $model_id Model identifier.
		 * @return static
		 */
		public function using_model_preference( string $model_id ): static { return $this; }

		/**
		 * Set the model object (from ModelRegistry::getProviderModel()).
		 *
		 * @param mixed $model Model instance.
		 * @return static
		 */
		public function using_model( mixed $model ): static { return $this; }

		/**
		 * Set the provider by provider ID.
		 *
		 * @param string $provider_id Provider identifier.
		 * @return static
		 */
		public function using_provider( string $provider_id ): static { return $this; }

		/**
		 * Set the maximum number of output tokens.
		 *
		 * @param int $tokens Token limit.
		 * @return static
		 */
		public function using_max_tokens( int $tokens ): static { return $this; }

		/**
		 * Register abilities (tools) available to the model.
		 *
		 * @param WP_Ability ...$abilities Ability instances.
		 * @return static
		 */
		public function using_abilities( WP_Ability ...$abilities ): static { return $this; }

		/**
		 * Provide conversation history.
		 *
		 * @param \WordPress\AiClient\Messages\DTO\Message ...$history History messages.
		 * @return static
		 */
		public function with_history( \WordPress\AiClient\Messages\DTO\Message ...$history ): static { return $this; }

		/**
		 * Attach a file (data URI) to the prompt.
		 *
		 * @param string $file Data URI string.
		 * @return static
		 */
		public function with_file( string $file ): static { return $this; }

		/**
		 * Request a structured JSON response conforming to the given schema.
		 *
		 * @param mixed $schema JSON Schema array or object.
		 * @return static
		 */
		public function as_json_response( mixed $schema ): static { return $this; }

		/**
		 * Generate a single text response.
		 *
		 * @return string|\WP_Error
		 */
		public function generate_text(): string|\WP_Error { return ''; }

		/**
		 * Generate multiple candidate text responses.
		 *
		 * @return string[]|\WP_Error
		 */
		public function generate_texts(): array|\WP_Error { return array(); }

		/**
		 * Generate a response and return the full GenerativeAiResult object.
		 *
		 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult|\WP_Error
		 */
		public function generate_text_result(): \WordPress\AiClient\Results\DTO\GenerativeAiResult|\WP_Error {
			return new \WordPress\AiClient\Results\DTO\GenerativeAiResult();
		}
	}

	/**
	 * Create a new WP AI Client prompt builder.
	 *
	 * Returns a fluent WP_AI_Client_Prompt_Builder instance pre-configured
	 * with the given prompt text. Call configuration methods and then one
	 * of the generate_*() methods to send the request.
	 *
	 * This is a WP 7.0 bridge function from wp-includes/ai-client/, NOT provided
	 * by the wordpress/php-ai-client Composer package.
	 *
	 * @since 7.0.0
	 *
	 * @param string $prompt Initial prompt text (optional — may also be set
	 *                       via using_system_instruction()).
	 * @return WP_AI_Client_Prompt_Builder
	 */
	function wp_ai_client_prompt( string $prompt = '' ): WP_AI_Client_Prompt_Builder {
		return new WP_AI_Client_Prompt_Builder( $prompt );
	}
}
