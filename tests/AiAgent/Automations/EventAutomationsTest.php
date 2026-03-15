<?php
/**
 * Integration tests for EventAutomations (event-driven automations CRUD).
 *
 * @package AiAgent
 * @subpackage Tests
 */

namespace AiAgent\Tests\Automations;

use AiAgent\Automations\EventAutomations;
use WP_UnitTestCase;

/**
 * Test EventAutomations CRUD, conditions, and trigger evaluation.
 */
class EventAutomationsTest extends WP_UnitTestCase {

	/**
	 * Event automation IDs created during tests (for cleanup).
	 *
	 * @var int[]
	 */
	private array $created_ids = [];

	/**
	 * Tear down: delete event automations created during the test.
	 */
	public function tearDown(): void {
		foreach ( $this->created_ids as $id ) {
			EventAutomations::delete( $id );
		}
		$this->created_ids = [];
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Create a test event automation and track its ID for cleanup.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return int Event automation ID.
	 */
	private function create_event( array $overrides = [] ): int {
		$data = array_merge(
			[
				'name'             => 'Test Event',
				'description'      => 'A test event automation',
				'hook_name'        => 'transition_post_status',
				'prompt_template'  => 'Post {{post.title}} changed status.',
				'conditions'       => [],
				'tool_profile'     => '',
				'max_iterations'   => 5,
				'enabled'          => 0,
			],
			$overrides
		);

		$id = EventAutomations::create( $data );
		$this->assertIsInt( $id, 'EventAutomations::create() should return an integer ID.' );
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
		$this->assertSame( $wpdb->prefix . 'ai_agent_event_automations', EventAutomations::table_name() );
	}

	// -------------------------------------------------------------------------
	// create
	// -------------------------------------------------------------------------

	/**
	 * Test create returns a positive integer ID.
	 */
	public function test_create_returns_id(): void {
		$id = $this->create_event();
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * Test create stores all provided fields correctly.
	 */
	public function test_create_stores_fields(): void {
		$conditions = [ 'post_type' => 'post', 'new_status' => 'publish' ];

		$id = $this->create_event(
			[
				'name'            => 'Publish Event',
				'description'     => 'Fires on publish',
				'hook_name'       => 'transition_post_status',
				'prompt_template' => 'Post {{post.title}} was published.',
				'conditions'      => $conditions,
				'tool_profile'    => 'minimal',
				'max_iterations'  => 3,
				'enabled'         => 0,
			]
		);

		$event = EventAutomations::get( $id );

		$this->assertNotNull( $event );
		$this->assertSame( 'Publish Event', $event['name'] );
		$this->assertSame( 'Fires on publish', $event['description'] );
		$this->assertSame( 'transition_post_status', $event['hook_name'] );
		$this->assertSame( 'Post {{post.title}} was published.', $event['prompt_template'] );
		$this->assertSame( $conditions, $event['conditions'] );
		$this->assertSame( 'minimal', $event['tool_profile'] );
		$this->assertSame( 3, $event['max_iterations'] );
		$this->assertFalse( $event['enabled'] );
	}

	/**
	 * Test create initialises run_count to zero.
	 */
	public function test_create_initialises_run_count_to_zero(): void {
		$id    = $this->create_event();
		$event = EventAutomations::get( $id );

		$this->assertSame( 0, $event['run_count'] );
	}

	/**
	 * Test create sets created_at and updated_at timestamps.
	 */
	public function test_create_sets_timestamps(): void {
		$id    = $this->create_event();
		$event = EventAutomations::get( $id );

		$this->assertNotEmpty( $event['created_at'] );
		$this->assertNotEmpty( $event['updated_at'] );
	}

	/**
	 * Test create stores conditions as a decoded array (JSON round-trip).
	 */
	public function test_create_stores_conditions_as_array(): void {
		$conditions = [ 'post_type' => 'page', 'new_status' => 'draft' ];
		$id         = $this->create_event( [ 'conditions' => $conditions ] );
		$event      = EventAutomations::get( $id );

		$this->assertIsArray( $event['conditions'] );
		$this->assertSame( 'page', $event['conditions']['post_type'] );
		$this->assertSame( 'draft', $event['conditions']['new_status'] );
	}

	/**
	 * Test create with empty conditions stores an empty array.
	 */
	public function test_create_with_empty_conditions(): void {
		$id    = $this->create_event( [ 'conditions' => [] ] );
		$event = EventAutomations::get( $id );

		$this->assertIsArray( $event['conditions'] );
		$this->assertEmpty( $event['conditions'] );
	}

	// -------------------------------------------------------------------------
	// get
	// -------------------------------------------------------------------------

	/**
	 * Test get returns null for a non-existent ID.
	 */
	public function test_get_returns_null_for_missing_id(): void {
		$this->assertNull( EventAutomations::get( 999999 ) );
	}

	/**
	 * Test get returns an array with expected keys.
	 */
	public function test_get_returns_expected_keys(): void {
		$id    = $this->create_event();
		$event = EventAutomations::get( $id );

		$expected_keys = [
			'id', 'name', 'description', 'hook_name', 'prompt_template',
			'conditions', 'tool_profile', 'max_iterations', 'enabled',
			'run_count', 'last_run_at', 'created_at', 'updated_at',
		];

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $event, "Key '{$key}' should be present in event automation array." );
		}
	}

