/**
 * Client-side abilities entry point.
 *
 * Imports the registry, navigation, and editor modules (in that order) and
 * registers the `gratis-ai-agent-js` category and all initial abilities.
 *
 * Import order matters:
 * 1. `registry` — must be first so `registerCategory()` and
 *    `registerClientAbility()` are available before the ability modules run.
 * 2. `navigation` — registers `gratis-ai-agent-js/navigate-to`.
 * 3. `editor` — registers `gratis-ai-agent-js/insert-block` (self-guarded on
 *    block-editor availability, so safe to import on non-editor screens).
 *
 * Usage in entry points:
 *
 * ```js
 * import { ensureRegistered } from '../abilities';
 * ensureRegistered(); // idempotent — safe to call multiple times
 * ```
 *
 * @module abilities
 */

/**
 * Internal dependencies
 */
import { registerCategory } from './registry';
import { register as registerNavigation } from './navigation';
import { register as registerEditor } from './editor';

/** Track whether registration has already run. */
let registered = false;

/**
 * Register the `gratis-ai-agent-js` category and all client-side abilities.
 *
 * Idempotent — subsequent calls are no-ops. Entry points should call this
 * before mounting their React app so abilities are available as soon as the
 * store is ready.
 */
export function ensureRegistered() {
	if ( registered ) {
		return;
	}
	registerCategory();
	registerNavigation();
	registerEditor();
	registered = true;
}

// Re-export registry helpers so callers can access them without a separate import.
export { registerClientAbility, snapshotDescriptors } from './registry';
