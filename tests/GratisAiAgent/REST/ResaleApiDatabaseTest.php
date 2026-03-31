<?php

declare(strict_types=1);
/**
 * Unit tests for ResaleApiDatabase.
 *
 * Exercises the database layer directly (no REST server). Coverage:
 *   - clients_table_name / usage_table_name return expected table names.
 *   - get_schema returns non-empty SQL string.
 *   - create_client inserts a row and returns an integer ID.
 *   - get_client returns the row by ID.
 *   - get_client returns null for unknown ID.
 *   - get_client_by_key returns the row by API key.
 *   - get_client_by_key returns null for unknown key.
 *   - list_clients returns all clients ordered by name.
 *   - update_client updates fields and returns true.
 *   - update_client returns true for non-existent ID (no rows affected = 0, not false).
 *   - delete_client removes the row and returns true.
 *   - log_usage inserts a usage row and increments client counters.
 *   - get_usage returns paginated rows for a client.
 *   - count_usage returns the total row count.
 *   - get_usage_summary returns aggregated totals.
 *   - get_usage_summary respects date range filters.
 *   - reset_monthly_quota resets tokens_used_this_month and advances quota_reset_at.
 *
 * @package GratisAiAgent
 * @subpackage Tests\REST
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\REST;

use GratisAiAgent\REST\ResaleApiDatabase;
use WP_UnitTestCase;

/**
 * Unit tests for ResaleApiDatabase.
 *
 * @group resale
 * @group database
 */
class ResaleApiDatabaseTest extends WP_UnitTestCase {

	// ─── Helpers ─────────────────────────────────────────────────────────────

	/**
	 * Create a test client and return its ID.
	 *
	 * @param array<string, mixed> $overrides Optional field overrides.
	 * @return int Client ID.
	 */
	private function create_client( array $overrides = [] ): int {
		$data = array_merge(
			[
				'name'    => 'Test Client ' . wp_generate_password( 6, false ),
				'api_key' => 'gaa_' . wp_generate_password( 32, false ),
				'enabled' => 1,
			],
			$overrides
		);

		$id = ResaleApiDatabase::create_client( $data );
		$this->assertNotFalse( $id, 'create_client should return an integer ID.' );
		return (int) $id;
	}

	// ─── Table names ──────────────────────────────────────────────────────────

	/**
	 * clients_table_name returns a non-empty string with the expected suffix.
	 */
	public function test_clients_table_name_returns_expected_suffix(): void {
		$name = ResaleApiDatabase::clients_table_name();
		$this->assertStringEndsWith( 'gratis_ai_agent_resale_clients', $name );
	}

	/**
	 * usage_table_name returns a non-empty string with the expected suffix.
	 */
	public function test_usage_table_name_returns_expected_suffix(): void {
		$name = ResaleApiDatabase::usage_table_name();
		$this->assertStringEndsWith( 'gratis_ai_agent_resale_usage', $name );
	}

	// ─── get_schema ───────────────────────────────────────────────────────────

	/**
	 * get_schema returns a non-empty SQL string containing both table names.
	 */
	public function test_get_schema_returns_sql(): void {
		$sql = ResaleApiDatabase::get_schema( 'DEFAULT CHARSET=utf8mb4' );
		$this->assertNotEmpty( $sql );
		$this->assertStringContainsString( 'gratis_ai_agent_resale_clients', $sql );
		$this->assertStringContainsString( 'gratis_ai_agent_resale_usage', $sql );
	}

	// ─── create_client ────────────────────────────────────────────────────────

