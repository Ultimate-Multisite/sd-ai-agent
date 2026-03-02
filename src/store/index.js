/**
 * WordPress dependencies
 */
import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

const STORE_NAME = 'ai-agent';

/**
 * Known model context windows (tokens).
 */
const MODEL_CONTEXT_WINDOWS = {
	'claude-sonnet-4-20250514': 200000,
	'claude-opus-4-20250115': 200000,
	'gpt-4o': 128000,
	'gpt-4o-mini': 128000,
};

const DEFAULT_STATE = {
	providers: [],
	providersLoaded: false,
	sessions: [],
	sessionsLoaded: false,
	currentSessionId: null,
	currentSessionMessages: [],
	currentSessionToolCalls: [],
	sending: false,
	currentJobId: null,
	selectedProviderId: localStorage.getItem( 'aiAgentProvider' ) || '',
	selectedModelId: localStorage.getItem( 'aiAgentModel' ) || '',
	floatingOpen: false,
	floatingMinimized: false,
	pageContext: '',

	// Settings
	settings: null,
	settingsLoaded: false,

	// Memory
	memories: [],
	memoriesLoaded: false,

	// Skills
	skills: [],
	skillsLoaded: false,

	// Token usage (current session)
	tokenUsage: { prompt: 0, completion: 0 },
};

