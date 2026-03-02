<?php
/**
 * Memory system — persistent storage for agent knowledge.
 *
 * @package AiAgent
 */

namespace AiAgent;

class Memory {

	/**
	 * Valid memory categories.
	 */
	const CATEGORIES = [ 'site_info', 'user_preferences', 'technical_notes', 'workflows', 'general' ];

	/**
	 * Get the memories table name.
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ai_agent_memories';
	}

	/**
	 * Get all memories, optionally filtered by category.
	 *
	 * @param string|null $category Optional category filter.
	 * @return array
	 */
	public static function get_all( ?string $category = null ): array {
		global $wpdb;

		$table = self::table_name();

		if ( $category ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE category = %s ORDER BY updated_at DESC",
					$table,
					$category
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i ORDER BY category ASC, updated_at DESC",
				$table
			)
		);
	}

	/**
	 * Get memories by category.
	 *
	 * @param string $category Category name.
	 * @return array
	 */
	public static function get_by_category( string $category ): array {
		return self::get_all( $category );
	}

	/**
	 * Create a new memory.
	 *
	 * @param string $category Memory category.
	 * @param string $content  Memory content.
	 * @return int|false Inserted row ID or false.
	 */
	public static function create( string $category, string $content ) {
		global $wpdb;

		if ( ! in_array( $category, self::CATEGORIES, true ) ) {
			$category = 'general';
		}

		$now    = current_time( 'mysql', true );
		$result = $wpdb->insert(
			self::table_name(),
			[
				'category'   => $category,
				'content'    => $content,
				'created_at' => $now,
				'updated_at' => $now,
			],
			[ '%s', '%s', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update an existing memory.
	 *
	 * @param int   $id   Memory ID.
	 * @param array $data Fields to update (category, content).
	 * @return bool
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;

		$allowed = [ 'category', 'content' ];
		$data    = array_intersect_key( $data, array_flip( $allowed ) );

		if ( isset( $data['category'] ) && ! in_array( $data['category'], self::CATEGORIES, true ) ) {
			$data['category'] = 'general';
		}

		$data['updated_at'] = current_time( 'mysql', true );

		$result = $wpdb->update(
			self::table_name(),
			$data,
			[ 'id' => $id ],
			array_fill( 0, count( $data ), '%s' ),
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete a memory by ID.
	 *
	 * @param int $id Memory ID.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			self::table_name(),
			[ 'id' => $id ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Get memories formatted for inclusion in a system prompt.
	 *
	 * Groups by category. Applies a ~2000-token soft cap by truncating
	 * the oldest entries first when the total exceeds the limit.
	 *
	 * @return string Formatted memory string.
	 */
	public static function get_formatted_for_prompt(): string {
		$all = self::get_all();

		if ( empty( $all ) ) {
			return '';
		}

		// Group by category.
		$grouped = [];
		foreach ( $all as $memory ) {
			$grouped[ $memory->category ][] = $memory->content;
		}

		// Build output per category.
		$sections   = [];
		$word_count = 0;
		$soft_cap   = 1540; // ~2000 tokens at 1.3 words/token

		foreach ( self::CATEGORIES as $category ) {
			if ( empty( $grouped[ $category ] ) ) {
				continue;
			}

			$label   = ucwords( str_replace( '_', ' ', $category ) );
			$entries = $grouped[ $category ];
			$lines   = [];

			foreach ( $entries as $entry ) {
				$entry_words = str_word_count( $entry );

				if ( $word_count + $entry_words > $soft_cap ) {
					break 2; // Stop adding entries across all categories.
				}

				$lines[]     = '- ' . $entry;
				$word_count += $entry_words;
			}

			if ( ! empty( $lines ) ) {
				$sections[] = "### $label\n" . implode( "\n", $lines );
			}
		}

		if ( empty( $sections ) ) {
			return '';
		}

		return "## Your Memory\n\n" . implode( "\n\n", $sections );
	}
}
