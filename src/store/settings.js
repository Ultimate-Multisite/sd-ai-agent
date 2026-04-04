/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * @typedef {import('../types').StoreState} StoreState
 * @typedef {import('../types').Provider} Provider
 * @typedef {import('../types').Settings} Settings
 */

export const DEFAULT_STATE = {
	providers: [],
	providersLoaded: false,
	selectedProviderId:
		localStorage.getItem( 'gratisAiAgentProvider' ) || '',
	selectedModelId: localStorage.getItem( 'gratisAiAgentModel' ) || '',

	// Settings
	settings: null,
	settingsLoaded: false,
};

export const actions = {
	/**
	 * Replace the providers list.
	 *
	 * @param {Provider[]} providers - Available AI providers.
	 * @return {Object} Redux action.
	 */
	setProviders( providers ) {
		return { type: 'SET_PROVIDERS', providers };
	},

	/**
	 * Select an AI provider and persist the choice to localStorage.
	 *
	 * @param {string} providerId - Provider identifier.
	 * @return {Object} Redux action.
	 */
	setSelectedProvider( providerId ) {
		localStorage.setItem( 'gratisAiAgentProvider', providerId );
		return { type: 'SET_SELECTED_PROVIDER', providerId };
	},

	/**
	 * Select a model and persist the choice to localStorage.
	 *
	 * @param {string} modelId - Model identifier.
	 * @return {Object} Redux action.
	 */
	setSelectedModel( modelId ) {
		localStorage.setItem( 'gratisAiAgentModel', modelId );
		return { type: 'SET_SELECTED_MODEL', modelId };
	},

	/**
	 * Replace the plugin settings.
	 *
	 * @param {Settings} settings - Plugin settings object.
	 * @return {Object} Redux action.
	 */
	setSettings( settings ) {
		return { type: 'SET_SETTINGS', settings };
	},

	// ─── Thunks ──────────────────────────────────────────────────

	/**
	 * Fetch available AI providers from the REST API and populate the store.
	 * Auto-selects the first provider/model when none is saved or the saved
	 * provider is no longer available.
	 *
	 * @return {Function} Redux thunk.
	 */
	fetchProviders() {
		return async ( { dispatch } ) => {
			try {
				const providers = await apiFetch( {
					path: '/gratis-ai-agent/v1/providers',
				} );
				dispatch.setProviders( providers );

				// Auto-select first provider if none saved or saved one is unavailable.
				const saved = localStorage.getItem( 'gratisAiAgentProvider' );
				if (
					( ! saved ||
						! providers.find( ( p ) => p.id === saved ) ) &&
					providers.length
				) {
					dispatch.setSelectedProvider( providers[ 0 ].id );
					if ( providers[ 0 ].models?.length ) {
						dispatch.setSelectedModel(
							providers[ 0 ].models[ 0 ].id
						);
					} else {
						dispatch.setSelectedModel( '' );
					}
				}
			} catch {
				dispatch.setProviders( [] );
			}
		};
	},

	/**
	 * Fetch plugin settings from the REST API.
	 *
	 * @return {Function} Redux thunk.
	 */
	fetchSettings() {
		return async ( { dispatch } ) => {
			try {
				const settings = await apiFetch( {
					path: '/gratis-ai-agent/v1/settings',
				} );
				dispatch.setSettings( settings );
			} catch {
				dispatch.setSettings( {} );
			}
		};
	},

	/**
	 * Save plugin settings via the REST API.
	 *
	 * @param {Partial<Settings>} data - Settings fields to update.
	 * @return {Function} Redux thunk that resolves with the saved settings.
	 */
	saveSettings( data ) {
		return async ( { dispatch } ) => {
			try {
				const settings = await apiFetch( {
					path: '/gratis-ai-agent/v1/settings',
					method: 'POST',
					data,
				} );
				dispatch.setSettings( settings );
				return settings;
			} catch ( err ) {
				throw err;
			}
		};
	},
};

export const selectors = {
	/**
	 * @param {StoreState} state
	 * @return {Provider[]} Available AI providers.
	 */
	getProviders( state ) {
		return state.providers;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether providers have been fetched.
	 */
	getProvidersLoaded( state ) {
		return state.providersLoaded;
	},

	/**
	 * @param {StoreState} state
	 * @return {string} Currently selected provider ID.
	 */
	getSelectedProviderId( state ) {
		return state.selectedProviderId;
	},

	/**
	 * @param {StoreState} state
	 * @return {string} Currently selected model ID.
	 */
	getSelectedModelId( state ) {
		return state.selectedModelId;
	},

	/**
	 * @param {StoreState} state
	 * @return {import('../types').ProviderModel[]} Models for the selected provider.
	 */
	getSelectedProviderModels( state ) {
		const provider = state.providers.find(
			( p ) => p.id === state.selectedProviderId
		);
		return provider?.models || [];
	},

	/**
	 * @param {StoreState} state
	 * @return {Settings|null} Plugin settings, or null if not yet loaded.
	 */
	getSettings( state ) {
		return state.settings;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether settings have been fetched.
	 */
	getSettingsLoaded( state ) {
		return state.settingsLoaded;
	},

	// YOLO mode (skip all confirmations)
	isYoloMode( state ) {
		return state.settings?.yolo_mode ?? false;
	},
};

/**
 * Settings slice reducer.
 *
 * @param {StoreState} state  - Current state.
 * @param {Object}     action - Dispatched action.
 * @return {StoreState} Next state.
 */
export function reducer( state, action ) {
	switch ( action.type ) {
		case 'SET_PROVIDERS':
			return {
				...state,
				providers: action.providers,
				providersLoaded: true,
			};
		case 'SET_SELECTED_PROVIDER':
			return { ...state, selectedProviderId: action.providerId };
		case 'SET_SELECTED_MODEL':
			return { ...state, selectedModelId: action.modelId };
		case 'SET_SETTINGS':
			return {
				...state,
				settings: action.settings,
				settingsLoaded: true,
			};
		default:
			return state;
	}
}
