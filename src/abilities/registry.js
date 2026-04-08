/**
 * Client-side ability registry helpers.
 *
 * Thin wrapper around `@wordpress/abilities` that:
 * - Registers the `gratis-ai-agent-js` category (idempotent).
 * - Provides `registerClientAbility()` to register individual abilities with
 *   the correct category and annotation shape, guarding against double-registration.
 * - Provides `snapshotDescriptors()` to capture the current set of
 *   `gratis-ai-agent-js/*` ability definitions as plain objects, ready to be
 *   posted to the server in t164.
 *
 * @module abilities/registry
 */

/**
 * WordPress dependencies
 */
import {
	registerAbilityCategory,
	registerAbility,
	getAbilities,
} from '@wordpress/abilities';

/** Category slug used for all client-side abilities registered by this plugin. */
const CATEGORY_SLUG = 'gratis-ai-agent-js';

/** Track whether the category has been registered to keep registration idempotent. */
let categoryRegistered = false;

/**
 * Register the `gratis-ai-agent-js` ability category.
 *
 * Safe to call multiple times — subsequent calls are no-ops.
 */
export function registerCategory() {
	if ( categoryRegistered ) {
		return;
	}
	registerAbilityCategory( CATEGORY_SLUG, {
		label: 'Gratis AI Agent (client)',
		description:
			'Client-side abilities that run in the browser — navigation, block editor operations, and UI interactions.',
	} );
	categoryRegistered = true;
}

/** Track registered ability names to prevent double-registration. */
const registeredAbilities = new Set();

/**
 * Register a single client-side ability into the `gratis-ai-agent-js` category.
 *
 * Merges the caller-supplied definition with the required `category` field and
 * ensures `meta.annotations` is present. Guards against double-registration so
 * entry points can call `ensureRegistered()` multiple times safely.
 *
 * @param {Object}   def                    Ability definition.
 * @param {string}   def.name               Fully-qualified ability name, e.g. `gratis-ai-agent-js/navigate-to`.
 * @param {string}   def.label              Human-readable label.
 * @param {string}   def.description        Short description.
 * @param {Function} def.callback           Async function that executes the ability.
 * @param {Object}   [def.inputSchema]      JSON Schema for the ability's input.
 * @param {Object}   [def.outputSchema]     JSON Schema for the ability's output.
 * @param {Object}   [def.meta]             Additional metadata.
 * @param {Object}   [def.meta.annotations] Ability annotations (e.g. `{ readonly: true }`).
 */
export function registerClientAbility( def ) {
	if ( registeredAbilities.has( def.name ) ) {
		return;
	}
	registerAbility( {
		...def,
		category: CATEGORY_SLUG,
		meta: {
			...( def.meta || {} ),
			annotations: {
				...( def.meta?.annotations || {} ),
			},
		},
	} );
	registeredAbilities.add( def.name );
}

/**
 * Snapshot the current `gratis-ai-agent-js/*` ability definitions.
 *
 * Returns an array of plain objects suitable for JSON serialisation and
 * posting to the server (used by t164 to send client descriptors with each
 * chat request so the agent loop can validate and route them).
 *
 * @return {Array<Object>} Array of ability descriptor objects.
 */
export function snapshotDescriptors() {
	const abilities = getAbilities( { category: CATEGORY_SLUG } );
	if ( ! abilities ) {
		return [];
	}
	return abilities.map( ( ability ) => ( {
		name: ability.name,
		label: ability.label,
		description: ability.description,
		category: ability.category,
		inputSchema: ability.inputSchema,
		outputSchema: ability.outputSchema,
		meta: ability.meta,
	} ) );
}
