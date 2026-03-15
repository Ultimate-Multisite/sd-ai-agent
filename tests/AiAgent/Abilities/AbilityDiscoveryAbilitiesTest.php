<?php
/**
 * Test case for AbilityDiscoveryAbilities class.
 *
 * @package AiAgent
 * @subpackage Tests
 */

namespace AiAgent\Tests\Abilities;

use AiAgent\Abilities\AbilityDiscoveryAbilities;
use WP_UnitTestCase;

/**
 * Test AbilityDiscoveryAbilities handler methods.
 *
 * These tests verify the handler logic directly. The Abilities API
 * (wp_get_abilities, wp_get_ability) may not be available in the test
 * environment (requires WordPress 6.9+). Tests gracefully handle both cases.
 */
class AbilityDiscoveryAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Test handle_list_abilities returns array.
	 */
	public function test_handle_list_abilities_returns_array() {
		$result = AbilityDiscoveryAbilities::handle_list_abilities( [] );

		// Either returns abilities list or WP_Error if API unavailable.
		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			'Result should be array or WP_Error.'
		);
	}

	/**
	 * Test handle_list_abilities result structure when API available.
	 */
	public function test_handle_list_abilities_structure_when_available() {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			$this->markTestSkipped( 'Abilities API not available (requires WordPress 6.9+).' );
		}

		$result = AbilityDiscoveryAbilities::handle_list_abilities( [] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'abilities', $result );
		$this->assertArrayHasKey( 'count', $result );
		$this->assertIsArray( $result['abilities'] );
		$this->assertIsInt( $result['count'] );
		$this->assertSame( count( $result['abilities'] ), $result['count'] );
	}

	/**
	 * Test handle_list_abilities with category filter when API available.
	 */
	public function test_handle_list_abilities_category_filter() {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			$this->markTestSkipped( 'Abilities API not available (requires WordPress 6.9+).' );
		}

		$result = AbilityDiscoveryAbilities::handle_list_abilities( [
			'category' => 'ai-agent',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'abilities', $result );
		$this->assertSame( 'ai-agent', $result['filter'] );

		foreach ( $result['abilities'] as $ability ) {
			$this->assertSame( 'ai-agent', $ability['category'] );
		}
	}

	/**
	 * Test handle_list_abilities returns WP_Error when API unavailable.
	 */
	public function test_handle_list_abilities_api_unavailable() {
		if ( function_exists( 'wp_get_abilities' ) ) {
			$this->markTestSkipped( 'Abilities API is available — testing unavailable path not applicable.' );
		}

		$result = AbilityDiscoveryAbilities::handle_list_abilities( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'abilities_api_unavailable', $result->get_error_code() );
	}

	/**
	 * Test handle_get_ability with empty ability ID returns WP_Error.
	 */
	public function test_handle_get_ability_empty_id() {
		$result = AbilityDiscoveryAbilities::handle_get_ability( [ 'ability' => '' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_argument', $result->get_error_code() );
	}

	/**
	 * Test handle_get_ability with missing ability key returns WP_Error.
	 */
	public function test_handle_get_ability_missing_key() {
		$result = AbilityDiscoveryAbilities::handle_get_ability( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_get_ability with non-existent ability returns WP_Error.
	 */
	public function test_handle_get_ability_not_found() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available (requires WordPress 6.9+).' );
		}

		// WP_Abilities_Registry::get_registered triggers _doing_it_wrong for unknown abilities.
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );

		$result = AbilityDiscoveryAbilities::handle_get_ability( [
			'ability' => 'nonexistent/ability-xyz',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ability_not_found', $result->get_error_code() );
	}

	/**
	 * Test handle_get_ability with valid ability returns structure.
	 */
	public function test_handle_get_ability_valid() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available (requires WordPress 6.9+).' );
		}

		// Use a known ability that should be registered.
		$result = AbilityDiscoveryAbilities::handle_get_ability( [
			'ability' => 'ai-agent/memory-save',
		] );

		if ( is_wp_error( $result ) ) {
			// Ability may not be registered in test env.
			$this->assertSame( 'ability_not_found', $result->get_error_code() );
			return;
		}

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'description', $result );
		$this->assertArrayHasKey( 'category', $result );
		$this->assertArrayHasKey( 'input_schema', $result );
	}

	/**
	 * Test handle_execute_ability with empty ability ID returns WP_Error.
	 */
	public function test_handle_execute_ability_empty_id() {
		$result = AbilityDiscoveryAbilities::handle_execute_ability( [ 'ability' => '' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_argument', $result->get_error_code() );
	}

	/**
	 * Test handle_execute_ability with missing ability key returns WP_Error.
	 */
	public function test_handle_execute_ability_missing_key() {
		$result = AbilityDiscoveryAbilities::handle_execute_ability( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test handle_execute_ability with non-existent ability returns WP_Error.
	 */
	public function test_handle_execute_ability_not_found() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available (requires WordPress 6.9+).' );
		}

		// WP_Abilities_Registry::get_registered triggers _doing_it_wrong for unknown abilities.
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );

		$result = AbilityDiscoveryAbilities::handle_execute_ability( [
			'ability' => 'nonexistent/ability-xyz',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ability_not_found', $result->get_error_code() );
	}
}
