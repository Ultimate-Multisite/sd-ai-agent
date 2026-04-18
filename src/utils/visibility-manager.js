/**
 * Visibility manager — singleton for document.visibilityState change notifications.
 *
 * Provides a subscription mechanism so multiple pollers can react to tab
 * visibility changes without each attaching their own event listener.
 * When the page becomes hidden, active pollers slow to 15s intervals.
 * When it becomes visible, pollers are notified immediately so they can
 * fire an extra poll before resuming their normal backoff schedule.
 */

/** @type {Set<function(boolean): void>} */
const listeners = new Set();

let listenerAttached = false;

/**
 * Attach the global visibilitychange listener once.
 */
function ensureListenerAttached() {
	if ( listenerAttached || typeof document === 'undefined' ) {
		return;
	}
	listenerAttached = true;
	document.addEventListener( 'visibilitychange', () => {
		const hidden = document.hidden;
		for ( const cb of listeners ) {
			try {
				cb( hidden );
			} catch {
				// Individual callback errors must not break other listeners.
			}
		}
	} );
}

/**
 * Subscribe to visibility change events.
 *
 * The callback receives a single boolean argument: `true` when the page is
 * hidden, `false` when it becomes visible again.
 *
 * @param {function(boolean): void} callback - Called on each visibility change.
 * @return {function(): void} Unsubscribe function — call to stop notifications.
 */
export function onVisibilityChange( callback ) {
	ensureListenerAttached();
	listeners.add( callback );
	return () => {
		listeners.delete( callback );
	};
}

/**
 * Whether the document is currently hidden (tab in background).
 *
 * @return {boolean} True when hidden.
 */
export function isDocumentHidden() {
	return typeof document !== 'undefined' && document.hidden;
}
