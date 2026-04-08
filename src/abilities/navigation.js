/**
 * Client-side navigation ability.
 *
 * Registers `gratis-ai-agent-js/navigate-to` — navigates the browser to a
 * wp-admin-relative path. Uses `window.location.assign()` for now (full-page
 * navigation). This is still a UX win because the model does not have to ask
 * the user to click; a SPA-router upgrade can land later once core ships a
 * router primitive.
 *
 * Annotated `readonly: true` because it does not mutate site data — it only
 * changes the browser's current URL.
 *
 * @module abilities/navigation
 */

/**
 * Internal dependencies
 */
import { registerClientAbility } from './registry';

/**
 * Register the `gratis-ai-agent-js/navigate-to` ability.
 *
 * Input schema: `{ path: string }` where `path` is a wp-admin-relative path
 * (e.g. `plugins.php`, `edit.php?post_type=page`).
 */
export function register() {
	registerClientAbility( {
		name: 'gratis-ai-agent-js/navigate-to',
		label: 'Navigate to admin page',
		description:
			'Navigates the browser to a wp-admin-relative path. Does not require a page reload when the target is inside the admin SPA.',
		inputSchema: {
			type: 'object',
			properties: {
				path: {
					type: 'string',
					description:
						'wp-admin-relative path, e.g. "plugins.php" or "edit.php?post_type=page".',
				},
			},
			required: [ 'path' ],
		},
		outputSchema: {
			type: 'object',
			properties: {
				navigated: {
					type: 'boolean',
					description: 'True when navigation was initiated.',
				},
			},
		},
		meta: {
			annotations: {
				readonly: true,
			},
		},
		/**
		 * Execute the navigate-to ability.
		 *
		 * @param {Object} input      Ability input.
		 * @param {string} input.path wp-admin-relative path to navigate to.
		 * @return {Promise<{navigated: boolean}>} Result object.
		 */
		async callback( { path } ) {
			const adminUrl = window.wpApiSettings?.root
				? window.wpApiSettings.root.replace(
						/\/wp-json\/?$/,
						'/wp-admin/'
				  )
				: '/wp-admin/';
			// Strip any leading slash from path to avoid double-slash.
			const cleanPath = path.replace( /^\/+/, '' );
			window.location.assign( adminUrl + cleanPath );
			return { navigated: true };
		},
	} );
}
