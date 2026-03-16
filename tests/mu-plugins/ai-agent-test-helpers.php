<?php
/**
 * MU-Plugin: Test helpers for AI Agent development.
 *
 * Loaded automatically by wp-env in the development environment.
 * Provides debugging aids and test fixtures.
 *
 * @package AiAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Enable error display in development.
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	ini_set( 'display_errors', '1' ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed
}

/**
 * Stub for wp_ai_client_prompt() — available in WordPress 6.9+ core.
 *
 * Provides a no-op implementation so the floating widget and other plugin
 * features that guard on function_exists( 'wp_ai_client_prompt' ) load
 * correctly in wp-env E2E test environments where the function may not
 * yet be present.
 *
 * @param string $prompt  The prompt text.
 * @param array  $options Optional options array.
 * @return string Empty string (stub — no AI call is made).
 */
if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
	function wp_ai_client_prompt( string $prompt, array $options = [] ): string { // phpcs:ignore
		return '';
	}
}
