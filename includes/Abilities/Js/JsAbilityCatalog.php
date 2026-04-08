<?php

declare(strict_types=1);
/**
 * JS Ability Catalog — PHP mirror of the client-side ability registry.
 *
 * Provides a static list of the `gratis-ai-agent-js/*` abilities that the
 * plugin registers in the browser via `@wordpress/abilities`. This class is
 * a pure data class: no hooks, no execute callbacks. It exists so that:
 *
 * - t164 (AgentLoop pause/resume) can validate client-posted descriptors
 *   against this authoritative list without trusting the client.
 * - The abilities-explorer UI can list client abilities alongside server ones.
 *
 * The ability names and schemas here MUST stay in sync with the JS source
 * (`src/abilities/navigation.js` and `src/abilities/editor.js`). When adding
 * a new client ability, update both files.
 *
 * @package GratisAiAgent\Abilities\Js
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities\Js;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static catalog of client-side (JS) abilities registered by this plugin.
 *
 * Each entry mirrors the shape expected by `wp_register_ability()` / the
 * `@wordpress/abilities` store, extended with a `screens` hint that describes
 * on which admin screens the ability is available.
 */
class JsAbilityCatalog {

	/**
	 * Return the list of client-side ability descriptors.
	 *
	 * Each descriptor is an associative array with the following keys:
	 *
	 * - name         (string)  Fully-qualified ability name, e.g. `gratis-ai-agent-js/navigate-to`.
	 * - label        (string)  Human-readable label.
	 * - description  (string)  Short description of what the ability does.
	 * - category     (string)  Always `gratis-ai-agent-js` for this catalog.
	 * - input_schema (array)   JSON Schema (draft-2020-12) for the ability's input.
	 * - output_schema (array)  JSON Schema for the ability's output.
	 * - annotations  (array)   Ability annotations (e.g. `readonly`).
	 * - screens      (string)  Which screens the ability is available on.
	 *
	 * @return array<int, array{
	 *   name: string,
	 *   label: string,
	 *   description: string,
	 *   category: string,
	 *   input_schema: array<string, mixed>,
	 *   output_schema: array<string, mixed>,
	 *   annotations: array<string, mixed>,
	 *   screens: string,
	 * }>
	 */
	public static function get_descriptors(): array {
		return [
			[
				'name'          => 'gratis-ai-agent-js/navigate-to',
				'label'         => __( 'Navigate to admin page', 'gratis-ai-agent' ),
				'description'   => __( 'Navigates the browser to a wp-admin-relative path. Does not require a page reload when the target is inside the admin SPA.', 'gratis-ai-agent' ),
				'category'      => 'gratis-ai-agent-js',
				'input_schema'  => [
					'type'       => 'object',
					'properties' => [
						'path' => [
							'type'        => 'string',
							'description' => __( 'wp-admin-relative path, e.g. "plugins.php" or "edit.php?post_type=page".', 'gratis-ai-agent' ),
						],
					],
					'required'   => [ 'path' ],
				],
				'output_schema' => [
					'type'       => 'object',
					'properties' => [
						'navigated' => [
							'type'        => 'boolean',
							'description' => __( 'True when navigation was initiated.', 'gratis-ai-agent' ),
						],
					],
				],
				'annotations'   => [
					'readonly' => true,
				],
				'screens'       => 'all',
			],
			[
				'name'          => 'gratis-ai-agent-js/insert-block',
				'label'         => __( 'Insert block into editor', 'gratis-ai-agent' ),
				'description'   => __( 'Inserts a block into the active block editor. Only available on screens where the block editor is mounted.', 'gratis-ai-agent' ),
				'category'      => 'gratis-ai-agent-js',
				'input_schema'  => [
					'type'       => 'object',
					'properties' => [
						'blockName'  => [
							'type'        => 'string',
							'description' => __( 'Registered block name, e.g. "core/paragraph".', 'gratis-ai-agent' ),
						],
						'attributes' => [
							'type'        => 'object',
							'description' => __( 'Block attributes to set on the new block.', 'gratis-ai-agent' ),
							'properties'  => [],
						],
						'innerHTML'  => [
							'type'        => 'string',
							'description' => __( 'Optional inner HTML for the block.', 'gratis-ai-agent' ),
						],
					],
					'required'   => [ 'blockName' ],
				],
				'output_schema' => [
					'type'       => 'object',
					'properties' => [
						'inserted' => [
							'type'        => 'boolean',
							'description' => __( 'True when the block was inserted.', 'gratis-ai-agent' ),
						],
					],
				],
				'annotations'   => [
					'readonly' => false,
				],
				'screens'       => 'block-editor',
			],
		];
	}
}
