<?php
/**
 * Test case for ToolCapabilities class.
 *
 * @package AiAgent
 * @subpackage Tests
 */

namespace AiAgent\Tests\Abilities;

use AiAgent\Abilities\ToolCapabilities;
use WP_UnitTestCase;

/**
 * Test ToolCapabilities functionality.
 */
class ToolCapabilitiesTest extends WP_UnitTestCase {

	/**
	 * Test cap_name derives the correct capability name from an ability ID.
	 *
	 * @dataProvider provider_cap_name
	 *
	 * @param string $ability_id   Input ability ID.
	 * @param string $expected_cap Expected capability name.
	 */
	public function test_cap_name( string $ability_id, string $expected_cap ): void {
		$this->assertSame( $expected_cap, ToolCapabilities::cap_name( $ability_id ) );
	}

	/**
	 * Data provider for test_cap_name.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function provider_cap_name(): array {
		return [
			'memory-save'              => [ 'ai-agent/memory-save', 'ai_agent_tool_memory_save' ],
			'memory-list'              => [ 'ai-agent/memory-list', 'ai_agent_tool_memory_list' ],
			'memory-delete'            => [ 'ai-agent/memory-delete', 'ai_agent_tool_memory_delete' ],
			'db-query'                 => [ 'ai-agent/db-query', 'ai_agent_tool_db_query' ],
			'run-php'                  => [ 'ai-agent/run-php', 'ai_agent_tool_run_php' ],
			'file-read'                => [ 'ai-agent/file-read', 'ai_agent_tool_file_read' ],
			'get-plugins'              => [ 'ai-agent/get-plugins', 'ai_agent_tool_get_plugins' ],
			'navigate'                 => [ 'ai-agent/navigate', 'ai_agent_tool_navigate' ],
			'seo-audit-url'            => [ 'ai-agent/seo-audit-url', 'ai_agent_tool_seo_audit_url' ],
			'content-analyze'          => [ 'ai-agent/content-analyze', 'ai_agent_tool_content_analyze' ],
			'markdown-to-blocks'       => [ 'ai-agent/markdown-to-blocks', 'ai_agent_tool_markdown_to_blocks' ],
			'import-stock-image'       => [ 'ai-agent/import-stock-image', 'ai_agent_tool_import_stock_image' ],
			'custom-tool-with-slashes' => [ 'ai-agent-custom/my-tool', 'ai_agent_tool_my_tool' ],
		];
	}

	/**
	 * Test capability_exists returns false when capability is not in any role.
	 */
	public function test_capability_exists_returns_false_for_unknown_cap(): void {
		$this->assertFalse( ToolCapabilities::capability_exists( 'ai_agent_tool_nonexistent_xyz_12345' ) );
	}

	/**
	 * Test capability_exists returns true after adding capability to a role.
	 */
	public function test_capability_exists_returns_true_after_adding_to_role(): void {
		$cap  = 'ai_agent_tool_test_cap_' . uniqid();
		$role = get_role( 'administrator' );
		$this->assertNotNull( $role );

		$role->add_cap( $cap, true );
		$this->assertTrue( ToolCapabilities::capability_exists( $cap ) );

		// Clean up.
		$role->remove_cap( $cap );
	}

	/**
	 * Test current_user_can falls back to manage_options when capability doesn't exist.
	 */
	public function test_current_user_can_falls_back_to_manage_options(): void {
		// Create a user with manage_options.
		$admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		// Use an ability ID whose capability has never been registered.
		$this->assertTrue( ToolCapabilities::current_user_can( 'ai-agent/nonexistent-tool-xyz' ) );

		// Create a subscriber (no manage_options).
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse( ToolCapabilities::current_user_can( 'ai-agent/nonexistent-tool-xyz' ) );
	}

	/**
	 * Test current_user_can uses the specific capability when it exists.
	 */
	public function test_current_user_can_uses_specific_cap_when_registered(): void {
		$ability_id = 'ai-agent/test-specific-tool-' . uniqid();
		$cap        = ToolCapabilities::cap_name( $ability_id );

		// Grant the capability to the editor role.
		$editor_role = get_role( 'editor' );
		$this->assertNotNull( $editor_role );
		$editor_role->add_cap( $cap, true );

		// Editor should now have access.
		$editor_id = $this->factory->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $editor_id );
		$this->assertTrue( ToolCapabilities::current_user_can( $ability_id ) );

		// Subscriber should not have access.
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );
		$this->assertFalse( ToolCapabilities::current_user_can( $ability_id ) );

		// Clean up.
		$editor_role->remove_cap( $cap );
	}

	/**
	 * Test the ai_agent_tool_capability filter overrides the capability name.
	 */
	public function test_filter_overrides_capability_name(): void {
		$ability_id   = 'ai-agent/memory-save';
		$override_cap = 'edit_posts';

		add_filter(
			'ai_agent_tool_capability',
			function ( string $cap, string $id ) use ( $ability_id, $override_cap ): string {
				if ( $id === $ability_id ) {
					return $override_cap;
				}
				return $cap;
			},
			10,
			2
		);

		// Grant edit_posts to editor role (it already has it by default).
		$editor_id = $this->factory->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $editor_id );

		// The capability 'edit_posts' exists in roles, so it should be used directly.
		$this->assertTrue( ToolCapabilities::current_user_can( $ability_id ) );

		remove_all_filters( 'ai_agent_tool_capability' );
	}

	/**
	 * Test register_capabilities adds capabilities to the administrator role.
	 */
	public function test_register_capabilities_adds_to_admin_role(): void {
		$test_ids = [
			'ai-agent/test-reg-tool-a-' . uniqid(),
			'ai-agent/test-reg-tool-b-' . uniqid(),
		];

		ToolCapabilities::register_capabilities( $test_ids );

		$admin_role = get_role( 'administrator' );
		$this->assertNotNull( $admin_role );

		foreach ( $test_ids as $id ) {
			$cap = ToolCapabilities::cap_name( $id );
			$this->assertArrayHasKey( $cap, $admin_role->capabilities );
			$this->assertTrue( $admin_role->capabilities[ $cap ] );

			// Clean up.
			$admin_role->remove_cap( $cap );
		}
	}

	/**
	 * Test all_ability_ids returns a non-empty array of strings.
	 */
	public function test_all_ability_ids_returns_non_empty_array(): void {
		$ids = ToolCapabilities::all_ability_ids();
		$this->assertIsArray( $ids );
		$this->assertNotEmpty( $ids );

		foreach ( $ids as $id ) {
			$this->assertIsString( $id );
			$this->assertStringStartsWith( 'ai-agent/', $id );
		}
	}

	/**
	 * Test all_ability_ids contains expected core abilities.
	 */
	public function test_all_ability_ids_contains_core_abilities(): void {
		$ids = ToolCapabilities::all_ability_ids();

		$expected = [
			'ai-agent/memory-save',
			'ai-agent/memory-list',
			'ai-agent/memory-delete',
			'ai-agent/db-query',
			'ai-agent/run-php',
			'ai-agent/file-read',
			'ai-agent/file-write',
			'ai-agent/navigate',
		];

		foreach ( $expected as $id ) {
			$this->assertContains( $id, $ids, "Expected ability ID '{$id}' not found in all_ability_ids()" );
		}
	}
}
