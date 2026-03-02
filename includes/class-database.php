<?php
/**
 * Database table management for AI Agent sessions.
 *
 * @package AiAgent
 */

namespace AiAgent;

class Database {

	const DB_VERSION_OPTION = 'ai_agent_db_version';
	const DB_VERSION        = '3.0.0';

	/**
	 * Get the sessions table name.
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ai_agent_sessions';
	}

	/**
	 * Get the memories table name.
	 */
	public static function memories_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ai_agent_memories';
	}

	/**
	 * Get the skills table name.
	 */
	public static function skills_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ai_agent_skills';
	}

	/**
	 * Install or upgrade the database table.
	 */
	public static function install(): void {
		global $wpdb;

		$installed_version = get_option( self::DB_VERSION_OPTION );

		if ( $installed_version === self::DB_VERSION ) {
			return;
		}

		$table          = self::table_name();
		$memories_table = self::memories_table_name();
		$skills_table   = self::skills_table_name();
		$charset        = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			title varchar(255) NOT NULL DEFAULT '',
			provider_id varchar(100) NOT NULL DEFAULT '',
			model_id varchar(100) NOT NULL DEFAULT '',
			messages longtext NOT NULL,
			tool_calls longtext NOT NULL,
			prompt_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			completion_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY updated_at (updated_at)
		) {$charset};

		CREATE TABLE {$memories_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			category varchar(50) NOT NULL DEFAULT 'general',
			content text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY category (category)
		) {$charset};

		CREATE TABLE {$skills_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(100) NOT NULL,
			name varchar(255) NOT NULL,
			description text NOT NULL,
			content longtext NOT NULL,
			is_builtin tinyint(1) NOT NULL DEFAULT 0,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY enabled (enabled)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Seed built-in skills.
		Skill::seed_builtins();

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Create a new session.
	 *
	 * @param array $data Session data: user_id, title, provider_id, model_id.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function create_session( array $data ) {
		global $wpdb;

		$now    = current_time( 'mysql', true );
		$result = $wpdb->insert(
			self::table_name(),
			[
				'user_id'     => $data['user_id'],
				'title'       => $data['title'] ?? '',
				'provider_id' => $data['provider_id'] ?? '',
				'model_id'    => $data['model_id'] ?? '',
				'messages'    => '[]',
				'tool_calls'  => '[]',
				'created_at'  => $now,
				'updated_at'  => $now,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get a single session by ID.
	 *
	 * @param int $session_id Session ID.
	 * @return object|null Session row or null.
	 */
	public static function get_session( int $session_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				self::table_name(),
				$session_id
			)
		);
	}

	/**
	 * List sessions for a user (lightweight — no messages/tool_calls).
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array Array of session summary objects.
	 */
	public static function list_sessions( int $user_id ): array {
		global $wpdb;

		$table = self::table_name();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, user_id, title, provider_id, model_id, created_at, updated_at,
					JSON_LENGTH(messages) AS message_count
				FROM %i
				WHERE user_id = %d
				ORDER BY updated_at DESC",
				$table,
				$user_id
			)
		);
	}

	/**
	 * Update session fields.
	 *
	 * @param int   $session_id Session ID.
	 * @param array $data       Fields to update.
	 * @return bool Whether the update succeeded.
	 */
	public static function update_session( int $session_id, array $data ): bool {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql', true );

		$formats = [];
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, [ 'user_id', 'id' ], true ) ) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}

		$result = $wpdb->update(
			self::table_name(),
			$data,
			[ 'id' => $session_id ],
			$formats,
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete a session.
	 *
	 * @param int $session_id Session ID.
	 * @return bool Whether the delete succeeded.
	 */
	public static function delete_session( int $session_id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			self::table_name(),
			[ 'id' => $session_id ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Update token usage for a session (accumulates).
	 *
	 * @param int $session_id       Session ID.
	 * @param int $prompt_tokens    Prompt tokens to add.
	 * @param int $completion_tokens Completion tokens to add.
	 * @return bool
	 */
	public static function update_session_tokens( int $session_id, int $prompt_tokens, int $completion_tokens ): bool {
		global $wpdb;

		$table = self::table_name();

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET prompt_tokens = prompt_tokens + %d, completion_tokens = completion_tokens + %d, updated_at = %s WHERE id = %d",
				$table,
				$prompt_tokens,
				$completion_tokens,
				current_time( 'mysql', true ),
				$session_id
			)
		);

		return $result !== false;
	}

	/**
	 * Append messages and tool calls to a session.
	 *
	 * Loads current data, merges new entries, and saves back.
	 *
	 * @param int   $session_id Session ID.
	 * @param array $messages   New message arrays to append.
	 * @param array $tool_calls New tool call log entries to append.
	 * @return bool Whether the update succeeded.
	 */
	public static function append_to_session( int $session_id, array $messages, array $tool_calls = [] ): bool {
		$session = self::get_session( $session_id );

		if ( ! $session ) {
			return false;
		}

		$existing_messages   = json_decode( $session->messages, true ) ?: [];
		$existing_tool_calls = json_decode( $session->tool_calls, true ) ?: [];

		$merged_messages   = array_merge( $existing_messages, $messages );
		$merged_tool_calls = array_merge( $existing_tool_calls, $tool_calls );

		return self::update_session( $session_id, [
			'messages'   => wp_json_encode( $merged_messages ),
			'tool_calls' => wp_json_encode( $merged_tool_calls ),
		] );
	}
}
