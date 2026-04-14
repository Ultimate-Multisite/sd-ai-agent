<?php

declare(strict_types=1);
/**
 * Hook Scanner abilities — scan installed plugins and themes for extension hooks.
 *
 * Registers two abilities via the WordPress 7.0+ Abilities API:
 *   - gratis-ai-agent/scan-plugin-hooks
 *   - gratis-ai-agent/scan-theme-hooks
 *
 * @package GratisAiAgent\Abilities
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities;

use GratisAiAgent\PluginBuilder\HookScanner;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HookScannerAbilities — registration class for hook-scanner abilities.
 *
 * @since 1.5.0
 */
class HookScannerAbilities {

	/**
	 * Register hook scanner abilities on init.
	 *
	 * @since 1.5.0
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register all hook scanner abilities with the WordPress Abilities API.
	 *
	 * @since 1.5.0
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'gratis-ai-agent/scan-plugin-hooks',
			[
				'label'         => __( 'Scan Plugin Hooks', 'gratis-ai-agent' ),
				'description'   => __( 'Scan an installed plugin for WordPress actions and filters to enable extension-plugin generation.', 'gratis-ai-agent' ),
				'ability_class' => ScanPluginHooksAbility::class,
			]
		);

		wp_register_ability(
			'gratis-ai-agent/scan-theme-hooks',
			[
				'label'         => __( 'Scan Theme Hooks', 'gratis-ai-agent' ),
				'description'   => __( 'Scan an installed theme for WordPress actions and filters to enable extension-plugin generation.', 'gratis-ai-agent' ),
				'ability_class' => ScanThemeHooksAbility::class,
			]
		);
	}
}

// ─── Ability classes ──────────────────────────────────────────────────────────

/**
 * Scan Plugin Hooks ability.
 *
 * Scans an installed plugin for do_action() and apply_filters() calls to
 * discover hookable extension points for AI-generated addon plugins.
 *
 * @since 1.5.0
 */
class ScanPluginHooksAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Scan Plugin Hooks', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Scan an installed plugin for WordPress actions and filters to enable extension-plugin generation.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'slug'        => [
					'type'        => 'string',
					'description' => 'Plugin slug (directory name under wp-content/plugins/).',
				],
				'filter_type' => [
					'type'        => 'string',
					'enum'        => [ 'action', 'filter', 'all' ],
					'description' => 'Limit results to actions, filters, or return all (default: all).',
				],
				'search'      => [
					'type'        => 'string',
					'description' => 'Optional substring to filter hook names (case-insensitive).',
				],
			],
			'required'   => [ 'slug' ],
		];
	}

	protected function output_schema(): array {
		return self::hook_scan_output_schema();
	}

	/**
	 * Returns the shared JSON Schema for the hook scan result envelope.
	 *
	 * Shared by both ScanPluginHooksAbility and ScanThemeHooksAbility.
	 *
	 * @return array<string,mixed>
	 */
	public static function hook_scan_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'slug'          => [ 'type' => 'string' ],
				'type'          => [
					'type' => 'string',
					'enum' => [ 'plugin', 'theme' ],
				],
				'hooks'         => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'name'        => [ 'type' => 'string' ],
							'type'        => [
								'type' => 'string',
								'enum' => [ 'action', 'filter' ],
							],
							'file'        => [ 'type' => 'string' ],
							'line'        => [ 'type' => 'integer' ],
							'context'     => [ 'type' => 'string' ],
							'param_count' => [ 'type' => 'integer' ],
						],
					],
				],
				'total_hooks'   => [ 'type' => 'integer' ],
				'total_actions' => [ 'type' => 'integer' ],
				'total_filters' => [ 'type' => 'integer' ],
			],
		];
	}

	protected function execute_callback( $input ): array|WP_Error {
		$slug        = (string) ( $input['slug'] ?? '' );
		$filter_type = (string) ( $input['filter_type'] ?? 'all' );
		$search      = (string) ( $input['search'] ?? '' );

		if ( '' === $slug ) {
			return new WP_Error( 'gratis_ai_agent_invalid_slug', __( 'slug is required.', 'gratis-ai-agent' ) );
		}

		$result = HookScanner::scan_plugin( $slug );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::apply_filters_to_result( $result, $filter_type, $search );
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
		];
	}

	/**
	 * Filter scan results by hook type and/or search substring.
	 *
	 * @param array<string,mixed> $result      Full scan result from HookScanner.
	 * @param string              $filter_type 'action', 'filter', or 'all'.
	 * @param string              $search      Substring to match against hook names (empty = no filter).
	 * @return array<string,mixed> Filtered result with updated totals.
	 */
	public static function apply_filters_to_result( array $result, string $filter_type, string $search ): array {
		$hooks = is_array( $result['hooks'] ) ? $result['hooks'] : [];

		if ( 'action' === $filter_type ) {
			$hooks = array_values(
				array_filter(
					$hooks,
					static function ( mixed $h ): bool {
						return is_array( $h ) && isset( $h['type'] ) && 'action' === $h['type'];
					}
				)
			);
		} elseif ( 'filter' === $filter_type ) {
			$hooks = array_values(
				array_filter(
					$hooks,
					static function ( mixed $h ): bool {
						return is_array( $h ) && isset( $h['type'] ) && 'filter' === $h['type'];
					}
				)
			);
		}

		if ( '' !== $search ) {
			$needle = strtolower( $search );
			$hooks  = array_values(
				array_filter(
					$hooks,
					static function ( mixed $h ) use ( $needle ): bool {
						return is_array( $h )
							&& isset( $h['name'] )
							&& str_contains( strtolower( (string) $h['name'] ), $needle );
					}
				)
			);
		}

		$total_actions = 0;
		$total_filters = 0;
		foreach ( $hooks as $hook ) {
			if ( is_array( $hook ) && isset( $hook['type'] ) && 'action' === $hook['type'] ) {
				++$total_actions;
			} else {
				++$total_filters;
			}
		}

		return [
			'slug'          => $result['slug'],
			'type'          => $result['type'],
			'hooks'         => $hooks,
			'total_hooks'   => count( $hooks ),
			'total_actions' => $total_actions,
			'total_filters' => $total_filters,
		];
	}
}

