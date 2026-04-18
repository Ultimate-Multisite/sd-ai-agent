<?php

declare(strict_types=1);
/**
 * Typed DTO for an active-job row returned by wpdb::get_row().
 *
 * Provides typed property declarations for every column in the
 * gratis_ai_agent_active_jobs table, eliminating mixed-type phpstan errors.
 *
 * @package GratisAiAgent\Models\DTO
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Models\DTO;

/**
 * Immutable DTO for the gratis_ai_agent_active_jobs table row.
 */
readonly class ActiveJobRow {

	/**
	 * @param int         $id            Row ID (auto-increment PK).
	 * @param int         $session_id    WordPress session ID.
	 * @param string      $job_id        UUID job identifier.
	 * @param int         $user_id       WordPress user ID.
	 * @param string      $status        Job status (processing|awaiting_confirmation|complete|error).
	 * @param string|null $pending_tools JSON-encoded pending tool array, or null.
	 * @param string|null $tool_calls    JSON-encoded tool-call log, or null.
	 * @param string      $created_at    MySQL datetime string (UTC).
	 * @param string      $updated_at    MySQL datetime string (UTC).
	 */
	public function __construct(
		public int $id,
		public int $session_id,
		public string $job_id,
		public int $user_id,
		public string $status,
		public ?string $pending_tools,
		public ?string $tool_calls,
		public string $created_at,
		public string $updated_at,
	) {}

	/**
	 * Construct an ActiveJobRow from the raw stdClass returned by wpdb::get_row().
	 *
	 * @param object $row Raw row from wpdb::get_row().
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			id:            (int) $row->id,
			session_id:    (int) $row->session_id,
			job_id:        (string) ( $row->job_id ?? '' ),
			user_id:       (int) $row->user_id,
			status:        (string) ( $row->status ?? 'processing' ),
			pending_tools: isset( $row->pending_tools ) && '' !== $row->pending_tools ? (string) $row->pending_tools : null,
			tool_calls:    isset( $row->tool_calls ) && '' !== $row->tool_calls ? (string) $row->tool_calls : null,
			created_at:    (string) ( $row->created_at ?? '' ),
			updated_at:    (string) ( $row->updated_at ?? '' ),
		);
	}
}