const actions = {
	setProviders( providers ) {
		return { type: 'SET_PROVIDERS', providers };
	},
	setSessions( sessions ) {
		return { type: 'SET_SESSIONS', sessions };
	},
	setCurrentSession( sessionId, messages, toolCalls ) {
		return {
			type: 'SET_CURRENT_SESSION',
			sessionId,
			messages,
			toolCalls,
		};
	},
	clearCurrentSession() {
		return { type: 'CLEAR_CURRENT_SESSION' };
	},
	setSending( sending ) {
		return { type: 'SET_SENDING', sending };
	},
	setCurrentJobId( jobId ) {
		return { type: 'SET_CURRENT_JOB_ID', jobId };
	},
	setSelectedProvider( providerId ) {
		localStorage.setItem( 'aiAgentProvider', providerId );
		return { type: 'SET_SELECTED_PROVIDER', providerId };
	},
	setSelectedModel( modelId ) {
		localStorage.setItem( 'aiAgentModel', modelId );
		return { type: 'SET_SELECTED_MODEL', modelId };
	},
	setFloatingOpen( open ) {
		return { type: 'SET_FLOATING_OPEN', open };
	},
	setFloatingMinimized( minimized ) {
		return { type: 'SET_FLOATING_MINIMIZED', minimized };
	},
	setPageContext( context ) {
		return { type: 'SET_PAGE_CONTEXT', context };
	},
	appendMessage( message ) {
		return { type: 'APPEND_MESSAGE', message };
	},
	removeLastMessage() {
		return { type: 'REMOVE_LAST_MESSAGE' };
	},
	setSettings( settings ) {
		return { type: 'SET_SETTINGS', settings };
	},
	setMemories( memories ) {
		return { type: 'SET_MEMORIES', memories };
	},
	setSkills( skills ) {
		return { type: 'SET_SKILLS', skills };
	},
	setTokenUsage( tokenUsage ) {
		return { type: 'SET_TOKEN_USAGE', tokenUsage };
	},

	// ─── Thunks ──────────────────────────────────────────────────

	fetchProviders() {
		return async ( { dispatch } ) => {
			try {
				const providers = await apiFetch( {
					path: '/ai-agent/v1/providers',
				} );
				dispatch.setProviders( providers );

				// Auto-select first provider if none saved.
				const saved = localStorage.getItem( 'aiAgentProvider' );
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
					}
				}
			} catch {
				dispatch.setProviders( [] );
			}
		};
	},

	fetchSessions() {
		return async ( { dispatch } ) => {
			try {
				const sessions = await apiFetch( {
					path: '/ai-agent/v1/sessions',
				} );
				dispatch.setSessions( sessions );
			} catch {
				dispatch.setSessions( [] );
			}
		};
	},

	openSession( sessionId ) {
		return async ( { dispatch } ) => {
			try {
				const session = await apiFetch( {
					path: `/ai-agent/v1/sessions/${ sessionId }`,
				} );
				dispatch.setCurrentSession(
					session.id,
					session.messages || [],
					session.tool_calls || []
				);
				if ( session.provider_id ) {
					dispatch.setSelectedProvider( session.provider_id );
				}
				if ( session.model_id ) {
					dispatch.setSelectedModel( session.model_id );
				}
				if ( session.token_usage ) {
					dispatch.setTokenUsage( session.token_usage );
				}
			} catch {
				// ignore
			}
		};
	},

	deleteSession( sessionId ) {
		return async ( { dispatch, select } ) => {
			try {
				await apiFetch( {
					path: `/ai-agent/v1/sessions/${ sessionId }`,
					method: 'DELETE',
				} );
				if ( select.getCurrentSessionId() === sessionId ) {
					dispatch.clearCurrentSession();
				}
				dispatch.fetchSessions();
			} catch {
				// ignore
			}
		};
	},

	sendMessage( message ) {
		return async ( { dispatch, select } ) => {
			dispatch.setSending( true );

			// Append user message to UI immediately.
			dispatch.appendMessage( {
				role: 'user',
				parts: [ { text: message } ],
			} );

			let sessionId = select.getCurrentSessionId();

			// Lazy create session on first message.
			if ( ! sessionId ) {
				try {
					const session = await apiFetch( {
						path: '/ai-agent/v1/sessions',
						method: 'POST',
						data: {
							provider_id: select.getSelectedProviderId(),
							model_id: select.getSelectedModelId(),
						},
					} );
					sessionId = session.id;
					dispatch.setCurrentSession(
						session.id,
						select.getCurrentSessionMessages(),
						[]
					);
				} catch {
					dispatch.appendMessage( {
						role: 'system',
						parts: [
							{
								text: 'Error: Failed to create session.',
							},
						],
					} );
					dispatch.setSending( false );
					return;
				}
			}

			// Build the request body.
			const body = {
				message,
				session_id: sessionId,
				provider_id: select.getSelectedProviderId(),
				model_id: select.getSelectedModelId(),
			};

			// Include page context if available.
			const pageContext = select.getPageContext();
			if ( pageContext ) {
				body.system_instruction =
					'You are a helpful WordPress assistant with access to tools that can interact with this WordPress site. ' +
					'Use the available tools to help the user accomplish their goals. ' +
					'When you need information from the site, call the appropriate tool rather than guessing. ' +
					'After using tools, summarize the results clearly for the user.\n\n' +
					'The user is currently viewing this admin page:\n' +
					pageContext;
			}

			try {
				const result = await apiFetch( {
					path: '/ai-agent/v1/run',
					method: 'POST',
					data: body,
				} );

				if ( ! result.job_id ) {
					throw new Error( 'No job_id returned' );
				}

				dispatch.setCurrentJobId( result.job_id );
				dispatch.pollJob( result.job_id );
			} catch ( err ) {
				dispatch.appendMessage( {
					role: 'system',
					parts: [
						{
							text: `Error: ${ err.message || 'Failed to start job' }`,
						},
					],
				} );
				dispatch.setSending( false );
			}
		};
	},

	pollJob( jobId ) {
		return async ( { dispatch, select } ) => {
			let attempts = 0;
			const maxAttempts = 200;

			const poll = async () => {
				attempts++;
				if ( attempts > maxAttempts ) {
					dispatch.appendMessage( {
						role: 'system',
						parts: [ { text: 'Error: Request timed out.' } ],
					} );
					dispatch.setSending( false );
					dispatch.setCurrentJobId( null );
					return;
				}

				// If job was cancelled (different jobId now), stop.
				if ( select.getCurrentJobId() !== jobId ) {
					return;
				}

				try {
					const result = await apiFetch( {
						path: `/ai-agent/v1/job/${ jobId }`,
					} );

					if ( result.status === 'processing' ) {
						setTimeout( poll, 3000 );
						return;
					}

					if ( result.status === 'error' ) {
						dispatch.appendMessage( {
							role: 'system',
							parts: [
								{
									text: `Error: ${ result.message || 'Unknown error' }`,
								},
							],
						} );
					}

					if ( result.status === 'complete' ) {
						// Add assistant reply.
						if ( result.reply ) {
							dispatch.appendMessage( {
								role: 'model',
								parts: [ { text: result.reply } ],
								toolCalls: result.tool_calls,
							} );
						}

						if ( result.session_id ) {
							dispatch.setCurrentSession(
								result.session_id,
								select.getCurrentSessionMessages(),
								select.getCurrentSessionToolCalls()
							);
						}

						// Update token usage.
						if ( result.token_usage ) {
							const current = select.getTokenUsage();
							dispatch.setTokenUsage( {
								prompt:
									current.prompt +
									( result.token_usage.prompt || 0 ),
								completion:
									current.completion +
									( result.token_usage.completion || 0 ),
							} );
						}

						dispatch.fetchSessions();
					}
				} catch {
					// Network blip — keep polling.
					setTimeout( poll, 3000 );
					return;
				}

				dispatch.setSending( false );
				dispatch.setCurrentJobId( null );
			};

			setTimeout( poll, 2000 );
		};
	},

	// ─── Settings thunks ─────────────────────────────────────────

	fetchSettings() {
		return async ( { dispatch } ) => {
			try {
				const settings = await apiFetch( {
					path: '/ai-agent/v1/settings',
				} );
				dispatch.setSettings( settings );
			} catch {
				dispatch.setSettings( {} );
			}
		};
	},

	saveSettings( data ) {
		return async ( { dispatch } ) => {
			try {
				const settings = await apiFetch( {
					path: '/ai-agent/v1/settings',
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

	// ─── Memory thunks ───────────────────────────────────────────

	fetchMemories() {
		return async ( { dispatch } ) => {
			try {
				const memories = await apiFetch( {
					path: '/ai-agent/v1/memory',
				} );
				dispatch.setMemories( memories );
			} catch {
				dispatch.setMemories( [] );
			}
		};
	},

	createMemory( category, content ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: '/ai-agent/v1/memory',
				method: 'POST',
				data: { category, content },
			} );
			dispatch.fetchMemories();
		};
	},

	updateMemory( id, data ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/ai-agent/v1/memory/${ id }`,
				method: 'PATCH',
				data,
			} );
			dispatch.fetchMemories();
		};
	},

	deleteMemory( id ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/ai-agent/v1/memory/${ id }`,
				method: 'DELETE',
			} );
			dispatch.fetchMemories();
		};
	},

	// ─── Skills thunks ──────────────────────────────────────────

	fetchSkills() {
		return async ( { dispatch } ) => {
			try {
				const skills = await apiFetch( {
					path: '/ai-agent/v1/skills',
				} );
				dispatch.setSkills( skills );
			} catch {
				dispatch.setSkills( [] );
			}
		};
	},

	createSkill( data ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: '/ai-agent/v1/skills',
				method: 'POST',
				data,
			} );
			dispatch.fetchSkills();
		};
	},

	updateSkill( id, data ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/ai-agent/v1/skills/${ id }`,
				method: 'PATCH',
				data,
			} );
			dispatch.fetchSkills();
		};
	},

	deleteSkill( id ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/ai-agent/v1/skills/${ id }`,
				method: 'DELETE',
			} );
			dispatch.fetchSkills();
		};
	},

	resetSkill( id ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/ai-agent/v1/skills/${ id }/reset`,
				method: 'POST',
			} );
			dispatch.fetchSkills();
		};
	},

	// ─── Compact thunk ───────────────────────────────────────────

	compactConversation() {
		return async ( { dispatch, select } ) => {
			const messages = select.getCurrentSessionMessages();
			if ( ! messages.length ) {
				return;
			}

			// Build a summary request.
			const summaryText = messages
				.map( ( m ) => {
					const role = m.role === 'model' ? 'Assistant' : 'User';
					const text = m.parts
						?.filter( ( p ) => p.text )
						.map( ( p ) => p.text )
						.join( '' );
					return text ? `${ role }: ${ text }` : null;
				} )
				.filter( Boolean )
				.join( '\n' );

			// Create a new session.
			try {
				const session = await apiFetch( {
					path: '/ai-agent/v1/sessions',
					method: 'POST',
					data: {
						title: 'Compacted conversation',
						provider_id: select.getSelectedProviderId(),
						model_id: select.getSelectedModelId(),
					},
				} );

				// Send the summary as the first message in the new session.
				dispatch.setCurrentSession( session.id, [], [] );
				dispatch.setTokenUsage( { prompt: 0, completion: 0 } );
				dispatch.sendMessage(
					'Please provide a concise summary of this conversation so we can continue in a fresh context:\n\n' +
						summaryText
				);
				dispatch.fetchSessions();
			} catch {
				// ignore
			}
		};
	},
};

const selectors = {
	getProviders( state ) {
		return state.providers;
	},
	getProvidersLoaded( state ) {
		return state.providersLoaded;
	},
	getSessions( state ) {
		return state.sessions;
	},
	getSessionsLoaded( state ) {
		return state.sessionsLoaded;
	},
	getCurrentSessionId( state ) {
		return state.currentSessionId;
	},
	getCurrentSessionMessages( state ) {
		return state.currentSessionMessages;
	},
	getCurrentSessionToolCalls( state ) {
		return state.currentSessionToolCalls;
	},
	isSending( state ) {
		return state.sending;
	},
	getCurrentJobId( state ) {
		return state.currentJobId;
	},
	getSelectedProviderId( state ) {
		return state.selectedProviderId;
	},
	getSelectedModelId( state ) {
		return state.selectedModelId;
	},
	getSelectedProviderModels( state ) {
		const provider = state.providers.find(
			( p ) => p.id === state.selectedProviderId
		);
		return provider?.models || [];
	},
	isFloatingOpen( state ) {
		return state.floatingOpen;
	},
	isFloatingMinimized( state ) {
		return state.floatingMinimized;
	},
	getPageContext( state ) {
		return state.pageContext;
	},

	// Settings
	getSettings( state ) {
		return state.settings;
	},
	getSettingsLoaded( state ) {
		return state.settingsLoaded;
	},

	// Memory
	getMemories( state ) {
		return state.memories;
	},
	getMemoriesLoaded( state ) {
		return state.memoriesLoaded;
	},

	// Skills
	getSkills( state ) {
		return state.skills;
	},
	getSkillsLoaded( state ) {
		return state.skillsLoaded;
	},

	// Token usage
	getTokenUsage( state ) {
		return state.tokenUsage;
	},
	getContextPercentage( state ) {
		const contextLimit =
			MODEL_CONTEXT_WINDOWS[ state.selectedModelId ] ||
			state.settings?.context_window_default ||
			128000;
		return ( state.tokenUsage.prompt / contextLimit ) * 100;
	},
	isContextWarning( state ) {
		const contextLimit =
			MODEL_CONTEXT_WINDOWS[ state.selectedModelId ] ||
			state.settings?.context_window_default ||
			128000;
		return ( state.tokenUsage.prompt / contextLimit ) * 100 > 80;
	},
};

const reducer = ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_PROVIDERS':
			return {
				...state,
				providers: action.providers,
				providersLoaded: true,
			};
		case 'SET_SESSIONS':
			return {
				...state,
				sessions: action.sessions,
				sessionsLoaded: true,
			};
		case 'SET_CURRENT_SESSION':
			return {
				...state,
				currentSessionId: action.sessionId,
				currentSessionMessages: action.messages,
				currentSessionToolCalls: action.toolCalls,
			};
		case 'CLEAR_CURRENT_SESSION':
			return {
				...state,
				currentSessionId: null,
				currentSessionMessages: [],
				currentSessionToolCalls: [],
				tokenUsage: { prompt: 0, completion: 0 },
			};
		case 'SET_SENDING':
			return { ...state, sending: action.sending };
		case 'SET_CURRENT_JOB_ID':
			return { ...state, currentJobId: action.jobId };
		case 'SET_SELECTED_PROVIDER':
			return { ...state, selectedProviderId: action.providerId };
		case 'SET_SELECTED_MODEL':
			return { ...state, selectedModelId: action.modelId };
		case 'SET_FLOATING_OPEN':
			return { ...state, floatingOpen: action.open };
		case 'SET_FLOATING_MINIMIZED':
			return { ...state, floatingMinimized: action.minimized };
		case 'SET_PAGE_CONTEXT':
			return { ...state, pageContext: action.context };
		case 'APPEND_MESSAGE':
			return {
				...state,
				currentSessionMessages: [
					...state.currentSessionMessages,
					action.message,
				],
			};
		case 'REMOVE_LAST_MESSAGE':
			return {
				...state,
				currentSessionMessages: state.currentSessionMessages.slice(
					0,
					-1
				),
			};
		case 'SET_SETTINGS':
			return {
				...state,
				settings: action.settings,
				settingsLoaded: true,
			};
		case 'SET_MEMORIES':
			return {
				...state,
				memories: action.memories,
				memoriesLoaded: true,
			};
		case 'SET_SKILLS':
			return {
				...state,
				skills: action.skills,
				skillsLoaded: true,
			};
		case 'SET_TOKEN_USAGE':
			return { ...state, tokenUsage: action.tokenUsage };
		default:
			return state;
	}
};

const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
} );

register( store );

export default STORE_NAME;
