<?php
/**
 * Test case for SkillAbilities class.
 *
 * @package AiAgent
 * @subpackage Tests
 */

namespace AiAgent\Tests\Abilities;

use AiAgent\Abilities\SkillAbilities;
use AiAgent\Models\Skill;
use WP_UnitTestCase;

/**
 * Test SkillAbilities handler methods.
 */
class SkillAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Test handle_skill_list with no enabled skills returns message.
	 *
	 * Note: Built-in skills cannot be deleted (Skill::delete returns 'builtin').
	 * This test disables all skills and verifies the empty-list message.
	 */
	public function test_handle_skill_list_empty() {
		// Disable all skills so handle_skill_list (which uses get_all(true)) returns empty.
		$all = Skill::get_all();
		foreach ( $all as $skill ) {
			Skill::update( (int) $skill->id, [ 'enabled' => 0 ] );
		}

		$result = SkillAbilities::handle_skill_list();

		// Re-enable all skills to avoid affecting other tests.
		foreach ( $all as $skill ) {
			Skill::update( (int) $skill->id, [ 'enabled' => (int) $skill->enabled ] );
		}

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertStringContainsString( 'No skills', $result['message'] );
	}

	/**
	 * Test handle_skill_list with skills returns list.
	 */
	public function test_handle_skill_list_with_skills() {
		$skill_id = Skill::create( [
			'name'        => 'Test Skill',
			'slug'        => 'test-skill-' . uniqid(),
			'description' => 'A test skill',
			'content'     => 'Skill content here',
			'enabled'     => 1,
		] );

		$result = SkillAbilities::handle_skill_list();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'skills', $result );
		$this->assertIsArray( $result['skills'] );
		$this->assertGreaterThanOrEqual( 1, count( $result['skills'] ) );

		// Each skill should have slug, name, description.
		$skill = $result['skills'][0];
		$this->assertArrayHasKey( 'slug', $skill );
		$this->assertArrayHasKey( 'name', $skill );
		$this->assertArrayHasKey( 'description', $skill );

		Skill::delete( $skill_id );
	}

	/**
	 * Test handle_skill_load with valid slug.
	 */
	public function test_handle_skill_load_valid() {
		$slug     = 'test-skill-load-' . uniqid();
		$skill_id = Skill::create( [
			'name'        => 'Load Test Skill',
			'slug'        => $slug,
			'description' => 'A skill for load testing',
			'content'     => 'This is the skill content.',
			'enabled'     => 1,
		] );

		$result = SkillAbilities::handle_skill_load( [ 'slug' => $slug ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'slug', $result );
		$this->assertArrayHasKey( 'content', $result );
		$this->assertSame( $slug, $result['slug'] );
		$this->assertSame( 'This is the skill content.', $result['content'] );

		Skill::delete( $skill_id );
	}

	/**
	 * Test handle_skill_load with non-existent slug returns error.
	 */
	public function test_handle_skill_load_not_found() {
		$result = SkillAbilities::handle_skill_load( [
			'slug' => 'nonexistent-skill-xyz-' . uniqid(),
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	/**
	 * Test handle_skill_load with disabled skill returns error.
	 */
	public function test_handle_skill_load_disabled() {
		$slug     = 'disabled-skill-' . uniqid();
		$skill_id = Skill::create( [
			'name'        => 'Disabled Skill',
			'slug'        => $slug,
			'description' => 'A disabled skill',
			'content'     => 'Content',
			'enabled'     => 0,
		] );

		$result = SkillAbilities::handle_skill_load( [ 'slug' => $slug ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'disabled', $result['error'] );

		Skill::delete( $skill_id );
	}

	/**
	 * Test handle_skill_load with empty slug returns error.
	 */
	public function test_handle_skill_load_empty_slug() {
		$result = SkillAbilities::handle_skill_load( [ 'slug' => '' ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'required', $result['error'] );
	}

	/**
	 * Test handle_skill_load with missing slug key returns error.
	 */
	public function test_handle_skill_load_missing_slug() {
		$result = SkillAbilities::handle_skill_load( [] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}
}
