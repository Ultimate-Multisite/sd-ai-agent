/**
 * Client-side navigation ability.
 *
 * Registers `gratis-ai-agent-js/navigate-to`, which lets the model send the
 * browser to a wp-admin-relative path without requiring the user to click.
 *
 * Runs on every admin screen; no preconditions beyond `window.location`
 * being available. Annotated `readonly: true` because it does not mutate
 * site state — it only changes what the user is looking at.
 */

import { __ } from '@wordpress/i18n';

import { registerClientAbility, CLIENT_NAMESPACE_PREFIX } from './registry';

/**
 * Resolve a user-supplied path against the wp-admin base URL.
 *
 * Accepts:
 *   - absolute wp-admin URLs (returned unchanged after validation)
 *   - wp-admin-relative paths (e.g. "plugins.php", "edit.php?post_type=page")
 *   - paths with leading slash (treated as wp-admin-relative; leading slash stripped)
 *
 * Refuses to navigate off-origin or to non-admin URLs, which would be a
 * surprising behaviour from a "navigate to admin page" ability and a
 * potential phishing vector.
 *
 * @param {string} path The path to resolve.
 * @return {string|null} Absolute URL, or null if the path is invalid.
 */
function resolveAdminPath( path ) {
	if ( typeof path !== 'string' || path.trim() === '' ) {
		return null;
	}

	const base =
		( typeof window !== 'undefined' &&
			window.gratisAiAgentConfig &&
			window.gratisAiAgentConfig.adminUrl ) ||
		( typeof window !== 'undefined' && window.ajaxurl
			? window.ajaxurl.replace( /admin-ajax\.php.*$/, '' )
			: '/wp-admin/' );

	const trimmed = path.replace( /^\/+/, '' );

	try {
		const url = new URL( trimmed, base );

		// Refuse off-origin navigation.
		if ( url.origin !== window.location.origin ) {
			return null;
		}

		// Refuse navigation outside /wp-admin/.
		if ( ! url.pathname.includes( '/wp-admin/' ) ) {
			return null;
		}

		return url.toString();
	} catch ( _err ) {
		return null;
	}
}

/**
 * Register the navigation ability. Safe to call multiple times — the
 * registry helper deduplicates.
 *
 * @return {Promise<void>}
 */
export async function registerNavigationAbilities() {
	await registerClientAbility( {
		name: `${ CLIENT_NAMESPACE_PREFIX }navigate-to`,
		label: __( 'Navigate to admin page', 'gratis-ai-agent' ),
		description: __(
			'Navigate the browser to a wp-admin-relative path (e.g. "plugins.php", "edit.php?post_type=page"). Runs in the browser without a server round-trip.',
			'gratis-ai-agent'
		),
		input_schema: {
			type: 'object',
			properties: {
				path: {
					type: 'string',
					description:
						'A wp-admin-relative path, e.g. "plugins.php", "edit.php?post_type=page", "users.php".',
					minLength: 1,
				},
			},
			required: [ 'path' ],
		},
		output_schema: {
			type: 'object',
			properties: {
				success: { type: 'boolean' },
				url: { type: 'string' },
			},
			required: [ 'success' ],
		},
		meta: {
			annotations: {
				readonly: true,
			},
		},
		screens: [ 'admin' ],
		callback: async ( { path } ) => {
			const target = resolveAdminPath( path );
			if ( ! target ) {
				throw new Error(
					`Invalid or disallowed admin path: "${ path }"`
				);
			}

			// Use assign() so the navigation is recorded in browser history.
			// A future slice can upgrade this to an SPA-internal hash nav
			// when the caller is already inside unified-admin.
			window.location.assign( target );

			return { success: true, url: target };
		},
	} );
}
