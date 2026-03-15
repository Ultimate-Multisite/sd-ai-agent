<?php
/**
 * Integration tests for EventTriggerRegistry.
 *
 * @package AiAgent
 * @subpackage Tests
 */

namespace AiAgent\Tests\Automations;

use AiAgent\Automations\EventTriggerRegistry;
use WP_UnitTestCase;

/**
 * Test EventTriggerRegistry lookup, grouping, and filter extensibility.
 */
class EventTriggerRegistryTest extends WP_UnitTestCase {

	// -------------------------------------------------------------------------
	// get_all
	// -------------------------------------------------------------------------

	/**
	 * Test get_all returns a non-empty array.
	 */
	public function test_get_all_returns_non_empty_array(): void {
		$triggers = EventTriggerRegistry::get_all();

		$this->assertIsArray( $triggers );
		$this->assertNotEmpty( $triggers );
	}

	/**
	 * Test each trigger in get_all has required keys.
	 */
	public function test_get_all_triggers_have_required_keys(): void {
		$triggers = EventTriggerRegistry::get_all();

		foreach ( $triggers as $trigger ) {
			$this->assertArrayHasKey( 'hook_name', $trigger, 'Trigger must have hook_name.' );
			$this->assertArrayHasKey( 'label', $trigger, 'Trigger must have label.' );
			$this->assertArrayHasKey( 'description', $trigger, 'Trigger must have description.' );
			$this->assertArrayHasKey( 'category', $trigger, 'Trigger must have category.' );
			$this->assertArrayHasKey( 'args', $trigger, 'Trigger must have args.' );
			$this->assertArrayHasKey( 'placeholders', $trigger, 'Trigger must have placeholders.' );
			$this->assertArrayHasKey( 'conditions', $trigger, 'Trigger must have conditions.' );
		}
	}

	/**
	 * Test each trigger has a non-empty hook_name string.
	 */
	public function test_get_all_triggers_have_non_empty_hook_names(): void {
		$triggers = EventTriggerRegistry::get_all();

		foreach ( $triggers as $trigger ) {
			$this->assertIsString( $trigger['hook_name'] );
			$this->assertNotEmpty( $trigger['hook_name'] );
		}
	}

	/**
	 * Test each trigger's args is an array.
	 */
	public function test_get_all_triggers_args_are_arrays(): void {
		$triggers = EventTriggerRegistry::get_all();

		foreach ( $triggers as $trigger ) {
			$this->assertIsArray( $trigger['args'], "args for hook '{$trigger['hook_name']}' should be an array." );
		}
	}

	/**
	 * Test get_all includes core WordPress triggers.
	 */
	public function test_get_all_includes_wordpress_triggers(): void {
		$triggers   = EventTriggerRegistry::get_all();
		$hook_names = array_column( $triggers, 'hook_name' );

		$expected_hooks = [
			'transition_post_status',
			'user_register',
			'wp_login',
			'comment_post',
			'delete_post',
		];

		foreach ( $expected_hooks as $hook ) {
			$this->assertContains( $hook, $hook_names, "Core hook '{$hook}' should be in the registry." );
		}
	}

	/**
	 * Test get_all is extensible via the ai_agent_event_triggers filter.
	 */
	public function test_get_all_is_filterable(): void {
		$custom_trigger = [
			'hook_name'    => 'my_custom_hook',
			'label'        => 'Custom Hook',
			'description'  => 'A custom hook for testing.',
			'category'     => 'other',
			'args'         => [ 'arg1' ],
			'placeholders' => [],
			'conditions'   => [],
		];

		$filter = static function ( array $triggers ) use ( $custom_trigger ): array {
			$triggers[] = $custom_trigger;
			return $triggers;
		};

		add_filter( 'ai_agent_event_triggers', $filter );
		$triggers   = EventTriggerRegistry::get_all();
		$hook_names = array_column( $triggers, 'hook_name' );
		remove_filter( 'ai_agent_event_triggers', $filter );

		$this->assertContains( 'my_custom_hook', $hook_names, 'Custom triggers added via filter should appear in get_all().' );
	}

	// -------------------------------------------------------------------------
	// get
	// -------------------------------------------------------------------------

	/**
	 * Test get returns the correct trigger definition for a known hook.
	 */
	public function test_get_returns_trigger_for_known_hook(): void {
		$trigger = EventTriggerRegistry::get( 'transition_post_status' );

		$this->assertNotNull( $trigger );
		$this->assertSame( 'transition_post_status', $trigger['hook_name'] );
	}

	/**
	 * Test get returns null for an unknown hook name.
	 */
	public function test_get_returns_null_for_unknown_hook(): void {
		$this->assertNull( EventTriggerRegistry::get( 'nonexistent_hook_xyz' ) );
	}

