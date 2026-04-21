<?php
/**
 * Plugin Name: AI Agent for WP
 * Plugin URI:  https://github.com/Ultimate-Multisite/gratis-ai-agent
 * Description: Agentic AI loop for WordPress — chat with an AI that can call WordPress abilities (tools) autonomously.
 * Version:     1.7.0
 * Author:      superdav42
 * Author URI:  https://github.com/superdav42
 * License:     GPL-2.0-or-later
 * Requires at least: 7.0
 * Requires PHP: 8.2
 * Text Domain: ai-agent-for-wp
 *
 * @package GratisAiAgent
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GRATIS_AI_AGENT_VERSION', '1.7.0' );
define( 'GRATIS_AI_AGENT_DIR', __DIR__ );
define( 'GRATIS_AI_AGENT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Built-in fallback model ID used when no model is configured in settings
 * and no connector default is available.
 *
 * Developers can override the effective default at runtime via the
 * `gratis_ai_agent_default_model` filter rather than changing this constant.
 */
define( 'GRATIS_AI_AGENT_DEFAULT_MODEL', 'claude-sonnet-4' );

// Load Jetpack Autoloader for PSR-4 autoloading with version conflict resolution.
// Jetpack Autoloader ensures the newest version of shared packages (like php-ai-client) is used.
if ( file_exists( GRATIS_AI_AGENT_DIR . '/vendor/autoload_packages.php' ) ) {
	require_once GRATIS_AI_AGENT_DIR . '/vendor/autoload_packages.php';
} elseif ( file_exists( GRATIS_AI_AGENT_DIR . '/vendor/autoload.php' ) ) {
	require_once GRATIS_AI_AGENT_DIR . '/vendor/autoload.php';
} else {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__(
					'Gratis AI Agent is missing its vendor dependencies. Please run "composer install" in the plugin directory.',
					'gratis-ai-agent',
				),
			);
		},
	);
	return;
}

// WP 7.0 Compatibility: prevent PSR interface conflict with bundled php-ai-client.
//
// WP 7.0 bundles a scoped copy of php-ai-client in wp-includes/php-ai-client/
// with renamed PSR interfaces (WordPress\AiClientDependencies\Psr\*). Our
// Composer copy uses the standard unscoped Psr\* interfaces. When both are
// loaded in the same PHP process, WP_AI_Client_HTTP_Client's declaration
// becomes incompatible with ClientWithOptionsInterface (fatal error).
//
// On WP 7.0+, we register a high-priority SPL autoloader that loads
// WordPress\AiClient\* and WordPress\AiClientDependencies\* from WP core's
// bundled copy (scoped PSR) instead of our Composer vendor directory.
// Jetpack Autoloader still runs but PHP skips it for already-defined classes.
//
// On WP 6.9 (where function_exists('wp_ai_client_prompt') is false), this
// block is skipped and Jetpack loads our bundled copies as normal.
if ( function_exists( 'wp_ai_client_prompt' ) && defined( 'ABSPATH' ) ) {
	$gratis_wp_sdk_dir = ABSPATH . 'wp-includes/php-ai-client/';
	if ( is_dir( $gratis_wp_sdk_dir . 'src' ) ) {
		spl_autoload_register(
			static function ( string $class_name ) use ( $gratis_wp_sdk_dir ): void {
				if ( 0 === strncmp( $class_name, 'WordPress\\AiClient\\', 19 ) ) {
					$relative_class = substr( $class_name, 19 );
					$file           = $gratis_wp_sdk_dir . 'src/' . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';
					if ( file_exists( $file ) ) {
						require $file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
					}
					return;
				}
				if ( 0 === strncmp( $class_name, 'WordPress\\AiClientDependencies\\', 31 ) ) {
					$relative_class = substr( $class_name, 31 );
					$file           = $gratis_wp_sdk_dir . 'third-party/' . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';
					if ( file_exists( $file ) ) {
						require $file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
					}
					return;
				}
			},
			true,  // throw exceptions on error
			true   // prepend — must run BEFORE the Jetpack Autoloader (which also prepends)
		);
	}
	unset( $gratis_wp_sdk_dir );
}

use GratisAiAgent\Bootstrap\LifecycleHandler;
use GratisAiAgent\Plugin;

// Activation / deactivation hooks fire *before* `plugins_loaded`, so they
// cannot be wired through the DI container. `LifecycleHandler` consolidates
// the handful of static calls that used to live inline here.
register_activation_hook( __FILE__, [ LifecycleHandler::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ LifecycleHandler::class, 'deactivate' ] );

// Normalize REQUEST_URI for plain-permalink REST requests.
//
// `XWP_Context::rest()` detects REST context by checking if REQUEST_URI
// contains the wp-json prefix (e.g. `/wp-json/`). WordPress also routes REST
// requests via `?rest_route=/...` when pretty permalinks are disabled (the
// default in fresh wp-env installs and many CI environments).
//
// Without this normalisation, `XWP_Context::rest()` returns false for
// `?rest_route=` requests, so all DI handlers with `context: CTX_REST` are
// never initialised and every REST endpoint returns 404.
//
// The normalisation runs before `xwp_load_app()` queues the container build
// at `plugins_loaded:PHP_INT_MIN`, so XWP_Context::get() caches the correct
// REST context when the container actually initialises.
// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
if ( ! empty( $_GET['rest_route'] ) ) {
	$gratis_rest_prefix = function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json';
	$gratis_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	if ( false === strpos( (string) $gratis_request_uri, $gratis_rest_prefix ) ) {
		$_SERVER['REQUEST_URI'] = '/' . $gratis_rest_prefix . wp_unslash( $_GET['rest_route'] );
	}
	unset( $gratis_rest_prefix, $gratis_request_uri );
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated

// Bootstrap the DI container.
//
// `xwp_load_app()` schedules the container build at its default
// `plugins_loaded:PHP_INT_MIN` so it runs *before* the `Plugin` module's
// own `#[Module(hook: 'plugins_loaded', priority: 1)]` registration fires.
//
// All hook wiring — REST controllers, abilities, admin menus, core services,
// frontend assets — is managed by `#[Handler]` classes registered in
// `GratisAiAgent\Plugin::$handlers`. Nothing else needs to live in this file.
xwp_load_app(
	[
		'id'            => 'gratis-ai-agent',
		'module'        => Plugin::class,
		'autowiring'    => true,
		'compile'       => 'production' === wp_get_environment_type(),
		// The default `compile_class` is `CompiledContainer` + uppercased ID,
		// which produces invalid PHP class names when the ID contains hyphens.
		'compile_class' => 'CompiledContainerGratisAiAgent',
		'compile_dir'   => GRATIS_AI_AGENT_DIR . '/build/di-cache/' . GRATIS_AI_AGENT_VERSION,
	],
);
