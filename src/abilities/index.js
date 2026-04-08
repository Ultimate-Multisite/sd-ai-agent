/**
 * Client-side abilities entry.
 *
 * Imported from every plugin entry point (`unified-admin`, `floating-widget`,
 * `screen-meta`, `admin-page`) to register the plugin's
 * `gratis-ai-agent-js/*` abilities into the shared `core/abilities` store
 * before the chat UI mounts.
 *
 * `ensureRegistered()` is idempotent: each underlying helper deduplicates
 * by ability name, so importing this module from multiple entry points on
 * the same page (e.g. unified admin + floating widget simultaneously) is
 * safe.
 *
 * This module does **not** touch the agent loop, REST endpoints, or the
 * sessions store. It is pure registration. The agent-loop round-trip that
 * actually makes the model *call* these abilities lands in t164.
 */

import { ensureCategoryRegistered, snapshotDescriptors } from './registry';
import { registerNavigationAbilities } from './navigation';
import { registerEditorAbilities } from './editor';

// Re-export for callers that need to snapshot the registry at send-message
// time (t164's sessionsSlice round-trip).
export { snapshotDescriptors };

/**
 * Internal: single in-flight promise so concurrent callers wait on the
 * same registration cycle instead of racing.
 *
 * @type {Promise<void>|null}
 */
let registrationPromise = null;

/**
 * Register all client-side abilities into the shared `core/abilities` store.
 *
 * Resolves once registration is complete. Subsequent calls return the same
 * promise and do no additional work.
 *
 * Failures inside individual ability registrations are logged but not
 * propagated — a missing client ability must never break the chat UI.
 *
 * @return {Promise<void>}
 */
export function ensureRegistered() {
	if ( registrationPromise ) {
		return registrationPromise;
	}

	registrationPromise = ( async () => {
		try {
			await ensureCategoryRegistered();
			// Navigation is safe on every admin screen.
			await registerNavigationAbilities();
			// Editor self-guards — no-op if the block editor is not mounted.
			await registerEditorAbilities();
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.warn(
				'[gratis-ai-agent] Client ability registration failed:',
				err
			);
		}
	} )();

	return registrationPromise;
}

// Kick off registration as soon as this module is imported. Entry points can
// also `await ensureRegistered()` explicitly before mounting if they want to
// guarantee registration has completed — but for the foundation slice, the
// abilities do not need to be ready before the chat mounts (t164 will snapshot
// them at send-message time, long after registration has resolved).
ensureRegistered();
