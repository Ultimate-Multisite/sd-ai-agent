/**
 * Client-side ability registry wrapper.
 *
 * Thin helpers around the WordPress 7.0 `@wordpress/abilities` API that:
 *
 *   1. Register the shared `gratis-ai-agent-js` category once, idempotently.
 *   2. Register individual client-side abilities with the correct shape
 *      (category, meta.annotations, etc.) and guard against double
 *      registration when entry points import the module multiple times.
 *   3. Snapshot the current registered `gratis-ai-agent-js/*` abilities as
 *      plain descriptor objects (name, schemas, annotations) that the
 *      agent-loop round-trip in t164 will POST to the server alongside the
 *      user message.
 *
 * `@wordpress/abilities` is a WordPress 7.0 script module. It is loaded via
 * `wp_enqueue_script_module( '@wordpress/abilities' )` from PHP, which adds
 * the bare specifier to the document's import map. We use a dynamic
 * `import()` with `webpackIgnore: true` so webpack leaves the specifier
 * alone at build time and the browser resolves it at runtime via the import
 * map — this lets our classic-script bundles interop with the script-module
 * package without a build-time dependency.
 *
 * @see https://make.wordpress.org/core/2026/03/24/client-side-abilities-api-in-wordpress-7-0/
 */

/* eslint-disable import/no-unresolved */

import { __ } from '@wordpress/i18n';

/**
 * Category slug used for every client-side ability registered by this
 * plugin. Must match `JsAbilityCatalog::CATEGORY_SLUG` on the PHP side.
 */
export const CLIENT_CATEGORY_SLUG = 'gratis-ai-agent-js';

/**
 * Namespace prefix for ability names. Chosen distinct from the PHP-side
 * `gratis-ai-agent/*` namespace so the two registries cannot collide.
 */
export const CLIENT_NAMESPACE_PREFIX = 'gratis-ai-agent-js/';

/**
 * Cached reference to the `@wordpress/abilities` module once loaded.
 *
 * @type {Promise<Object>|null}
 */
let abilitiesModulePromise = null;

/**
 * Set of ability names already registered this page load — prevents
 * "ability already registered" errors when entry points import the module
 * more than once.
 */
const registeredAbilityNames = new Set();

/**
 * Whether the shared category has been registered this page load.
 */
let categoryRegistered = false;

/**
 * Lazily load the `@wordpress/abilities` script module via a dynamic import.
 *
 * The `webpackIgnore: true` magic comment tells webpack not to bundle or
 * transform this import — the browser resolves the bare specifier at
 * runtime against the import map installed by
 * `wp_enqueue_script_module( '@wordpress/abilities' )`.
 *
 * @return {Promise<Object>} The loaded module namespace.
 */
async function loadAbilitiesModule() {
	if ( abilitiesModulePromise ) {
		return abilitiesModulePromise;
	}

	abilitiesModulePromise = import(
		/* webpackIgnore: true */ '@wordpress/abilities'
	).catch( ( err ) => {
		// Reset so a subsequent caller can retry (e.g. in tests or HMR).
		abilitiesModulePromise = null;
		// eslint-disable-next-line no-console
		console.warn(
			'[gratis-ai-agent] @wordpress/abilities not available; client-side abilities will not be registered.',
			err
		);
		return null;
	} );

	return abilitiesModulePromise;
}

/**
 * Register the shared `gratis-ai-agent-js` category if not already present.
 *
 * Categories are required to exist before any ability in them is registered.
 * This function is idempotent: subsequent calls are no-ops.
 *
 * @return {Promise<void>}
 */
export async function ensureCategoryRegistered() {
	if ( categoryRegistered ) {
		return;
	}

	const mod = await loadAbilitiesModule();
	if ( ! mod ) {
		return;
	}

	try {
		// Check whether another plugin or an earlier call already registered
		// the category — getAbilityCategory returns undefined when absent.
		const existing = mod.getAbilityCategory
			? mod.getAbilityCategory( CLIENT_CATEGORY_SLUG )
			: undefined;

		if ( ! existing && mod.registerAbilityCategory ) {
			mod.registerAbilityCategory( CLIENT_CATEGORY_SLUG, {
				label: __( 'Gratis AI Agent — Client-side', 'gratis-ai-agent' ),
				description: __(
					'Client-side abilities provided by the Gratis AI Agent plugin. Execute in the browser without a server round-trip.',
					'gratis-ai-agent'
				),
			} );
		}

		categoryRegistered = true;
	} catch ( err ) {
		// eslint-disable-next-line no-console
		console.warn(
			'[gratis-ai-agent] Failed to register client ability category:',
			err
		);
	}
}

/**
 * Register a single client-side ability.
 *
 * The definition object mirrors `registerAbility()`'s argument shape, with a
 * couple of conveniences:
 *
 *   - `category` is forced to `CLIENT_CATEGORY_SLUG` — callers must not set it.
 *   - `meta.annotations` is merged with sensible defaults; callers pass the
 *     fields they care about.
 *   - Double-registration is guarded: subsequent calls with the same name
 *     are silently ignored so entry points can import the module multiple
 *     times.
 *
 * @param {Object}        definition               The ability definition.
 * @param {string}        definition.name          Fully qualified ability name, must start with `gratis-ai-agent-js/`.
 * @param {string}        definition.label         Human-readable label.
 * @param {string}        definition.description   Human-readable description shown to the model.
 * @param {Object}        definition.input_schema  JSON Schema (draft-04) for arguments.
 * @param {Object}        definition.output_schema JSON Schema for the return value.
 * @param {Object}        [definition.meta]        Optional meta; annotations merged under meta.annotations.
 * @param {Function}      definition.callback      Async handler receiving validated args; returns output.
 * @param {Array<string>} [definition.screens]     Screen predicates (advisory; filtered by snapshotDescriptors).
 * @return {Promise<boolean>} Resolves true if newly registered, false if already present or load failed.
 */
