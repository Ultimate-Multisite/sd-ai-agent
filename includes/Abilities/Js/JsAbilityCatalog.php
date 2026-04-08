<?php

declare(strict_types=1);
/**
 * Pure metadata catalog of client-side (JavaScript) abilities.
 *
 * Mirrors the metadata of `gratis-ai-agent-js/*` abilities that are
 * registered in the browser via `src/abilities/*.js`. This class is the
 * authoritative PHP-side list of which ability names are client-only, so
 * server code (AgentLoop, REST controllers, tests) can validate descriptors
 * posted by the browser without trusting the client.
 *
 * No execute callback lives here — this is data only. Execution happens
 * in the browser via `wp.data.dispatch('core/abilities').executeAbility()`.
 *
 * The JS side and this file are two hand-maintained mirrors of the same list.
 * Keep them in sync: when adding a `gratis-ai-agent-js/*` ability in
 * `src/abilities/*.js`, add a matching descriptor here.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities\Js;

/**
 * Static metadata catalog of gratis-ai-agent-js/* abilities.
 */
class JsAbilityCatalog {

	/**
	 * Client-side category slug shared by all abilities in this catalog.
	 */
	public const CATEGORY_SLUG = 'gratis-ai-agent-js';

	/**
	 * Namespace prefix for ability names. Chosen distinct from the PHP-side
	 * `gratis-ai-agent/*` namespace so the client and server registries
	 * cannot collide even when core-abilities mirrors PHP abilities into the
	 * same JS store.
	 */
	public const NAMESPACE_PREFIX = 'gratis-ai-agent-js/';

	/**
	 * Get the client-side ability category descriptor.
	 *
	 * @return array{slug: string, label: string, description: string}
	 */
	public static function get_category(): array {
		return array(
			'slug'        => self::CATEGORY_SLUG,
			'label'       => __( 'Gratis AI Agent — Client-side', 'gratis-ai-agent' ),
			'description' => __(
				'Client-side abilities provided by the Gratis AI Agent plugin. Execute in the browser without a server round-trip.',
				'gratis-ai-agent'
			),
		);
	}

	/**
	 * Get the catalog of client-side ability descriptors.
	 *
	 * Each descriptor is a plain array mirroring the shape expected by
	 * `@wordpress/abilities` `registerAbility()`, minus the `callback` (which
	 * only exists on the client side):
	 *
	 *   - name          — fully qualified ability name
	 *   - label         — human-readable label
	 *   - description   — human-readable description shown in the tool list
	 *   - category      — always self::CATEGORY_SLUG
	 *   - input_schema  — JSON Schema (draft-04) for tool arguments
	 *   - output_schema — JSON Schema for the return value
	 *   - meta          — { annotations: { readonly, destructive, idempotent } }
	 *   - screens       — list of admin-screen predicates on which this
	 *                     ability should be offered to the model. Values:
	 *                       * '*'                — every screen
	 *                       * 'editor'           — only when the block editor
	 *                                              is loaded (post/page/site
	 *                                              editor contexts)
	 *                       * 'admin'            — any wp-admin screen
	 *                     The client-side `snapshotDescriptors()` helper
	 *                     filters by these before posting to the agent loop.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_descriptors(): array {
		return array(
			array(
				'name'          => self::NAMESPACE_PREFIX . 'navigate-to',
				'label'         => __( 'Navigate to admin page', 'gratis-ai-agent' ),
				'description'   => __(
					'Navigate the browser to a wp-admin-relative path (e.g. "plugins.php", "edit.php?post_type=page"). Runs client-side without requiring the model to ask the user to click.',
					'gratis-ai-agent'
				),
				'category'      => self::CATEGORY_SLUG,
				'input_schema'  => array(
					'type'       => 'object',
					'properties' => array(
						'path' => array(
							'type'        => 'string',
							'description' => 'A wp-admin-relative path, e.g. "plugins.php", "edit.php?post_type=page", "users.php".',
							'minLength'   => 1,
						),
					),
					'required'   => array( 'path' ),
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'url'     => array( 'type' => 'string' ),
					),
					'required'   => array( 'success' ),
				),
				'meta'          => array(
					'annotations' => array(
						'readonly' => true,
					),
				),
				'screens'       => array( 'admin' ),
			),
			array(
				'name'          => self::NAMESPACE_PREFIX . 'insert-block',
				'label'         => __( 'Insert block into editor', 'gratis-ai-agent' ),
				'description'   => __(
					'Insert a Gutenberg block into the currently loaded block editor (post, page, or site editor). Only available when an editor is mounted on the page.',
					'gratis-ai-agent'
				),
				'category'      => self::CATEGORY_SLUG,
				'input_schema'  => array(
					'type'       => 'object',
					'properties' => array(
						'blockName'  => array(
							'type'        => 'string',
							'description' => 'Block type name, e.g. "core/paragraph", "core/heading", "core/image".',
							'minLength'   => 1,
						),
						'attributes' => array(
							'type'        => 'object',
							'description' => 'Block attributes. For core/paragraph, use { "content": "..." }. For core/heading, use { "level": 2, "content": "..." }.',
						),
					),
					'required'   => array( 'blockName' ),
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'success'  => array( 'type' => 'boolean' ),
						'clientId' => array( 'type' => 'string' ),
					),
					'required'   => array( 'success' ),
				),
				'meta'          => array(
					'annotations' => array(
						'readonly' => false,
					),
				),
				'screens'       => array( 'editor' ),
			),
		);
	}

	/**
	 * Look up a single descriptor by ability name.
	 *
	 * @param string $name Fully qualified ability name.
	 * @return array<string, mixed>|null Descriptor array, or null if not in the catalog.
	 */
	public static function get_descriptor( string $name ): ?array {
		foreach ( self::get_descriptors() as $descriptor ) {
			if ( $descriptor['name'] === $name ) {
				return $descriptor;
			}
		}

		return null;
	}

	/**
	 * Check whether an ability name is registered in this catalog.
	 *
	 * Used by AgentLoop (t164) to decide whether a tool call should be
	 * executed server-side or returned to the client for in-browser dispatch.
	 *
	 * @param string $name Fully qualified ability name.
	 * @return bool True if the name is a client-side ability.
	 */
	public static function is_client_ability( string $name ): bool {
		return null !== self::get_descriptor( $name );
	}

	/**
	 * Get the list of client ability names in this catalog.
	 *
	 * @return list<string>
	 */
	public static function get_ability_names(): array {
		$names = array();
		foreach ( self::get_descriptors() as $descriptor ) {
			$names[] = (string) $descriptor['name'];
		}
		return $names;
	}
}
