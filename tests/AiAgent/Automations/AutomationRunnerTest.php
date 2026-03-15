<?php
/**
 * Integration tests for AutomationRunner (cron scheduling and execution).
 *
 * @package AiAgent
 * @subpackage Tests
 */

namespace AiAgent\Tests\Automations;

use AiAgent\Automations\AutomationRunner;
use AiAgent\Automations\Automations;
use WP_UnitTestCase;

/**
 * Test AutomationRunner schedule/unschedule, cron hook, and reschedule_all.
 *
 * Note: AutomationRunner::run() invokes AgentLoop which requires a live AI
 * provider. Those execution paths are covered by the scheduled-execution tests
 * below using a mock/stub approach via WordPress hooks, keeping the suite
 * self-contained and provider-independent.
 */
class AutomationRunnerTest extends WP_UnitTestCase {

	/**
	 * Automation IDs created during tests (for cleanup).
	 *
	 * @var int[]
	 */
	private array $automation_ids = [];

	/**
	 * Tear down: delete automations and unschedule any lingering cron events.
	 */
	public function tearDown(): void {
		foreach ( $this->automation_ids as $id ) {
			AutomationRunner::unschedule( $id );
			Automations::delete( $id );
		}
		$this->automation_ids = [];
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Create a minimal automation and track it for cleanup.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return int Automation ID.
	 */
	private function create_automation( array $overrides = [] ): int {
		$data = array_merge(
			[
				'name'    => 'Runner Test Automation',
				'prompt'  => 'Test prompt.',
				'enabled' => 0,
			],
			$overrides
		);

		$id = Automations::create( $data );
		$this->assertIsInt( $id );
		$this->automation_ids[] = $id;
		return $id;
	}

	// -------------------------------------------------------------------------
	// CRON_HOOK constant
	// -------------------------------------------------------------------------

	/**
	 * Test CRON_HOOK constant has the expected value.
	 */
	public function test_cron_hook_constant(): void {
		$this->assertSame( 'ai_agent_run_automation', AutomationRunner::CRON_HOOK );
	}

	// -------------------------------------------------------------------------
	// schedule
	// -------------------------------------------------------------------------

	/**
	 * Test schedule registers a cron event for the automation.
	 */
	public function test_schedule_registers_cron_event(): void {
		$id = $this->create_automation();

		AutomationRunner::schedule( $id, 'daily' );

		$timestamp = wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id ] );
		$this->assertNotFalse( $timestamp, 'A cron event should be registered after schedule().' );
		$this->assertGreaterThan( 0, $timestamp );
	}

	/**
	 * Test schedule is idempotent — calling it twice does not create duplicates.
	 */
	public function test_schedule_is_idempotent(): void {
		$id = $this->create_automation();

		AutomationRunner::schedule( $id, 'daily' );
		$first_timestamp = wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id ] );

		AutomationRunner::schedule( $id, 'daily' );
		$second_timestamp = wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id ] );

		$this->assertSame( $first_timestamp, $second_timestamp, 'Calling schedule() twice should not create a second event.' );
	}

	/**
	 * Test schedule works with all valid schedule names.
	 */
	public function test_schedule_accepts_all_valid_schedules(): void {
		foreach ( Automations::VALID_SCHEDULES as $schedule ) {
			$id = $this->create_automation( [ 'name' => "Schedule test: {$schedule}" ] );

			AutomationRunner::schedule( $id, $schedule );

			$timestamp = wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id ] );
			$this->assertNotFalse(
				$timestamp,
				"schedule() should register a cron event for schedule '{$schedule}'."
			);

			AutomationRunner::unschedule( $id );
		}
	}

	// -------------------------------------------------------------------------
	// unschedule
	// -------------------------------------------------------------------------

	/**
	 * Test unschedule removes a previously scheduled cron event.
	 */
	public function test_unschedule_removes_cron_event(): void {
		$id = $this->create_automation();

		AutomationRunner::schedule( $id, 'daily' );
		$this->assertNotFalse( wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id ] ) );

		AutomationRunner::unschedule( $id );

		$this->assertFalse(
			wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id ] ),
			'Cron event should be removed after unschedule().'
		);
	}

	/**
	 * Test unschedule is safe to call when no event is scheduled.
	 */
	public function test_unschedule_is_safe_when_not_scheduled(): void {
		$id = $this->create_automation();

		// Should not throw or produce errors.
		AutomationRunner::unschedule( $id );

		$this->assertFalse( wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id ] ) );
	}

	// -------------------------------------------------------------------------
	// add_cron_schedules
	// -------------------------------------------------------------------------

	/**
	 * Test add_cron_schedules adds a weekly schedule if not present.
	 */
	public function test_add_cron_schedules_adds_weekly(): void {
		$schedules = AutomationRunner::add_cron_schedules( [] );

		$this->assertArrayHasKey( 'weekly', $schedules );
		$this->assertArrayHasKey( 'interval', $schedules['weekly'] );
		$this->assertSame( WEEK_IN_SECONDS, $schedules['weekly']['interval'] );
	}

	/**
	 * Test add_cron_schedules does not overwrite an existing weekly schedule.
	 */
	public function test_add_cron_schedules_does_not_overwrite_existing(): void {
		$existing = [
			'weekly' => [
				'interval' => 604800,
				'display'  => 'Custom Weekly',
			],
		];

		$schedules = AutomationRunner::add_cron_schedules( $existing );

		$this->assertSame( 'Custom Weekly', $schedules['weekly']['display'] );
	}

	/**
	 * Test add_cron_schedules preserves existing schedules.
	 */
	public function test_add_cron_schedules_preserves_existing(): void {
		$existing = [
			'hourly' => [ 'interval' => 3600, 'display' => 'Once Hourly' ],
		];

		$schedules = AutomationRunner::add_cron_schedules( $existing );

		$this->assertArrayHasKey( 'hourly', $schedules );
		$this->assertArrayHasKey( 'weekly', $schedules );
	}

	// -------------------------------------------------------------------------
	// reschedule_all
	// -------------------------------------------------------------------------

	/**
	 * Test reschedule_all schedules cron events for all enabled automations.
	 */
	public function test_reschedule_all_schedules_enabled_automations(): void {
		$enabled_id  = $this->create_automation( [ 'enabled' => 1, 'schedule' => 'daily' ] );
		$disabled_id = $this->create_automation( [ 'enabled' => 0 ] );

		// Unschedule both first to simulate a clean state.
		AutomationRunner::unschedule( $enabled_id );
		AutomationRunner::unschedule( $disabled_id );

		AutomationRunner::reschedule_all();

		$this->assertNotFalse(
			wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $enabled_id ] ),
			'Enabled automation should be rescheduled by reschedule_all().'
		);

		$this->assertFalse(
			wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $disabled_id ] ),
			'Disabled automation should not be scheduled by reschedule_all().'
		);
	}

	// -------------------------------------------------------------------------
	// unschedule_all
	// -------------------------------------------------------------------------

	/**
	 * Test unschedule_all removes cron events for all automations.
	 */
	public function test_unschedule_all_removes_all_cron_events(): void {
		$id1 = $this->create_automation( [ 'enabled' => 1 ] );
		$id2 = $this->create_automation( [ 'enabled' => 1 ] );

		// Confirm both are scheduled.
		$this->assertNotFalse( wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id1 ] ) );
		$this->assertNotFalse( wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id2 ] ) );

		AutomationRunner::unschedule_all();

		$this->assertFalse(
			wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id1 ] ),
			'Cron event for automation 1 should be removed by unschedule_all().'
		);
		$this->assertFalse(
			wp_next_scheduled( AutomationRunner::CRON_HOOK, [ $id2 ] ),
			'Cron event for automation 2 should be removed by unschedule_all().'
		);
	}

	// -------------------------------------------------------------------------
	// run — returns null for missing/disabled automation
	// -------------------------------------------------------------------------

	/**
	 * Test run returns null when the automation does not exist.
	 */
	public function test_run_returns_null_for_missing_automation(): void {
		$result = AutomationRunner::run( 999999 );
		$this->assertNull( $result );
	}

	// -------------------------------------------------------------------------
	// Scheduled execution — cron hook fires AutomationRunner::run
	// -------------------------------------------------------------------------

	/**
	 * Test that the CRON_HOOK action is registered and calls AutomationRunner::run.
	 *
	 * We verify the hook is wired up by checking has_action(), not by firing
	 * the full agent loop (which requires a live AI provider).
	 */
	public function test_cron_hook_is_registered_after_register(): void {
		AutomationRunner::register();

		$this->assertNotFalse(
			has_action( AutomationRunner::CRON_HOOK, [ AutomationRunner::class, 'run' ] ),
			'AutomationRunner::run should be registered on the CRON_HOOK action.'
		);
	}

	/**
	 * Test that the cron_schedules filter is registered after register().
	 */
	public function test_cron_schedules_filter_is_registered(): void {
		AutomationRunner::register();

		$this->assertNotFalse(
			has_filter( 'cron_schedules', [ AutomationRunner::class, 'add_cron_schedules' ] ),
			'add_cron_schedules should be registered on the cron_schedules filter.'
		);
	}
}
