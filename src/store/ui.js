/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * @typedef {import('../types').StoreState} StoreState
 */

export const DEFAULT_STATE = {
	floatingOpen: false,
	floatingMinimized: false,
	pageContext: '',

	// Debug mode
	debugMode: localStorage.getItem( 'gratisAiAgentDebugMode' ) === 'true',

	// Proactive alerts — count of issues surfaced as a badge on the FAB.
	alertCount: 0,

	// Text-to-speech (t084) — persisted to localStorage.
	ttsEnabled: localStorage.getItem( 'gratisAiAgentTtsEnabled' ) === 'true',
	ttsVoiceURI: localStorage.getItem( 'gratisAiAgentTtsVoiceURI' ) || '',
	ttsRate: parseFloat(
		localStorage.getItem( 'gratisAiAgentTtsRate' ) || '1'
	),
	ttsPitch: parseFloat(
		localStorage.getItem( 'gratisAiAgentTtsPitch' ) || '1'
	),
};

export const actions = {
	/**
	 * Open or close the floating panel.
	 *
	 * @param {boolean} open - Whether the panel should be open.
	 * @return {Object} Redux action.
	 */
	setFloatingOpen( open ) {
		return { type: 'SET_FLOATING_OPEN', open };
	},

	/**
	 * Minimize or expand the floating panel.
	 *
	 * @param {boolean} minimized - Whether the panel should be minimized.
	 * @return {Object} Redux action.
	 */
	setFloatingMinimized( minimized ) {
		return { type: 'SET_FLOATING_MINIMIZED', minimized };
	},

	/**
	 * Set structured page context for the AI.
	 *
	 * @param {string|Object} context - Page context object or string.
	 * @return {Object} Redux action.
	 */
	setPageContext( context ) {
		return { type: 'SET_PAGE_CONTEXT', context };
	},

	/**
	 * Enable or disable debug mode and persist the choice to localStorage.
	 *
	 * @param {boolean} enabled - Whether debug mode should be active.
	 * @return {Object} Redux action.
	 */
	setDebugMode( enabled ) {
		localStorage.setItem(
			'gratisAiAgentDebugMode',
			enabled ? 'true' : 'false'
		);
		return { type: 'SET_DEBUG_MODE', enabled };
	},

	setAlertCount( count ) {
		return { type: 'SET_ALERT_COUNT', count };
	},

	// ─── Text-to-speech (t084) ───────────────────────────────────

	/**
	 * Enable or disable text-to-speech and persist the choice to localStorage.
	 *
	 * @param {boolean} enabled - Whether TTS should be active.
	 * @return {Object} Redux action.
	 */
	setTtsEnabled( enabled ) {
		localStorage.setItem(
			'gratisAiAgentTtsEnabled',
			enabled ? 'true' : 'false'
		);
		return { type: 'SET_TTS_ENABLED', enabled };
	},

	/**
	 * Set the TTS voice URI and persist to localStorage.
	 *
	 * @param {string} voiceURI - SpeechSynthesisVoice.voiceURI value.
	 * @return {Object} Redux action.
	 */
	setTtsVoiceURI( voiceURI ) {
		localStorage.setItem( 'gratisAiAgentTtsVoiceURI', voiceURI );
		return { type: 'SET_TTS_VOICE_URI', voiceURI };
	},

	/**
	 * Set the TTS speech rate and persist to localStorage.
	 *
	 * @param {number} rate - Speech rate (0.1–10).
	 * @return {Object} Redux action.
	 */
	setTtsRate( rate ) {
		localStorage.setItem( 'gratisAiAgentTtsRate', String( rate ) );
		return { type: 'SET_TTS_RATE', rate };
	},

	/**
	 * Set the TTS speech pitch and persist to localStorage.
	 *
	 * @param {number} pitch - Speech pitch (0–2).
	 * @return {Object} Redux action.
	 */
	setTtsPitch( pitch ) {
		localStorage.setItem( 'gratisAiAgentTtsPitch', String( pitch ) );
		return { type: 'SET_TTS_PITCH', pitch };
	},

	// ─── Thunks ──────────────────────────────────────────────────

	fetchAlerts() {
		return async ( { dispatch } ) => {
			try {
				const data = await apiFetch( {
					path: '/gratis-ai-agent/v1/alerts',
				} );
				dispatch.setAlertCount( data.count || 0 );
			} catch {
				// Non-fatal — badge simply stays at 0 on error.
				dispatch.setAlertCount( 0 );
			}
		};
	},
};

export const selectors = {
	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether the floating panel is open.
	 */
	isFloatingOpen( state ) {
		return state.floatingOpen;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether the floating panel is minimized.
	 */
	isFloatingMinimized( state ) {
		return state.floatingMinimized;
	},

	/**
	 * @param {StoreState} state
	 * @return {string|Object} Structured page context for the AI.
	 */
	getPageContext( state ) {
		return state.pageContext;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether debug mode is active.
	 */
	isDebugMode( state ) {
		return state.debugMode;
	},

	getAlertCount( state ) {
		return state.alertCount;
	},

	// Text-to-speech (t084)

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether text-to-speech is enabled.
	 */
	isTtsEnabled( state ) {
		return state.ttsEnabled;
	},

	/**
	 * @param {StoreState} state
	 * @return {string} Selected TTS voice URI (empty = browser default).
	 */
	getTtsVoiceURI( state ) {
		return state.ttsVoiceURI;
	},

	/**
	 * @param {StoreState} state
	 * @return {number} TTS speech rate.
	 */
	getTtsRate( state ) {
		return state.ttsRate;
	},

	/**
	 * @param {StoreState} state
	 * @return {number} TTS speech pitch.
	 */
	getTtsPitch( state ) {
		return state.ttsPitch;
	},
};

/**
 * UI slice reducer.
 *
 * @param {StoreState} state  - Current state.
 * @param {Object}     action - Dispatched action.
 * @return {StoreState} Next state.
 */
export function reducer( state, action ) {
	switch ( action.type ) {
		case 'SET_FLOATING_OPEN':
			return { ...state, floatingOpen: action.open };
		case 'SET_FLOATING_MINIMIZED':
			return { ...state, floatingMinimized: action.minimized };
		case 'SET_PAGE_CONTEXT':
			return { ...state, pageContext: action.context };
		case 'SET_DEBUG_MODE':
			return { ...state, debugMode: action.enabled };
		case 'SET_ALERT_COUNT':
			return { ...state, alertCount: action.count };
		case 'SET_TTS_ENABLED':
			return { ...state, ttsEnabled: action.enabled };
		case 'SET_TTS_VOICE_URI':
			return { ...state, ttsVoiceURI: action.voiceURI };
		case 'SET_TTS_RATE':
			return { ...state, ttsRate: action.rate };
		case 'SET_TTS_PITCH':
			return { ...state, ttsPitch: action.pitch };
		default:
			return state;
	}
}