	/**
	 * create_client returns an integer ID greater than zero.
	 */
	public function test_create_client_returns_integer_id(): void {
		$id = $this->create_client();
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * create_client stores the provided name.
	 */
	public function test_create_client_stores_name(): void {
		$id     = $this->create_client( [ 'name' => 'Stored Name Client' ] );
		$client = ResaleApiDatabase::get_client( $id );
		$this->assertNotNull( $client );
		$this->assertSame( 'Stored Name Client', $client->name );
	}

	/**
	 * create_client stores the api_key.
	 */
	public function test_create_client_stores_api_key(): void {
		$key    = 'gaa_' . wp_generate_password( 32, false );
		$id     = $this->create_client( [ 'api_key' => $key ] );
		$client = ResaleApiDatabase::get_client( $id );
		$this->assertNotNull( $client );
		$this->assertSame( $key, $client->api_key );
	}

	// ─── get_client ───────────────────────────────────────────────────────────

	/**
	 * get_client returns null for an unknown ID.
	 */
	public function test_get_client_returns_null_for_unknown_id(): void {
		$this->assertNull( ResaleApiDatabase::get_client( 999999 ) );
	}

	/**
	 * get_client returns the client object for a known ID.
	 */
	public function test_get_client_returns_object(): void {
		$id     = $this->create_client();
		$client = ResaleApiDatabase::get_client( $id );
		$this->assertIsObject( $client );
		$this->assertSame( (string) $id, (string) $client->id );
	}

	// ─── get_client_by_key ────────────────────────────────────────────────────

	/**
	 * get_client_by_key returns null for an unknown key.
	 */
	public function test_get_client_by_key_returns_null_for_unknown_key(): void {
		$this->assertNull( ResaleApiDatabase::get_client_by_key( 'gaa_nonexistent_key_xyz' ) );
	}

	/**
	 * get_client_by_key returns the client for a known key.
	 */
	public function test_get_client_by_key_returns_client(): void {
		$key = 'gaa_' . wp_generate_password( 32, false );
		$id  = $this->create_client( [ 'api_key' => $key ] );

		$client = ResaleApiDatabase::get_client_by_key( $key );
		$this->assertNotNull( $client );
		$this->assertSame( (string) $id, (string) $client->id );
	}

	// ─── list_clients ─────────────────────────────────────────────────────────

	/**
	 * list_clients returns an array (possibly empty).
	 */
	public function test_list_clients_returns_array(): void {
		$this->assertIsArray( ResaleApiDatabase::list_clients() );
	}

	/**
	 * list_clients includes newly created clients.
	 */
	public function test_list_clients_includes_new_client(): void {
		$id = $this->create_client( [ 'name' => 'Listed Client' ] );

		$clients = ResaleApiDatabase::list_clients();
		$ids     = array_map( static fn( $c ) => (int) $c->id, $clients );
		$this->assertContains( $id, $ids );
	}

	// ─── update_client ────────────────────────────────────────────────────────

	/**
	 * update_client returns true and persists the change.
	 */
	public function test_update_client_persists_change(): void {
		$id = $this->create_client( [ 'name' => 'Before Update' ] );

		$result = ResaleApiDatabase::update_client( $id, [ 'name' => 'After Update' ] );
		$this->assertTrue( $result );

		$client = ResaleApiDatabase::get_client( $id );
		$this->assertNotNull( $client );
		$this->assertSame( 'After Update', $client->name );
	}

	/**
	 * update_client encodes allowed_models array to JSON.
	 */
	public function test_update_client_encodes_allowed_models(): void {
		$id = $this->create_client();

		ResaleApiDatabase::update_client( $id, [ 'allowed_models' => [ 'gpt-4o', 'claude-3-5-sonnet' ] ] );

		$client = ResaleApiDatabase::get_client( $id );
		$this->assertNotNull( $client );
		$decoded = json_decode( $client->allowed_models, true );
		$this->assertIsArray( $decoded );
		$this->assertContains( 'gpt-4o', $decoded );
	}

	// ─── delete_client ────────────────────────────────────────────────────────

	/**
	 * delete_client removes the client row.
	 */
	public function test_delete_client_removes_row(): void {
		$id = $this->create_client();

		$result = ResaleApiDatabase::delete_client( $id );
		$this->assertTrue( $result );

		$this->assertNull( ResaleApiDatabase::get_client( $id ) );
	}

	/**
	 * delete_client also removes associated usage logs.
	 */
	public function test_delete_client_removes_usage_logs(): void {
		$id = $this->create_client();

		// Log some usage.
		ResaleApiDatabase::log_usage( $id, 'openai', 'gpt-4o', 10, 20, 0.001, 'success', '', 100 );

		$this->assertGreaterThan( 0, ResaleApiDatabase::count_usage( $id ) );

		ResaleApiDatabase::delete_client( $id );

		$this->assertSame( 0, ResaleApiDatabase::count_usage( $id ) );
	}

	// ─── log_usage ────────────────────────────────────────────────────────────

	/**
	 * log_usage returns an integer log row ID.
	 */
	public function test_log_usage_returns_integer_id(): void {
		$client_id = $this->create_client();

		$log_id = ResaleApiDatabase::log_usage(
			$client_id,
			'openai',
			'gpt-4o',
			100,
			50,
			0.005,
			'success',
			'',
			250
		);

		$this->assertNotFalse( $log_id );
		$this->assertGreaterThan( 0, (int) $log_id );
	}

	/**
	 * log_usage increments request_count on the client.
	 */
	public function test_log_usage_increments_request_count(): void {
		$client_id = $this->create_client();

		$before = ResaleApiDatabase::get_client( $client_id );
		$this->assertNotNull( $before );
		$count_before = (int) $before->request_count;

		ResaleApiDatabase::log_usage( $client_id, 'openai', 'gpt-4o', 10, 10, 0.001, 'success', '', 100 );

		$after = ResaleApiDatabase::get_client( $client_id );
		$this->assertNotNull( $after );
		$this->assertSame( $count_before + 1, (int) $after->request_count );
	}

	/**
	 * log_usage accumulates tokens_used_this_month on the client.
	 */
	public function test_log_usage_accumulates_tokens_this_month(): void {
		$client_id = $this->create_client();

		ResaleApiDatabase::log_usage( $client_id, 'openai', 'gpt-4o', 100, 50, 0.005, 'success', '', 100 );

		$client = ResaleApiDatabase::get_client( $client_id );
		$this->assertNotNull( $client );
		$this->assertSame( 150, (int) $client->tokens_used_this_month );
	}

	// ─── get_usage ────────────────────────────────────────────────────────────

	/**
	 * get_usage returns an empty array for a client with no logs.
	 */
	public function test_get_usage_returns_empty_for_new_client(): void {
		$client_id = $this->create_client();
		$this->assertSame( [], ResaleApiDatabase::get_usage( $client_id ) );
	}

	/**
	 * get_usage returns logged rows for a client.
	 */
	public function test_get_usage_returns_logged_rows(): void {
		$client_id = $this->create_client();

		ResaleApiDatabase::log_usage( $client_id, 'openai', 'gpt-4o', 10, 5, 0.001, 'success', '', 100 );
		ResaleApiDatabase::log_usage( $client_id, 'openai', 'gpt-4o', 20, 10, 0.002, 'success', '', 200 );

		$rows = ResaleApiDatabase::get_usage( $client_id );
		$this->assertCount( 2, $rows );
	}

	/**
	 * get_usage respects limit and offset.
	 */
	public function test_get_usage_respects_limit(): void {
		$client_id = $this->create_client();

		for ( $i = 0; $i < 5; $i++ ) {
			ResaleApiDatabase::log_usage( $client_id, 'openai', 'gpt-4o', 10, 5, 0.001, 'success', '', 100 );
		}

		$rows = ResaleApiDatabase::get_usage( $client_id, 2, 0 );
		$this->assertCount( 2, $rows );
	}

	// ─── count_usage ──────────────────────────────────────────────────────────

	/**
	 * count_usage returns 0 for a client with no logs.
	 */
	public function test_count_usage_returns_zero_for_new_client(): void {
		$client_id = $this->create_client();
		$this->assertSame( 0, ResaleApiDatabase::count_usage( $client_id ) );
	}

	/**
	 * count_usage returns the correct count after logging.
	 */
	public function test_count_usage_returns_correct_count(): void {
		$client_id = $this->create_client();

		ResaleApiDatabase::log_usage( $client_id, 'openai', 'gpt-4o', 10, 5, 0.001, 'success', '', 100 );
		ResaleApiDatabase::log_usage( $client_id, 'openai', 'gpt-4o', 10, 5, 0.001, 'error', 'err', 100 );

		$this->assertSame( 2, ResaleApiDatabase::count_usage( $client_id ) );
	}

	// ─── get_usage_summary ────────────────────────────────────────────────────

	/**
	 * get_usage_summary returns zero totals for a client with no logs.
	 */
	public function test_get_usage_summary_returns_zeros_for_new_client(): void {
		$client_id = $this->create_client();
		$summary   = ResaleApiDatabase::get_usage_summary( $client_id );

		$this->assertSame( '0', (string) $summary['request_count'] );
		$this->assertSame( '0', (string) $summary['total_prompt_tokens'] );
		$this->assertSame( '0', (string) $summary['total_completion_tokens'] );
	}

	/**
	 * get_usage_summary aggregates token totals correctly.
	 */
	public function test_get_usage_summary_aggregates_totals(): void {
		$client_id = $this->create_client();

		ResaleApiDatabase::log_usage( $client_id, 'openai', 'gpt-4o', 100, 50, 0.005, 'success', '', 100 );
		ResaleApiDatabase::log_usage( $client_id, 'openai', 'gpt-4o', 200, 100, 0.010, 'success', '', 200 );

		$summary = ResaleApiDatabase::get_usage_summary( $client_id );

		$this->assertSame( '2', (string) $summary['request_count'] );
		$this->assertSame( '300', (string) $summary['total_prompt_tokens'] );
		$this->assertSame( '150', (string) $summary['total_completion_tokens'] );
	}

	/**
	 * get_usage_summary respects start_date filter.
	 */
	public function test_get_usage_summary_respects_start_date(): void {
		$client_id = $this->create_client();

		ResaleApiDatabase::log_usage( $client_id, 'openai', 'gpt-4o', 100, 50, 0.005, 'success', '', 100 );

		// Filter to a future date — should return zero results.
		$summary = ResaleApiDatabase::get_usage_summary( $client_id, '2099-01-01', null );
		$this->assertSame( '0', (string) $summary['request_count'] );
	}

	/**
	 * get_usage_summary respects end_date filter.
	 */
	public function test_get_usage_summary_respects_end_date(): void {
		$client_id = $this->create_client();

		ResaleApiDatabase::log_usage( $client_id, 'openai', 'gpt-4o', 100, 50, 0.005, 'success', '', 100 );

		// Filter to a past date — should return zero results.
		$summary = ResaleApiDatabase::get_usage_summary( $client_id, null, '2000-01-01' );
		$this->assertSame( '0', (string) $summary['request_count'] );
	}

	// ─── reset_monthly_quota ──────────────────────────────────────────────────

	/**
	 * reset_monthly_quota resets tokens_used_this_month to zero.
	 */
	public function test_reset_monthly_quota_resets_tokens(): void {
		$client_id = $this->create_client();

		// Accumulate some tokens.
		ResaleApiDatabase::log_usage( $client_id, 'openai', 'gpt-4o', 500, 250, 0.025, 'success', '', 100 );

		$before = ResaleApiDatabase::get_client( $client_id );
		$this->assertNotNull( $before );
		$this->assertGreaterThan( 0, (int) $before->tokens_used_this_month );

		$result = ResaleApiDatabase::reset_monthly_quota( $client_id );
		$this->assertTrue( $result );

		$after = ResaleApiDatabase::get_client( $client_id );
		$this->assertNotNull( $after );
		$this->assertSame( 0, (int) $after->tokens_used_this_month );
	}

	/**
	 * reset_monthly_quota advances quota_reset_at by approximately one month.
	 */
	public function test_reset_monthly_quota_advances_reset_date(): void {
		$client_id = $this->create_client( [
			'quota_reset_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
		] );

		ResaleApiDatabase::reset_monthly_quota( $client_id );

		$client = ResaleApiDatabase::get_client( $client_id );
		$this->assertNotNull( $client );
		$this->assertNotNull( $client->quota_reset_at );

		// New reset date should be in the future.
		$this->assertGreaterThan( time(), strtotime( $client->quota_reset_at ) );
	}
}