	/**
	 * Test get returns the correct args for transition_post_status.
	 */
	public function test_get_returns_correct_args_for_transition_post_status(): void {
		$trigger = EventTriggerRegistry::get( 'transition_post_status' );

		$this->assertNotNull( $trigger );
		$this->assertContains( 'new_status', $trigger['args'] );
		$this->assertContains( 'old_status', $trigger['args'] );
		$this->assertContains( 'post', $trigger['args'] );
	}

	/**
	 * Test get returns the correct args for user_register.
	 */
	public function test_get_returns_correct_args_for_user_register(): void {
		$trigger = EventTriggerRegistry::get( 'user_register' );

		$this->assertNotNull( $trigger );
		$this->assertContains( 'user_id', $trigger['args'] );
	}

	/**
	 * Test get returns the correct args for comment_post.
	 */
	public function test_get_returns_correct_args_for_comment_post(): void {
		$trigger = EventTriggerRegistry::get( 'comment_post' );

		$this->assertNotNull( $trigger );
		$this->assertContains( 'comment_id', $trigger['args'] );
		$this->assertContains( 'comment_approved', $trigger['args'] );
	}

	/**
	 * Test get returns conditions for hooks that support them.
	 */
	public function test_get_returns_conditions_for_transition_post_status(): void {
		$trigger = EventTriggerRegistry::get( 'transition_post_status' );

		$this->assertNotNull( $trigger );
		$this->assertArrayHasKey( 'post_type', $trigger['conditions'] );
		$this->assertArrayHasKey( 'new_status', $trigger['conditions'] );
		$this->assertArrayHasKey( 'old_status', $trigger['conditions'] );
	}

	/**
	 * Test get returns placeholders for transition_post_status.
	 */
	public function test_get_returns_placeholders_for_transition_post_status(): void {
		$trigger = EventTriggerRegistry::get( 'transition_post_status' );

		$this->assertNotNull( $trigger );
		$this->assertArrayHasKey( 'post.ID', $trigger['placeholders'] );
		$this->assertArrayHasKey( 'post.title', $trigger['placeholders'] );
	}

	// -------------------------------------------------------------------------
	// get_grouped
	// -------------------------------------------------------------------------

	/**
	 * Test get_grouped returns an array.
	 */
	public function test_get_grouped_returns_array(): void {
		$grouped = EventTriggerRegistry::get_grouped();
		$this->assertIsArray( $grouped );
	}

	/**
	 * Test get_grouped includes a 'wordpress' category.
	 */
	public function test_get_grouped_includes_wordpress_category(): void {
		$grouped = EventTriggerRegistry::get_grouped();
		$this->assertArrayHasKey( 'wordpress', $grouped );
	}

	/**
	 * Test each group has 'label' and 'triggers' keys.
	 */
	public function test_get_grouped_groups_have_required_keys(): void {
		$grouped = EventTriggerRegistry::get_grouped();

		foreach ( $grouped as $category => $group ) {
			$this->assertArrayHasKey( 'label', $group, "Category '{$category}' should have a label." );
			$this->assertArrayHasKey( 'triggers', $group, "Category '{$category}' should have triggers." );
			$this->assertIsArray( $group['triggers'] );
		}
	}

	/**
	 * Test get_grouped wordpress category contains transition_post_status.
	 */
	public function test_get_grouped_wordpress_contains_transition_post_status(): void {
		$grouped = EventTriggerRegistry::get_grouped();

		$this->assertArrayHasKey( 'wordpress', $grouped );

		$hook_names = array_column( $grouped['wordpress']['triggers'], 'hook_name' );
		$this->assertContains( 'transition_post_status', $hook_names );
	}

	/**
	 * Test get_grouped does not include woocommerce category when WooCommerce is absent.
	 */
	public function test_get_grouped_excludes_woocommerce_when_not_active(): void {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is active; skipping absence test.' );
		}

		$grouped = EventTriggerRegistry::get_grouped();
		$this->assertArrayNotHasKey( 'woocommerce', $grouped );
	}

	// -------------------------------------------------------------------------
	// Hook name uniqueness
	// -------------------------------------------------------------------------

	/**
	 * Test that all hook names in the registry are unique.
	 */
	public function test_hook_names_are_unique(): void {
		$triggers   = EventTriggerRegistry::get_all();
		$hook_names = array_column( $triggers, 'hook_name' );
		$unique     = array_unique( $hook_names );

		$this->assertCount(
			count( $unique ),
			$hook_names,
			'All hook names in the registry should be unique.'
		);
	}
}