	/**
	 * Test get returns correct types for typed fields.
	 */
	public function test_get_returns_correct_types(): void {
		$id    = $this->create_event();
		$event = EventAutomations::get( $id );

		$this->assertIsInt( $event['id'] );
		$this->assertIsInt( $event['max_iterations'] );
		$this->assertIsInt( $event['run_count'] );
		$this->assertIsBool( $event['enabled'] );
		$this->assertIsArray( $event['conditions'] );
	}

	// -------------------------------------------------------------------------
	// list
	// -------------------------------------------------------------------------

	/**
	 * Test list returns an array.
	 */
	public function test_list_returns_array(): void {
		$this->assertIsArray( EventAutomations::list() );
	}

	/**
	 * Test list includes newly created event automations.
	 */
	public function test_list_includes_created_events(): void {
		$id   = $this->create_event( [ 'name' => 'List Test Event' ] );
		$list = EventAutomations::list();
		$ids  = array_column( $list, 'id' );

		$this->assertContains( $id, $ids );
	}

	/**
	 * Test list with enabled_only=true returns only enabled events.
	 */
	public function test_list_enabled_only_filters_disabled(): void {
		$disabled_id = $this->create_event( [ 'name' => 'Disabled Event', 'enabled' => 0 ] );
		$enabled_id  = $this->create_event( [ 'name' => 'Enabled Event', 'enabled' => 1 ] );

		$enabled_list = EventAutomations::list( true );
		$ids          = array_column( $enabled_list, 'id' );

		$this->assertContains( $enabled_id, $ids, 'Enabled event should appear in enabled-only list.' );
		$this->assertNotContains( $disabled_id, $ids, 'Disabled event should not appear in enabled-only list.' );
	}

	/**
	 * Test list returns events ordered by name ascending.
	 */
	public function test_list_ordered_by_name(): void {
		$this->create_event( [ 'name' => 'Zebra Event' ] );
		$this->create_event( [ 'name' => 'Alpha Event' ] );

		$list  = EventAutomations::list();
		$names = array_column( $list, 'name' );

		$alpha_pos = array_search( 'Alpha Event', $names, true );
		$zebra_pos = array_search( 'Zebra Event', $names, true );

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
		$this->assertFalse( EventAutomations::update( 999999, [ 'name' => 'Ghost' ] ) );
	}

	/**
	 * Test update modifies the name field.
	 */
	public function test_update_modifies_name(): void {
		$id = $this->create_event( [ 'name' => 'Original Name' ] );

		$result = EventAutomations::update( $id, [ 'name' => 'Updated Name' ] );

		$this->assertTrue( $result );
		$this->assertSame( 'Updated Name', EventAutomations::get( $id )['name'] );
	}

	/**
	 * Test update modifies the hook_name field.
	 */
	public function test_update_modifies_hook_name(): void {
		$id = $this->create_event( [ 'hook_name' => 'transition_post_status' ] );

		EventAutomations::update( $id, [ 'hook_name' => 'user_register' ] );

		$this->assertSame( 'user_register', EventAutomations::get( $id )['hook_name'] );
	}

	/**
	 * Test update modifies conditions.
	 */
	public function test_update_modifies_conditions(): void {
		$id = $this->create_event( [ 'conditions' => [] ] );

		$new_conditions = [ 'post_type' => 'post' ];
		EventAutomations::update( $id, [ 'conditions' => $new_conditions ] );

		$event = EventAutomations::get( $id );
		$this->assertSame( $new_conditions, $event['conditions'] );
	}

	/**
	 * Test update with no fields returns true (no-op).
	 */
	public function test_update_with_no_fields_returns_true(): void {
		$id = $this->create_event();
		$this->assertTrue( EventAutomations::update( $id, [] ) );
	}

	/**
	 * Test update bumps updated_at timestamp.
	 */
	public function test_update_bumps_updated_at(): void {
		$id     = $this->create_event();
		$before = EventAutomations::get( $id )['updated_at'];

		sleep( 1 );

		EventAutomations::update( $id, [ 'name' => 'Bumped' ] );
		$after = EventAutomations::get( $id )['updated_at'];

		$this->assertNotSame( $before, $after, 'updated_at should change after an update.' );
	}

