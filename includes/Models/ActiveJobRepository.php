<?php

declare(strict_types=1);
/**
 * Repository for active background jobs.
 *
 * Persists job lifecycle alongside transients so jobs can be reconnected
 * after a page navigation or browser refresh.
 *
 * @package GratisAiAgent\Models
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Models;

use GratisAiAgent\Models\DTO\ActiveJobRow;

class ActiveJobRepository {

	/**
	 * Valid active job statuses — rows in these states are considered "active".
	 */
	const ACTIVE_STATUSES = [ 'processing', 'awaiting_confirmation' ];

	/**
	 * Get the active_jobs table name.
	 */
	public static function table_name(): string {
		global $wpdb;
		/** @var \wpdb $wpdb */
		return $wpdb->prefix . 'gratis_ai_agent_active_jobs';
	}

	/**
	 * Create a new active job record.
	 *
	 * @param string $job_id     UUID job identifier.
	 * @param int    $session_id WordPress session ID.
	 * @param int    $user_id    WordPress user ID.
	 * @param string $status     Initial status (default: processing).
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function create( string $job_id, int $session_id, int $user_id, string $status = 'processing' ) {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query; caching not applicable.
		$result = $wpdb->insert(
			self::table_name(),
			[
				'job_id'     => $job_id,
				'session_id' => $session_id,
				'user_id'    => $user_id,
				'status'     => $status,
				'created_at' => $now,
				'updated_at' => $now,
			],
			[ '%s', '%d', '%d', '%s', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get an active job row by its job ID.
	 *
	 * @param string $job_id UUID job identifier.
	 * @return ActiveJobRow|null
	 */
	public static function get_by_job_id( string $job_id ): ?ActiveJobRow {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE job_id = %s LIMIT 1',
				$table,
				$job_id
			)
		);

		if ( null === $row || ! is_object( $row ) ) {
			return null;
		}

		return ActiveJobRow::from_row( $row );
	}

	/**
	 * Get the most recent active job for a given session.
	 *
	 * Only returns rows with an active status (processing or awaiting_confirmation).
	 *
	 * @param int $session_id WordPress session ID.
	 * @return ActiveJobRow|null
	 */
	public static function get_by_session_id( int $session_id ): ?ActiveJobRow {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table     = self::table_name();
		$statuses  = array_map( fn( string $s ) => (string) $wpdb->prepare( '%s', $s ), self::ACTIVE_STATUSES );
		$status_in = implode( ', ', $statuses );

		// Build SQL separately so the phpcs:ignore can target the interpolated string line.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $status_in is built from $wpdb->prepare('%s') calls; values are already escaped.
		$sql = "SELECT * FROM %i WHERE session_id = %d AND status IN ({$status_in}) ORDER BY created_at DESC LIMIT 1";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom table query; $sql built from prepare() calls above; values escaped.
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $table, $session_id ) );

		if ( null === $row || ! is_object( $row ) ) {
			return null;
		}

		return ActiveJobRow::from_row( $row );
	}

	/**
	 * Get all active jobs for a given user.
	 *
	 * Returns jobs with status processing or awaiting_confirmation.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return list<ActiveJobRow>
	 */
	public static function get_active_for_user( int $user_id ): array {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$table     = self::table_name();
		$statuses  = array_map( fn( string $s ) => (string) $wpdb->prepare( '%s', $s ), self::ACTIVE_STATUSES );
		$status_in = implode( ', ', $statuses );

		// Build SQL separately so the phpcs:ignore can target the interpolated string line.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $status_in is built from $wpdb->prepare('%s') calls; values are already escaped.
		$sql = "SELECT * FROM %i WHERE user_id = %d AND status IN ({$status_in}) ORDER BY created_at DESC";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom table query; $sql built from prepare() calls above; values escaped.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $table, $user_id ) );

		if ( null === $rows ) {
			return [];
		}

		return array_values( array_map( [ ActiveJobRow::class, 'from_row' ], $rows ) );
	}

	/**
	 * Update the status (and optionally tool data) of a job.
	 *
	 * @param string               $job_id Job UUID.
	 * @param string               $status New status value.
	 * @param array<string, mixed> $data   Optional additional fields to update (pending_tools, tool_calls).
	 * @return bool True on success, false on failure.
	 */
	public static function update_status( string $job_id, string $status, array $data = [] ): bool {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$allowed = [ 'pending_tools', 'tool_calls' ];
		$update  = array_intersect_key( $data, array_flip( $allowed ) );

		$update['status']     = $status;
		$update['updated_at'] = current_time( 'mysql', true );

		$formats = array_fill( 0, count( $update ), '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$result = $wpdb->update(
			self::table_name(),
			$update,
			[ 'job_id' => $job_id ],
			$formats,
			[ '%s' ]
		);

		return $result !== false;
	}

	/**
	 * Delete an active job record by job ID.
	 *
	 * @param string $job_id Job UUID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( string $job_id ): bool {
		global $wpdb;
		/** @var \wpdb $wpdb */

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query; caching not applicable.
		$result = $wpdb->delete(
			self::table_name(),
			[ 'job_id' => $job_id ],
			[ '%s' ]
		);

		return $result !== false;
	}
}
