/**
 * Client-side block-editor abilities.
 *
 * Registers `gratis-ai-agent-js/insert-block`, which inserts a Gutenberg
 * block into the currently loaded block editor (post, page, or site editor).
 *
 * Guarded: if the block-editor store is not mounted when registration is
 * attempted, the ability is **not** registered at all, so it does not
 * appear on screens where it cannot be executed. This keeps the tool list
 * the model sees accurate per-screen.
 */

import { __ } from '@wordpress/i18n';

import { registerClientAbility, CLIENT_NAMESPACE_PREFIX } from './registry';

/**
 * Detect whether the block editor is mounted on the current page.
 *
 * We probe `wp.data.select('core/block-editor')` lazily via the global to
 * avoid pulling `@wordpress/data` into this module's dependency graph — the
 * block-editor store only exists on editor screens, and importing
 * `@wordpress/block-editor` unconditionally would pull a large bundle into
 * every entry point.
 *
 * @return {boolean} True if the block-editor store is available.
 */
function isBlockEditorAvailable() {
	try {
		if ( typeof wp === 'undefined' || ! wp.data || ! wp.data.select ) {
			return false;
		}
		return Boolean( wp.data.select( 'core/block-editor' ) );
	} catch ( _err ) {
		return false;
	}
}

/**
 * Insert a block using `@wordpress/blocks` + `@wordpress/block-editor`,
 * which we access through the global `wp` namespace to avoid a hard build
 * dependency from entry points that never load the editor.
 *
 * @param {string} blockName  Block type (e.g. "core/paragraph").
 * @param {Object} attributes Block attributes.
 * @return {{success: boolean, clientId?: string}} Insertion result.
 */
function insertBlockViaDataStore( blockName, attributes ) {
	if (
		typeof wp === 'undefined' ||
		! wp.blocks ||
		! wp.blocks.createBlock ||
		! wp.data ||
		! wp.data.dispatch
	) {
		throw new Error(
			'Block editor APIs are not available on this screen.'
		);
	}

	const block = wp.blocks.createBlock( blockName, attributes || {} );
	wp.data.dispatch( 'core/block-editor' ).insertBlocks( block );

	return { success: true, clientId: block.clientId };
}

/**
 * Register editor abilities, but only if the block editor is mounted on
 * the current page. On non-editor screens this function is a no-op — the
 * `insert-block` ability will simply not appear in the client registry,
 * and `snapshotDescriptors()` will therefore omit it from the descriptors
 * posted to the server.
 *
 * @return {Promise<void>}
 */
export async function registerEditorAbilities() {
	if ( ! isBlockEditorAvailable() ) {
		return;
	}

	await registerClientAbility( {
		name: `${ CLIENT_NAMESPACE_PREFIX }insert-block`,
		label: __( 'Insert block into editor', 'gratis-ai-agent' ),
		description: __(
			'Insert a Gutenberg block into the currently loaded block editor. Use core/paragraph with { "content": "…" } for text, core/heading with { "level": 2, "content": "…" } for headings.',
			'gratis-ai-agent'
		),
		input_schema: {
			type: 'object',
			properties: {
				blockName: {
					type: 'string',
					description:
						'Block type name, e.g. "core/paragraph", "core/heading", "core/image".',
					minLength: 1,
				},
				attributes: {
					type: 'object',
					description:
						'Block attributes. For core/paragraph, use { "content": "..." }. For core/heading, use { "level": 2, "content": "..." }.',
				},
			},
			required: [ 'blockName' ],
		},
		output_schema: {
			type: 'object',
			properties: {
				success: { type: 'boolean' },
				clientId: { type: 'string' },
			},
			required: [ 'success' ],
		},
		meta: {
			annotations: {
				readonly: false,
			},
		},
		screens: [ 'editor' ],
		callback: async ( { blockName, attributes } ) => {
			return insertBlockViaDataStore( blockName, attributes );
		},
	} );
}
