/**
 * WordPress dependencies
 */
import {
	createReduxStore,
	register,
	select as wpSelect,
} from '@wordpress/data';

/**
 * Internal slice imports
 */
import * as sessionsSlice from './sessions';
import * as settingsSlice from './settings';
import * as memorySlice from './memory';
import * as skillsSlice from './skills';
import * as agentsSlice from './agents';
import * as uiSlice from './ui';

/**
 * @typedef {import('../types').StoreState} StoreState
 */

const STORE_NAME = 'gratis-ai-agent';

// Migrate localStorage keys from old "aiAgent" prefix to "gratisAiAgent".
[ 'Provider', 'Model', 'DebugMode' ].forEach( ( key ) => {
	const oldKey = `aiAgent${ key }`;
	const newKey = `gratisAiAgent${ key }`;
	if (
		localStorage.getItem( oldKey ) !== null &&
		localStorage.getItem( newKey ) === null
	) {
		localStorage.setItem( newKey, localStorage.getItem( oldKey ) );
		localStorage.removeItem( oldKey );
	}
} );

// ─── Combined DEFAULT_STATE ───────────────────────────────────────────────────

const DEFAULT_STATE = {
	...sessionsSlice.DEFAULT_STATE,
	...settingsSlice.DEFAULT_STATE,
	...memorySlice.DEFAULT_STATE,
	...skillsSlice.DEFAULT_STATE,
	...agentsSlice.DEFAULT_STATE,
	...uiSlice.DEFAULT_STATE,
};

// ─── Combined actions ─────────────────────────────────────────────────────────

const actions = {
	...sessionsSlice.actions,
	...settingsSlice.actions,
	...memorySlice.actions,
	...skillsSlice.actions,
	...agentsSlice.actions,
	...uiSlice.actions,
};

// ─── Combined selectors ───────────────────────────────────────────────────────

// getContextPercentage and isContextWarning cross slice boundaries
// (sessions.tokenUsage + settings.selectedModelId + settings.settings),
// so they live here in index.js where all slices are visible.
const { MODEL_CONTEXT_WINDOWS } = sessionsSlice;

const selectors = {
	...sessionsSlice.selectors,
	...settingsSlice.selectors,
	...memorySlice.selectors,
	...skillsSlice.selectors,
	...agentsSlice.selectors,
	...uiSlice.selectors,

	/**
	 * Calculate the context window usage as a percentage (0–100+).
	 *
	 * @param {StoreState} state
	 * @return {number} Percentage of context window consumed by prompt tokens.
	 */
	getContextPercentage( state ) {
		const contextLimit =
			MODEL_CONTEXT_WINDOWS[ state.selectedModelId ] ||
			state.settings?.context_window_default ||
			128000;
		return ( state.tokenUsage.prompt / contextLimit ) * 100;
	},

	/**
	 * Whether the context window usage exceeds the 80% warning threshold.
	 *
	 * @param {StoreState} state
	 * @return {boolean} True when context usage is above 80%.
	 */
	isContextWarning( state ) {
		const contextLimit =
			MODEL_CONTEXT_WINDOWS[ state.selectedModelId ] ||
			state.settings?.context_window_default ||
			128000;
		return ( state.tokenUsage.prompt / contextLimit ) * 100 > 80;
	},
};

// ─── Combined reducer ─────────────────────────────────────────────────────────

/**
 * Redux reducer for the Gratis AI Agent store.
 *
 * Each slice reducer handles its own action types and returns the full state.
 * We run them in sequence, passing the output of one as the input to the next.
 *
 * @param {StoreState} state  - Current state (defaults to DEFAULT_STATE).
 * @param {Object}     action - Dispatched action.
 * @return {StoreState} Next state.
 */
function reducer( state = DEFAULT_STATE, action ) {
	let next = state;
	next = sessionsSlice.reducer( next, action );
	next = settingsSlice.reducer( next, action );
	next = memorySlice.reducer( next, action );
	next = skillsSlice.reducer( next, action );
	next = agentsSlice.reducer( next, action );
	next = uiSlice.reducer( next, action );
	return next;
}

// ─── Store registration ───────────────────────────────────────────────────────

const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
} );

// Guard against double-registration: both floating-widget.js and
// screen-meta.js import this module. The first bundle to load registers
// the store; subsequent bundles on the same page skip registration so
// the existing store instance (and its state) is preserved.
if ( ! wpSelect( STORE_NAME ) ) {
	register( store );
}

export default STORE_NAME;