	// -------------------------------------------------------------------------
	// delete
	// -------------------------------------------------------------------------

	/**
	 * Test delete removes the event automation from the database.
	 */
	public function test_delete_removes_event(): void {
		$id = $this->create_event();

		$result = EventAutomations::delete( $id );

		$this->assertTrue( $result );
		$this->assertNull( EventAutomations::get( $id ) );

		$this->created_ids = array_diff( $this->created_ids, [ $id ] );
	}

	// -------------------------------------------------------------------------
	// record_run
	// -------------------------------------------------------------------------

	/**
	 * Test record_run increments run_count and sets last_run_at.
	 */
	public function test_record_run_increments_count(): void {
		$id = $this->create_event();

		$this->assertSame( 0, EventAutomations::get( $id )['run_count'] );

		EventAutomations::record_run( $id );

		$event = EventAutomations::get( $id );
		$this->assertSame( 1, $event['run_count'] );
		$this->assertNotEmpty( $event['last_run_at'] );
	}

	/**
	 * Test record_run accumulates multiple runs.
	 */
	public function test_record_run_accumulates(): void {
		$id = $this->create_event();

		EventAutomations::record_run( $id );
		EventAutomations::record_run( $id );
		EventAutomations::record_run( $id );

		$this->assertSame( 3, EventAutomations::get( $id )['run_count'] );
	}

	// -------------------------------------------------------------------------
	// Trigger evaluation (conditions)
	// -------------------------------------------------------------------------

	/**
	 * Test that an event with no conditions is always enabled for dispatch.
	 *
	 * This verifies the data layer: an event with empty conditions is stored
	 * and retrieved correctly, and appears in the enabled list.
	 */
	public function test_event_with_no_conditions_is_retrievable(): void {
		$id    = $this->create_event( [ 'conditions' => [], 'enabled' => 1 ] );
		$event = EventAutomations::get( $id );

		$this->assertEmpty( $event['conditions'] );
		$this->assertTrue( $event['enabled'] );

		$enabled = EventAutomations::list( true );
		$ids     = array_column( $enabled, 'id' );
		$this->assertContains( $id, $ids );
	}

	/**
	 * Test that conditions are stored and retrieved as a structured array.
	 *
	 * Verifies the JSON round-trip for complex condition structures.
	 */
	public function test_complex_conditions_round_trip(): void {
		$conditions = [
			'post_type'  => 'post',
			'new_status' => 'publish',
			'old_status' => 'draft',
		];

		$id    = $this->create_event( [ 'conditions' => $conditions ] );
		$event = EventAutomations::get( $id );

		$this->assertSame( 'post', $event['conditions']['post_type'] );
		$this->assertSame( 'publish', $event['conditions']['new_status'] );
		$this->assertSame( 'draft', $event['conditions']['old_status'] );
	}

	/**
	 * Test that role conditions are stored and retrieved correctly.
	 */
	public function test_role_condition_round_trip(): void {
		$conditions = [ 'role' => 'administrator' ];
		$id         = $this->create_event( [ 'conditions' => $conditions ] );
		$event      = EventAutomations::get( $id );

		$this->assertSame( 'administrator', $event['conditions']['role'] );
	}

	/**
	 * Test that approved conditions are stored and retrieved correctly.
	 */
	public function test_approved_condition_round_trip(): void {
		$conditions = [ 'approved' => '1' ];
		$id         = $this->create_event(
			[
				'hook_name'  => 'comment_post',
				'conditions' => $conditions,
			]
		);
		$event = EventAutomations::get( $id );

		$this->assertSame( '1', $event['conditions']['approved'] );
	}

	// -------------------------------------------------------------------------
	// enable / disable via update
	// -------------------------------------------------------------------------

	/**
	 * Test enabling an event automation makes it appear in enabled-only list.
	 */
	public function test_enable_event_appears_in_enabled_list(): void {
		$id = $this->create_event( [ 'enabled' => 0 ] );

		EventAutomations::update( $id, [ 'enabled' => 1 ] );

		$enabled = EventAutomations::list( true );
		$ids     = array_column( $enabled, 'id' );
		$this->assertContains( $id, $ids );
	}

	/**
	 * Test disabling an event automation removes it from the enabled-only list.
	 */
	public function test_disable_event_removed_from_enabled_list(): void {
		$id = $this->create_event( [ 'enabled' => 1 ] );

		EventAutomations::update( $id, [ 'enabled' => 0 ] );

		$enabled = EventAutomations::list( true );
		$ids     = array_column( $enabled, 'id' );
		$this->assertNotContains( $id, $ids );
	}
}