export async function registerClientAbility( definition ) {
	if ( ! definition || typeof definition !== 'object' ) {
		return false;
	}

	const {
		name,
		label,
		description,
		input_schema: inputSchema,
		output_schema: outputSchema,
		meta = {},
		callback,
		screens = [ 'admin' ],
	} = definition;

	if (
		typeof name !== 'string' ||
		! name.startsWith( CLIENT_NAMESPACE_PREFIX )
	) {
		// eslint-disable-next-line no-console
		console.warn(
			`[gratis-ai-agent] Refusing to register ability with invalid name "${ name }" — must start with "${ CLIENT_NAMESPACE_PREFIX }".`
		);
		return false;
	}

	if ( registeredAbilityNames.has( name ) ) {
		return false;
	}

	await ensureCategoryRegistered();

	const mod = await loadAbilitiesModule();
	if ( ! mod || ! mod.registerAbility ) {
		return false;
	}

	// If another loader already registered the ability (e.g. a second plugin
	// bundle, or a hot reload), skip — registerAbility throws on duplicates.
	try {
		if ( mod.getAbility && mod.getAbility( name ) ) {
			registeredAbilityNames.add( name );
			return false;
		}
	} catch ( _err ) {
		// Ignore — not every build exposes getAbility consistently.
	}

	try {
		mod.registerAbility( {
			name,
			label,
			description,
			category: CLIENT_CATEGORY_SLUG,
			input_schema: inputSchema,
			output_schema: outputSchema,
			meta: {
				...( meta || {} ),
				annotations: {
					readonly: false,
					destructive: false,
					idempotent: false,
					...( ( meta && meta.annotations ) || {} ),
				},
				// Keep screen predicates on meta so `snapshotDescriptors()`
				// can read them back without needing a side-channel.
				gratisAiAgent: {
					screens,
				},
			},
			callback,
		} );

		registeredAbilityNames.add( name );
		return true;
	} catch ( err ) {
		// eslint-disable-next-line no-console
		console.warn(
			`[gratis-ai-agent] Failed to register client ability "${ name }":`,
			err
		);
		return false;
	}
}

/**
 * Detect whether the current admin screen matches a given predicate.
 *
 * Currently understood predicates:
 *
 *   - `'*'`     — always true
 *   - `'admin'` — true when inside `wp-admin` (we currently only register
 *                 from admin entry points, so this is effectively always true
 *                 at call time; kept for symmetry with PHP)
 *   - `'editor'` — true when the block editor store is mounted, detected by
 *                  probing `wp.data.select('core/block-editor')` lazily
 *
 * @param {string} predicate Screen predicate.
 * @return {boolean} True if the current screen matches.
 */
function matchesScreen( predicate ) {
	if ( predicate === '*' || predicate === 'admin' ) {
		return true;
	}

	if ( predicate === 'editor' ) {
		try {
			// Access via the global wp.data to avoid a hard import dependency
			// on @wordpress/data from a module that may load before it.
			const select =
				typeof wp !== 'undefined' && wp.data && wp.data.select;
			if ( ! select ) {
				return false;
			}
			const store = select( 'core/block-editor' );
			return Boolean( store );
		} catch ( _err ) {
			return false;
		}
	}

	return false;
}

/**
 * Snapshot the currently registered `gratis-ai-agent-js/*` abilities as
 * plain descriptor objects, filtered by the current screen context.
 *
 * t164 will call this immediately before POSTing a chat message, so the
 * server can merge these into the model's tool list.
 *
 * @return {Promise<Array<Object>>} Filtered descriptor array.
 */
export async function snapshotDescriptors() {
	const mod = await loadAbilitiesModule();
	if ( ! mod || ! mod.getAbilities ) {
		return [];
	}

	let list = [];
	try {
		list = mod.getAbilities( { category: CLIENT_CATEGORY_SLUG } ) || [];
	} catch ( err ) {
		// eslint-disable-next-line no-console
		console.warn(
			'[gratis-ai-agent] Failed to read client abilities registry:',
			err
		);
		return [];
	}

	const filtered = [];
	for ( const ability of list ) {
		const screens = ( ability &&
			ability.meta &&
			ability.meta.gratisAiAgent &&
			ability.meta.gratisAiAgent.screens ) || [ 'admin' ];

		const visible = screens.some( matchesScreen );
		if ( ! visible ) {
			continue;
		}

		filtered.push( {
			name: ability.name,
			label: ability.label,
			description: ability.description,
			category: ability.category,
			input_schema: ability.input_schema,
			output_schema: ability.output_schema,
			meta: {
				annotations: ( ability.meta && ability.meta.annotations ) || {},
			},
		} );
	}

	return filtered;
}
