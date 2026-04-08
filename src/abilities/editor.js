/**
 * Client-side block editor ability.
 *
 * Registers `gratis-ai-agent-js/insert-block` — inserts a block into the
 * active block editor. Guarded on `wp.data.select('core/block-editor')` being
 * defined so the module is safe to import on non-editor screens (it no-ops
 * cleanly when the editor is not mounted).
 *
 * Annotated `readonly: false` because it writes to the editor state.
 *
 * @module abilities/editor
 */

/**
 * Internal dependencies
 */
import { registerClientAbility } from './registry';

/**
 * Register the `gratis-ai-agent-js/insert-block` ability.
 *
 * The ability is only registered when the block editor store is available.
 * On non-editor screens the guard returns early and no ability is registered,
 * so `getAbility('gratis-ai-agent-js/insert-block')` returns `undefined` on
 * those screens — which is the expected behaviour per acceptance criterion 6.
 *
 * Input schema:
 * - `blockName`  (string, required) — registered block name, e.g. `core/paragraph`.
 * - `attributes` (object, optional) — block attributes.
 * - `innerHTML`  (string, optional) — inner HTML for the block.
 */
export function register() {
	// Guard: only register when the block editor store is available.
	// Use dynamic access so this module is safe to import on non-editor screens.
	if (
		! window.wp ||
		! window.wp.data ||
		! window.wp.data.select( 'core/block-editor' )
	) {
		return;
	}

	registerClientAbility( {
		name: 'gratis-ai-agent-js/insert-block',
		label: 'Insert block into editor',
		description:
			'Inserts a block into the active block editor. Only available on screens where the block editor is mounted.',
		inputSchema: {
			type: 'object',
			properties: {
				blockName: {
					type: 'string',
					description:
						'Registered block name, e.g. "core/paragraph".',
				},
				attributes: {
					type: 'object',
					description: 'Block attributes to set on the new block.',
					properties: {},
				},
				innerHTML: {
					type: 'string',
					description: 'Optional inner HTML for the block.',
				},
			},
			required: [ 'blockName' ],
		},
		outputSchema: {
			type: 'object',
			properties: {
				inserted: {
					type: 'boolean',
					description: 'True when the block was inserted.',
				},
			},
		},
		meta: {
			annotations: {
				readonly: false,
			},
		},
		/**
		 * Execute the insert-block ability.
		 *
		 * @param {Object} input              Ability input.
		 * @param {string} input.blockName    Registered block name.
		 * @param {Object} [input.attributes] Block attributes.
		 * @param {string} [input.innerHTML]  Inner HTML for the block.
		 * @return {Promise<{inserted: boolean}>} Result object.
		 */
		async callback( { blockName, attributes = {}, innerHTML } ) {
			const { createBlock } = window.wp.blocks;
			const { dispatch } = window.wp.data;

			if ( ! createBlock || ! dispatch ) {
				return { inserted: false };
			}

			const block = createBlock( blockName, attributes );

			// If innerHTML is provided, set it on the block's inner content.
			if ( innerHTML && block.innerContent !== undefined ) {
				block.innerContent = [ innerHTML ];
			}

			dispatch( 'core/block-editor' ).insertBlocks( block );
			return { inserted: true };
		},
	} );
}
