/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * @typedef {import('../types').StoreState} StoreState
 * @typedef {import('../types').Skill} Skill
 */

export const DEFAULT_STATE = {
	skills: [],
	skillsLoaded: false,
};

export const actions = {
	/**
	 * Replace the skills list.
	 *
	 * @param {Skill[]} skills - Skill entries.
	 * @return {Object} Redux action.
	 */
	setSkills( skills ) {
		return { type: 'SET_SKILLS', skills };
	},

	// ─── Thunks ──────────────────────────────────────────────────

	/**
	 * Fetch all skill entries from the REST API.
	 *
	 * @return {Function} Redux thunk.
	 */
	fetchSkills() {
		return async ( { dispatch } ) => {
			try {
				const skills = await apiFetch( {
					path: '/gratis-ai-agent/v1/skills',
				} );
				dispatch.setSkills( skills );
			} catch {
				dispatch.setSkills( [] );
			}
		};
	},

	/**
	 * Create a new skill.
	 *
	 * @param {Partial<Skill>} data - Skill fields.
	 * @return {Function} Redux thunk.
	 */
	createSkill( data ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: '/gratis-ai-agent/v1/skills',
				method: 'POST',
				data,
			} );
			dispatch.fetchSkills();
		};
	},

	/**
	 * Update an existing skill.
	 *
	 * @param {number}         id   - Skill identifier.
	 * @param {Partial<Skill>} data - Fields to update.
	 * @return {Function} Redux thunk.
	 */
	updateSkill( id, data ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/skills/${ id }`,
				method: 'PATCH',
				data,
			} );
			dispatch.fetchSkills();
		};
	},

	/**
	 * Delete a skill.
	 *
	 * @param {number} id - Skill identifier.
	 * @return {Function} Redux thunk.
	 */
	deleteSkill( id ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/skills/${ id }`,
				method: 'DELETE',
			} );
			dispatch.fetchSkills();
		};
	},

	/**
	 * Reset a skill to its built-in defaults.
	 *
	 * @param {number} id - Skill identifier.
	 * @return {Function} Redux thunk.
	 */
	resetSkill( id ) {
		return async ( { dispatch } ) => {
			await apiFetch( {
				path: `/gratis-ai-agent/v1/skills/${ id }/reset`,
				method: 'POST',
			} );
			dispatch.fetchSkills();
		};
	},
};

export const selectors = {
	/**
	 * @param {StoreState} state
	 * @return {Skill[]} Skill entries.
	 */
	getSkills( state ) {
		return state.skills;
	},

	/**
	 * @param {StoreState} state
	 * @return {boolean} Whether skills have been fetched.
	 */
	getSkillsLoaded( state ) {
		return state.skillsLoaded;
	},
};

/**
 * Skills slice reducer.
 *
 * @param {StoreState} state  - Current state.
 * @param {Object}     action - Dispatched action.
 * @return {StoreState} Next state.
 */
export function reducer( state, action ) {
	switch ( action.type ) {
		case 'SET_SKILLS':
			return {
				...state,
				skills: action.skills,
				skillsLoaded: true,
			};
		default:
			return state;
	}
}
