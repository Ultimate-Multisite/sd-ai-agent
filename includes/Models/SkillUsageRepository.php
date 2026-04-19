<?php

declare(strict_types=1);
/**
 * Repository for skill usage tracking persistence.
 *
 * Records which skills were loaded, how they were triggered, and
 * provides aggregated effectiveness statistics for the skill manager UI.
 *
 * @package GratisAiAgent\Models
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Models;

use GratisAiAgent\Core\Database;
use GratisAiAgent\Models\DTO\SkillUsageRow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles persistence for skill-load events and outcome telemetry.
 */
class SkillUsageRepository {

	/**
	 * Valid trigger types.
	 */
	const TRIGGER_AUTO      = 'auto';
	const TRIGGER_MANUAL    = 'manual';
	const TRIGGER_TOOL_CALL = 'tool_call';

	/**
	 * Valid outcome values.
	 */
	const OUTCOME_HELPFUL  = 'helpful';
	const OUTCOME_NEUTRAL  = 'neutral';
	const OUTCOME_NEGATIVE = 'negative';
	const OUTCOME_UNKNOWN  = 'unknown';

	/**
	 * Record a skill load event.
	 *
	 * @param int    $skill_id        FK to gratis_ai_agent_skills.id.
	 * @param int    $session_id      FK to gratis_ai_agent_sessions.id (0 = no session).
	 * @param string $trigger_type    How the skill was loaded: 'auto', 'manual', or 'tool_call'.
	 * @param int    $injected_tokens Estimated token cost of injecting the skill content.
	 * @param string $model_id        Model ID that received the skill.
	 * @param string $outcome         Initial outcome: default 'unknown', updated later.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function create(
		int $skill_id,
		int $session_id,
		string $trigger_type,
		int $injected_tokens,
		string $model_id,
		string $outcome = self::OUTCOME_UNKNOWN
	): int|false {
		global $wpdb;
		/** @var \wpdb $wpdb */

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query; caching not applicable.
		$result = $wpdb->insert(
			Database::skill_usage_table_name(),
			[
				'skill_id'        => $skill_id,
				'session_id'      => $session_id,
				'trigger_type'    => $trigger_type,
				'injected_tokens' => $injected_tokens,
				'outcome'         => $outcome,
				'model_id'        => $model_id,
				'created_at'      => current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%s', '%d', '%s', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update the outcome for a skill usage row.
	 *
	 * Called by the outcome heuristic after the agent loop completes.
	 *
	 * @param int    $id      Row ID to update.
	 * @param string $outcome New outcome: 'helpful', 'neutral', 'negative', or 'unknown'.
	 * @return bool Whether the update succeeded.
	 */
	public static function update_outcome( int $id, string $outcome ): bool {
		global $wpdb;
		/** @var \wpdb $wpdb */

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$result = $wpdb->update(
			Database::skill_usage_table_name(),
			[ 'outcome' => $outcome ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Update the outcome for all 'unknown' skill usage rows in a session.
	 *
	 * Called by AgentLoop after the loop completes to apply the outcome
	 * heuristic across all skills injected during that session.
	 *
	 * @param int    $session_id Session ID (0 = no-op).
	 * @param string $outcome    Outcome to apply: 'helpful', 'neutral', 'negative'.
	 * @return int Number of rows updated.
	 */
	public static function update_session_outcomes( int $session_id, string $outcome ): int {
		if ( $session_id <= 0 ) {
			return 0;
		}

		global $wpdb;
		/** @var \wpdb $wpdb */

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$rows_affected = $wpdb->update(
			Database::skill_usage_table_name(),
			[ 'outcome' => $outcome ],
			[
				'session_id' => $session_id,
				'outcome'    => self::OUTCOME_UNKNOWN,
			],
			[ '%s' ],
			[ '%d', '%s' ]
		);

		return is_int( $rows_affected ) ? $rows_affected : 0;
	}

	/**
	 * Get all usage records for a specific skill.
	 *
	 * @param int $skill_id Skill ID.
	 * @param int $limit    Max rows to return (default 100).
	 * @return list<SkillUsageRow>
	 */
	public static function get_by_skill( int $skill_id, int $limit = 100 ): array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Database::skill_usage_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE skill_id = %d ORDER BY created_at DESC LIMIT %d',
				$table,
				$skill_id,
				$limit
			)
		);

		if ( empty( $rows ) ) {
			return [];
		}

		return array_map( [ SkillUsageRow::class, 'from_row' ], $rows );
	}

	/**
	 * Get aggregated usage statistics per skill.
	 *
	 * Returns load counts, outcome breakdown, and estimated token cost
	 * grouped by skill ID — suitable for the skill manager admin UI.
	 *
	 * @return list<object> Each row has: skill_id, load_count, helpful_count,
	 *                       neutral_count, negative_count, unknown_count,
	 *                       total_tokens, last_used_at.
	 */
	public static function get_stats(): array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Database::skill_usage_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; table name from internal method.
		$rows = $wpdb->get_results(
			"SELECT
				skill_id,
				COUNT(*) AS load_count,
				SUM(outcome = 'helpful')  AS helpful_count,
				SUM(outcome = 'neutral')  AS neutral_count,
				SUM(outcome = 'negative') AS negative_count,
				SUM(outcome = 'unknown')  AS unknown_count,
				COALESCE(SUM(injected_tokens), 0) AS total_tokens,
				MAX(created_at) AS last_used_at
			FROM {$table}
			GROUP BY skill_id
			ORDER BY load_count DESC"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Get aggregated statistics for a single skill.
	 *
	 * @param int $skill_id Skill ID.
	 * @return object|null Stats object (load_count, helpful_count, neutral_count,
	 *                       negative_count, unknown_count, total_tokens, last_used_at)
	 *                       or null if no records exist.
	 */
	public static function get_stats_for_skill( int $skill_id ): ?object {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = Database::skill_usage_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					skill_id,
					COUNT(*) AS load_count,
					SUM(outcome = 'helpful')  AS helpful_count,
					SUM(outcome = 'neutral')  AS neutral_count,
					SUM(outcome = 'negative') AS negative_count,
					SUM(outcome = 'unknown')  AS unknown_count,
					COALESCE(SUM(injected_tokens), 0) AS total_tokens,
					MAX(created_at) AS last_used_at
				FROM %i
				WHERE skill_id = %d
				GROUP BY skill_id",
				$table,
				$skill_id
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}
}
