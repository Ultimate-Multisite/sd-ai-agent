<?php
/**
 * Integration tests for AutomationLogs.
 *
 * @package AiAgent
 * @subpackage Tests
 */

namespace AiAgent\Tests\Automations;

use AiAgent\Automations\AutomationLogs;
use AiAgent\Automations\Automations;
use WP_UnitTestCase;

/**
 * Test AutomationLogs creation, retrieval, deletion, and pruning.
 */
class AutomationLogsTest extends WP_UnitTestCase {

	/**
	 * Automation IDs created during tests (for cleanup).
	 *
	 * @var int[]
	 */
	private array $automation_ids = [];

	/**
	 * Tear down: delete automations (cascades to logs via Automations::delete).
	 */
	public function tearDown(): void {
		foreach ( $this->automation_ids as $id ) {
			Automations::delete( $id );
		}
		$this->automation_ids = [];
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Create a minimal automation for log association.
	 *
	 * @return int Automation ID.
	 */
	private function create_automation(): int {
		$id = Automations::create(
			[
				'name'    => 'Log Test Automation',
				'prompt'  => 'Test prompt.',
				'enabled' => 0,
			]
		);
		$this->assertIsInt( $id );
		$this->automation_ids[] = $id;
		return $id;
	}

	/**
	 * Create a log entry for the given automation ID.
	 *
	 * @param int   $automation_id Automation ID.
	 * @param array $overrides     Optional field overrides.
	 * @return int Log ID.
	 */
	private function create_log( int $automation_id, array $overrides = [] ): int {
		$data = array_merge(
			[
				'automation_id'     => $automation_id,
				'trigger_type'      => 'scheduled',
				'status'            => 'success',
				'reply'             => 'Test reply.',
				'tool_calls'        => [],
				'prompt_tokens'     => 100,
				'completion_tokens' => 50,
				'duration_ms'       => 1200,
				'error_message'     => '',
			],
			$overrides
		);

		$log_id = AutomationLogs::create( $data );
		$this->assertIsInt( $log_id );
		$this->assertGreaterThan( 0, $log_id );
		return $log_id;
	}

	// -------------------------------------------------------------------------
	// table_name
	// -------------------------------------------------------------------------

	/**
	 * Test table_name returns the correct prefixed table name.
	 */
	public function test_table_name(): void {
		global $wpdb;
		$this->assertSame( $wpdb->prefix . 'ai_agent_automation_logs', AutomationLogs::table_name() );
	}

	// -------------------------------------------------------------------------
	// create
	// -------------------------------------------------------------------------

	/**
	 * Test create returns a positive integer ID.
	 */
	public function test_create_returns_id(): void {
		$automation_id = $this->create_automation();
		$log_id        = $this->create_log( $automation_id );

		$this->assertGreaterThan( 0, $log_id );
	}

	/**
	 * Test create stores all fields correctly.
	 */
	public function test_create_stores_fields(): void {
		$automation_id = $this->create_automation();
		$log_id        = $this->create_log(
			$automation_id,
			[
				'trigger_type'      => 'event',
				'trigger_name'      => 'transition_post_status',
				'status'            => 'error',
				'reply'             => 'Something went wrong.',
				'prompt_tokens'     => 200,
				'completion_tokens' => 80,
				'duration_ms'       => 3000,
				'error_message'     => 'API timeout',
			]
		);

		$log = AutomationLogs::get( $log_id );

		$this->assertNotNull( $log );
		$this->assertSame( $automation_id, $log['automation_id'] );
		$this->assertSame( 'event', $log['trigger_type'] );
		$this->assertSame( 'transition_post_status', $log['trigger_name'] );
		$this->assertSame( 'error', $log['status'] );
		$this->assertSame( 'Something went wrong.', $log['reply'] );
		$this->assertSame( 200, $log['prompt_tokens'] );
		$this->assertSame( 80, $log['completion_tokens'] );
		$this->assertSame( 3000, $log['duration_ms'] );
		$this->assertSame( 'API timeout', $log['error_message'] );
	}

	/**
	 * Test create stores tool_calls as a decoded array.
	 */
	public function test_create_stores_tool_calls_as_array(): void {
		$automation_id = $this->create_automation();
		$tool_calls    = [
			[ 'tool' => 'get_posts', 'args' => [ 'limit' => 5 ] ],
		];

		$log_id = $this->create_log( $automation_id, [ 'tool_calls' => $tool_calls ] );
		$log    = AutomationLogs::get( $log_id );

		$this->assertIsArray( $log['tool_calls'] );
		$this->assertCount( 1, $log['tool_calls'] );
		$this->assertSame( 'get_posts', $log['tool_calls'][0]['tool'] );
	}

	/**
	 * Test create sets created_at timestamp.
	 */
	public function test_create_sets_created_at(): void {
		$automation_id = $this->create_automation();
		$log_id        = $this->create_log( $automation_id );
		$log           = AutomationLogs::get( $log_id );

		$this->assertNotEmpty( $log['created_at'] );
	}

	// -------------------------------------------------------------------------
	// get
	// -------------------------------------------------------------------------

	/**
	 * Test get returns null for a non-existent ID.
	 */
	public function test_get_returns_null_for_missing_id(): void {
		$this->assertNull( AutomationLogs::get( 999999 ) );
	}

	/**
	 * Test get returns an array with expected keys.
	 */
	public function test_get_returns_expected_keys(): void {
		$automation_id = $this->create_automation();
		$log_id        = $this->create_log( $automation_id );
		$log           = AutomationLogs::get( $log_id );

		$expected_keys = [
			'id', 'automation_id', 'trigger_type', 'trigger_name',
			'status', 'reply', 'tool_calls', 'prompt_tokens',
			'completion_tokens', 'duration_ms', 'error_message', 'created_at',
		];

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $log, "Key '{$key}' should be present in log array." );
		}
	}

	/**
	 * Test get returns correct types for typed fields.
	 */
	public function test_get_returns_correct_types(): void {
		$automation_id = $this->create_automation();
		$log_id        = $this->create_log( $automation_id );
		$log           = AutomationLogs::get( $log_id );

		$this->assertIsInt( $log['id'] );
		$this->assertIsInt( $log['automation_id'] );
		$this->assertIsInt( $log['prompt_tokens'] );
		$this->assertIsInt( $log['completion_tokens'] );
		$this->assertIsInt( $log['duration_ms'] );
		$this->assertIsArray( $log['tool_calls'] );
	}

	// -------------------------------------------------------------------------
	// list_for_automation
	// -------------------------------------------------------------------------

	/**
	 * Test list_for_automation returns logs for the given automation.
	 */
	public function test_list_for_automation_returns_logs(): void {
		$automation_id = $this->create_automation();
		$this->create_log( $automation_id );
		$this->create_log( $automation_id );

		$logs = AutomationLogs::list_for_automation( $automation_id );

		$this->assertIsArray( $logs );
		$this->assertCount( 2, $logs );
	}

	/**
	 * Test list_for_automation does not return logs from other automations.
	 */
	public function test_list_for_automation_isolates_by_id(): void {
		$automation_a = $this->create_automation();
		$automation_b = $this->create_automation();

		$this->create_log( $automation_a );
		$this->create_log( $automation_b );

		$logs_a = AutomationLogs::list_for_automation( $automation_a );

		foreach ( $logs_a as $log ) {
			$this->assertSame( $automation_a, $log['automation_id'] );
		}
	}

	/**
	 * Test list_for_automation returns logs ordered by created_at DESC.
	 */
	public function test_list_for_automation_ordered_desc(): void {
		$automation_id = $this->create_automation();
		$first_id      = $this->create_log( $automation_id );
		sleep( 1 );
		$second_id = $this->create_log( $automation_id );

		$logs = AutomationLogs::list_for_automation( $automation_id );
		$ids  = array_column( $logs, 'id' );

		// Most recent (second) should appear first.
		$this->assertSame( $second_id, $ids[0] );
		$this->assertSame( $first_id, $ids[1] );
	}

	/**
	 * Test list_for_automation respects the limit parameter.
	 */
	public function test_list_for_automation_respects_limit(): void {
		$automation_id = $this->create_automation();

		for ( $i = 0; $i < 5; $i++ ) {
			$this->create_log( $automation_id );
		}

		$logs = AutomationLogs::list_for_automation( $automation_id, 3 );
		$this->assertCount( 3, $logs );
	}

	/**
	 * Test list_for_automation respects the offset parameter.
	 */
	public function test_list_for_automation_respects_offset(): void {
		$automation_id = $this->create_automation();

		for ( $i = 0; $i < 4; $i++ ) {
			$this->create_log( $automation_id );
		}

		$all    = AutomationLogs::list_for_automation( $automation_id, 10, 0 );
		$paged  = AutomationLogs::list_for_automation( $automation_id, 10, 2 );

		$this->assertCount( 4, $all );
		$this->assertCount( 2, $paged );
	}

	/**
	 * Test list_for_automation returns empty array for unknown automation.
	 */
	public function test_list_for_automation_empty_for_unknown(): void {
		$logs = AutomationLogs::list_for_automation( 999999 );
		$this->assertIsArray( $logs );
		$this->assertEmpty( $logs );
	}

	// -------------------------------------------------------------------------
	// list_recent
	// -------------------------------------------------------------------------

	/**
	 * Test list_recent returns an array.
	 */
	public function test_list_recent_returns_array(): void {
		$this->assertIsArray( AutomationLogs::list_recent() );
	}

	/**
	 * Test list_recent respects the limit parameter.
	 */
	public function test_list_recent_respects_limit(): void {
		$automation_id = $this->create_automation();

		for ( $i = 0; $i < 5; $i++ ) {
			$this->create_log( $automation_id );
		}

		$logs = AutomationLogs::list_recent( 3 );
		$this->assertLessThanOrEqual( 3, count( $logs ) );
	}

	// -------------------------------------------------------------------------
	// delete_for_automation
	// -------------------------------------------------------------------------

	/**
	 * Test delete_for_automation removes all logs for the automation.
	 */
	public function test_delete_for_automation_removes_logs(): void {
		$automation_id = $this->create_automation();
		$this->create_log( $automation_id );
		$this->create_log( $automation_id );

		$deleted = AutomationLogs::delete_for_automation( $automation_id );

		$this->assertSame( 2, $deleted );
		$this->assertEmpty( AutomationLogs::list_for_automation( $automation_id ) );
	}

	/**
	 * Test delete_for_automation returns zero when no logs exist.
	 */
	public function test_delete_for_automation_returns_zero_when_empty(): void {
		$automation_id = $this->create_automation();
		$deleted       = AutomationLogs::delete_for_automation( $automation_id );

		$this->assertSame( 0, $deleted );
	}

	/**
	 * Test delete_for_automation does not affect logs from other automations.
	 */
	public function test_delete_for_automation_isolates(): void {
		$automation_a = $this->create_automation();
		$automation_b = $this->create_automation();

		$this->create_log( $automation_a );
		$this->create_log( $automation_b );

		AutomationLogs::delete_for_automation( $automation_a );

		$logs_b = AutomationLogs::list_for_automation( $automation_b );
		$this->assertCount( 1, $logs_b );
	}

	// -------------------------------------------------------------------------
	// Cascading delete via Automations::delete
	// -------------------------------------------------------------------------

	/**
	 * Test that deleting an automation also deletes its logs.
	 */
	public function test_automation_delete_cascades_to_logs(): void {
		$automation_id = $this->create_automation();
		$this->create_log( $automation_id );
		$this->create_log( $automation_id );

		Automations::delete( $automation_id );
		// Remove from cleanup list since we already deleted it.
		$this->automation_ids = array_diff( $this->automation_ids, [ $automation_id ] );

		$logs = AutomationLogs::list_for_automation( $automation_id );
		$this->assertEmpty( $logs, 'Logs should be deleted when their automation is deleted.' );
	}
}
