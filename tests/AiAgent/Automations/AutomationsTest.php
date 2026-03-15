<?php
/**
 * Integration tests for Automations (scheduled automations CRUD).
 *
 * @package AiAgent
 * @subpackage Tests
 */

namespace AiAgent\Tests\Automations;

use AiAgent\Automations\Automations;
use AiAgent\Automations\AutomationRunner;
use WP_UnitTestCase;

/**
 * Test Automations CRUD, enable/disable, and cron scheduling.
 */
class AutomationsTest extends WP_UnitTestCase {

	/**
	 * Clean up automations created during tests.
	 *
	 * @var int[]
	 */
	private array $created_ids = [];

	/**
	 * Tear down: delete any automations created during the test.
	 */
	public function tearDown(): void {
		foreach ( $this->created_ids as $id ) {
			Automations::delete( $id );
		}
		$this->created_ids = [];
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Create a test automation and track its ID for cleanup.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return int Automation ID.
	 */
	private function create_automation( array $overrides = [] ): int {
		$data = array_merge(
			[
				'name'        => 'Test Automation',
				'description' => 'A test automation',
				'prompt'      => 'Run a test task.',
				'schedule'    => 'daily',
				'enabled'     => 0,
			],
			$overrides
		);

		$id = Automations::create( $data );
		$this->assertIsInt( $id, 'Automations::create() should return an integer ID.' );
		$this->assertGreaterThan( 0, $id );
		$this->created_ids[] = $id;
		return $id;
	}

	// -------------------------------------------------------------------------
	// table_name
	// -------------------------------------------------------------------------

	/**
	 * Test table_name returns the correct prefixed table name.
	 */
	public function test_table_name(): void {
		global $wpdb;
		$this->assertSame( $wpdb->prefix . 'ai_agent_automations', Automations::table_name() );
	}

	// -------------------------------------------------------------------------
	// create
	// -------------------------------------------------------------------------

	/**
	 * Test create returns a positive integer ID.
	 */
	public function test_create_returns_id(): void {
		$id = $this->create_automation();
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * Test create stores all provided fields correctly.
	 */
	public function test_create_stores_fields(): void {
		$id = $this->create_automation(
			[
				'name'           => 'My Automation',
				'description'    => 'Does something useful',
				'prompt'         => 'Check the site health.',
				'schedule'       => 'weekly',
				'tool_profile'   => 'default',
				'max_iterations' => 5,
				'enabled'        => 0,
			]
		);

		$automation = Automations::get( $id );

		$this->assertNotNull( $automation );
		$this->assertSame( 'My Automation', $automation['name'] );
		$this->assertSame( 'Does something useful', $automation['description'] );
		$this->assertSame( 'Check the site health.', $automation['prompt'] );
		$this->assertSame( 'weekly', $automation['schedule'] );
		$this->assertSame( 'default', $automation['tool_profile'] );
		$this->assertSame( 5, $automation['max_iterations'] );
		$this->assertFalse( $automation['enabled'] );
	}

	/**
	 * Test create initialises run_count to zero.
	 */
	public function test_create_initialises_run_count_to_zero(): void {
		$id         = $this->create_automation();
		$automation = Automations::get( $id );

		$this->assertSame( 0, $automation['run_count'] );
	}

	/**
	 * Test create sets created_at and updated_at timestamps.
	 */
	public function test_create_sets_timestamps(): void {
		$id         = $this->create_automation();
		$automation = Automations::get( $id );

		$this->assertNotEmpty( $automation['created_at'] );
		$this->assertNotEmpty( $automation['updated_at'] );
	}

	/**
	 * Test create schedules a cron event when enabled is true.
	 */
	public function test_create_schedules_cron_when_enabled(): void {
		$id = $this->create_automation( [ 'enabled' => 1, 'schedule' => 'daily' ] );

		$timestamp = wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id ] );
		$this->assertNotFalse( $timestamp, 'A cron event should be scheduled for an enabled automation.' );

		// Cleanup cron.
		AutomationRunner::unschedule( $id );
	}