/**
 * Scan Theme Hooks ability.
 *
 * Scans an installed theme for do_action() and apply_filters() calls to
 * discover hookable extension points for AI-generated addon plugins.
 *
 * @since 1.5.0
 */
class ScanThemeHooksAbility extends AbstractAbility {

	protected function label(): string {
		return __( 'Scan Theme Hooks', 'gratis-ai-agent' );
	}

	protected function description(): string {
		return __( 'Scan an installed theme for WordPress actions and filters to enable extension-plugin generation.', 'gratis-ai-agent' );
	}

	protected function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'slug'        => [
					'type'        => 'string',
					'description' => 'Theme slug (directory name under wp-content/themes/).',
				],
				'filter_type' => [
					'type'        => 'string',
					'enum'        => [ 'action', 'filter', 'all' ],
					'description' => 'Limit results to actions, filters, or return all (default: all).',
				],
				'search'      => [
					'type'        => 'string',
					'description' => 'Optional substring to filter hook names (case-insensitive).',
				],
			],
			'required'   => [ 'slug' ],
		];
	}

	protected function output_schema(): array {
		return ScanPluginHooksAbility::hook_scan_output_schema();
	}

	protected function execute_callback( $input ): array|WP_Error {
		$slug        = (string) ( $input['slug'] ?? '' );
		$filter_type = (string) ( $input['filter_type'] ?? 'all' );
		$search      = (string) ( $input['search'] ?? '' );

		if ( '' === $slug ) {
			return new WP_Error( 'gratis_ai_agent_invalid_slug', __( 'slug is required.', 'gratis-ai-agent' ) );
		}

		$result = HookScanner::scan_theme( $slug );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return ScanPluginHooksAbility::apply_filters_to_result( $result, $filter_type, $search );
	}

	protected function permission_callback( $input ): bool {
		return current_user_can( 'manage_options' );
	}

	protected function meta(): array {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
		];
	}
}