	/**
	 * Test create does not schedule a cron event when disabled.
	 */
	public function test_create_does_not_schedule_cron_when_disabled(): void {
		$id        = $this->create_automation( [ 'enabled' => 0 ] );
		$timestamp = wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id ] );

		$this->assertFalse( $timestamp, 'No cron event should be scheduled for a disabled automation.' );
	}

	// -------------------------------------------------------------------------
	// get
	// -------------------------------------------------------------------------

	/**
	 * Test get returns null for a non-existent ID.
	 */
	public function test_get_returns_null_for_missing_id(): void {
		$this->assertNull( Automations::get( 999999 ) );
	}

	/**
	 * Test get returns an array with expected keys.
	 */
	public function test_get_returns_expected_keys(): void {
		$id         = $this->create_automation();
		$automation = Automations::get( $id );

		$expected_keys = [
			'id', 'name', 'description', 'prompt', 'schedule',
			'cron_expression', 'tool_profile', 'max_iterations',
			'enabled', 'last_run_at', 'next_run_at', 'run_count',
			'created_at', 'updated_at',
		];

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $automation, "Key '{$key}' should be present in automation array." );
		}
	}

	/**
	 * Test get returns correct types for typed fields.
	 */
	public function test_get_returns_correct_types(): void {
		$id         = $this->create_automation();
		$automation = Automations::get( $id );

		$this->assertIsInt( $automation['id'] );
		$this->assertIsInt( $automation['max_iterations'] );
		$this->assertIsInt( $automation['run_count'] );
		$this->assertIsBool( $automation['enabled'] );
	}

	// -------------------------------------------------------------------------
	// list
	// -------------------------------------------------------------------------

	/**
	 * Test list returns an array.
	 */
	public function test_list_returns_array(): void {
		$this->assertIsArray( Automations::list() );
	}

	/**
	 * Test list includes newly created automations.
	 */
	public function test_list_includes_created_automations(): void {
		$id   = $this->create_automation( [ 'name' => 'List Test Automation' ] );
		$list = Automations::list();
		$ids  = array_column( $list, 'id' );

		$this->assertContains( $id, $ids );
	}

	/**
	 * Test list with enabled_only=true returns only enabled automations.
	 */
	public function test_list_enabled_only_filters_disabled(): void {
		$disabled_id = $this->create_automation( [ 'name' => 'Disabled Auto', 'enabled' => 0 ] );
		$enabled_id  = $this->create_automation( [ 'name' => 'Enabled Auto', 'enabled' => 1 ] );

		$enabled_list = Automations::list( true );
		$ids          = array_column( $enabled_list, 'id' );

		$this->assertContains( $enabled_id, $ids, 'Enabled automation should appear in enabled-only list.' );
		$this->assertNotContains( $disabled_id, $ids, 'Disabled automation should not appear in enabled-only list.' );

		// Cleanup cron for the enabled one.
		AutomationRunner::unschedule( $enabled_id );
	}

	/**
	 * Test list returns automations ordered by name ascending.
	 */
	public function test_list_ordered_by_name(): void {
		$this->create_automation( [ 'name' => 'Zebra Automation' ] );
		$this->create_automation( [ 'name' => 'Alpha Automation' ] );

		$list  = Automations::list();
		$names = array_column( $list, 'name' );

		// Find our two entries.
		$alpha_pos = array_search( 'Alpha Automation', $names, true );
		$zebra_pos = array_search( 'Zebra Automation', $names, true );

		$this->assertNotFalse( $alpha_pos );
		$this->assertNotFalse( $zebra_pos );
		$this->assertLessThan( $zebra_pos, $alpha_pos, 'Alpha should come before Zebra in ASC order.' );
	}

	// -------------------------------------------------------------------------
	// update
	// -------------------------------------------------------------------------

	/**
	 * Test update returns false for a non-existent ID.
	 */
	public function test_update_returns_false_for_missing_id(): void {
		$this->assertFalse( Automations::update( 999999, [ 'name' => 'Ghost' ] ) );
	}

	/**
	 * Test update modifies the name field.
	 */
	public function test_update_modifies_name(): void {
		$id = $this->create_automation( [ 'name' => 'Original Name' ] );

		$result = Automations::update( $id, [ 'name' => 'Updated Name' ] );

		$this->assertTrue( $result );
		$this->assertSame( 'Updated Name', Automations::get( $id )['name'] );
	}

	/**
	 * Test update modifies the schedule field.
	 */
	public function test_update_modifies_schedule(): void {
		$id = $this->create_automation( [ 'schedule' => 'daily' ] );

		Automations::update( $id, [ 'schedule' => 'weekly' ] );

		$this->assertSame( 'weekly', Automations::get( $id )['schedule'] );
	}

	/**
	 * Test update with no fields returns true (no-op).
	 */
	public function test_update_with_no_fields_returns_true(): void {
		$id = $this->create_automation();
		$this->assertTrue( Automations::update( $id, [] ) );
	}

	/**
	 * Test update bumps updated_at timestamp.
	 */
	public function test_update_bumps_updated_at(): void {
		$id      = $this->create_automation();
		$before  = Automations::get( $id )['updated_at'];

		// Ensure at least 1 second passes so the timestamp changes.
		sleep( 1 );

		Automations::update( $id, [ 'name' => 'Bumped' ] );
		$after = Automations::get( $id )['updated_at'];

		$this->assertNotSame( $before, $after, 'updated_at should change after an update.' );
	}

	// -------------------------------------------------------------------------
	// delete
	// -------------------------------------------------------------------------

	/**
	 * Test delete removes the automation from the database.
	 */
	public function test_delete_removes_automation(): void {
		$id = $this->create_automation();

		$result = Automations::delete( $id );

		$this->assertTrue( $result );
		$this->assertNull( Automations::get( $id ) );

		// Remove from cleanup list since we already deleted it.
		$this->created_ids = array_diff( $this->created_ids, [ $id ] );
	}

	/**
	 * Test delete unschedules the cron event.
	 */
	public function test_delete_unschedules_cron(): void {
		$id = $this->create_automation( [ 'enabled' => 1 ] );

		// Verify cron was scheduled.
		$this->assertNotFalse( wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id ] ) );

		Automations::delete( $id );
		$this->created_ids = array_diff( $this->created_ids, [ $id ] );

		$this->assertFalse(
			wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id ] ),
			'Cron event should be removed when automation is deleted.'
		);
	}

	// -------------------------------------------------------------------------
	// enable / disable via update
	// -------------------------------------------------------------------------

	/**
	 * Test enabling an automation schedules a cron event.
	 */
	public function test_enable_automation_schedules_cron(): void {
		$id = $this->create_automation( [ 'enabled' => 0 ] );

		Automations::update( $id, [ 'enabled' => 1, 'schedule' => 'daily' ] );

		$timestamp = wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id ] );
		$this->assertNotFalse( $timestamp, 'Enabling an automation should schedule a cron event.' );

		AutomationRunner::unschedule( $id );
	}

	/**
	 * Test disabling an automation removes the cron event.
	 */
	public function test_disable_automation_removes_cron(): void {
		$id = $this->create_automation( [ 'enabled' => 1 ] );

		// Confirm cron is scheduled.
		$this->assertNotFalse( wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id ] ) );

		Automations::update( $id, [ 'enabled' => 0 ] );

		$this->assertFalse(
			wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id ] ),
			'Disabling an automation should remove the cron event.'
		);
	}

	// -------------------------------------------------------------------------
	// record_run
	// -------------------------------------------------------------------------

	/**
	 * Test record_run increments run_count and sets last_run_at.
	 */
	public function test_record_run_increments_count(): void {
		$id = $this->create_automation();

		$this->assertSame( 0, Automations::get( $id )['run_count'] );

		$now = current_time( 'mysql', true );
		Automations::record_run( $id, $now );

		$automation = Automations::get( $id );
		$this->assertSame( 1, $automation['run_count'] );
		$this->assertSame( $now, $automation['last_run_at'] );
	}

	/**
	 * Test record_run accumulates multiple runs.
	 */
	public function test_record_run_accumulates(): void {
		$id  = $this->create_automation();
		$now = current_time( 'mysql', true );

		Automations::record_run( $id, $now );
		Automations::record_run( $id, $now );
		Automations::record_run( $id, $now );

		$this->assertSame( 3, Automations::get( $id )['run_count'] );
	}

	// -------------------------------------------------------------------------
	// get_templates
	// -------------------------------------------------------------------------

	/**
	 * Test get_templates returns a non-empty array.
	 */
	public function test_get_templates_returns_array(): void {
		$templates = Automations::get_templates();

		$this->assertIsArray( $templates );
		$this->assertNotEmpty( $templates );
	}

	/**
	 * Test each template has required keys.
	 */
	public function test_get_templates_have_required_keys(): void {
		$templates = Automations::get_templates();

		foreach ( $templates as $template ) {
			$this->assertArrayHasKey( 'name', $template );
			$this->assertArrayHasKey( 'description', $template );
			$this->assertArrayHasKey( 'prompt', $template );
			$this->assertArrayHasKey( 'schedule', $template );
		}
	}

	/**
	 * Test each template schedule is a valid schedule value.
	 */
	public function test_get_templates_have_valid_schedules(): void {
		$templates = Automations::get_templates();

		foreach ( $templates as $template ) {
			$this->assertContains(
				$template['schedule'],
				Automations::VALID_SCHEDULES,
				"Template '{$template['name']}' has an invalid schedule: {$template['schedule']}"
			);
		}
	}

	// -------------------------------------------------------------------------
	// VALID_SCHEDULES constant
	// -------------------------------------------------------------------------

	/**
	 * Test VALID_SCHEDULES contains expected values.
	 */
	public function test_valid_schedules_constant(): void {
		$this->assertContains( 'hourly', Automations::VALID_SCHEDULES );
		$this->assertContains( 'twicedaily', Automations::VALID_SCHEDULES );
		$this->assertContains( 'daily', Automations::VALID_SCHEDULES );
		$this->assertContains( 'weekly', Automations::VALID_SCHEDULES );
	}
}
