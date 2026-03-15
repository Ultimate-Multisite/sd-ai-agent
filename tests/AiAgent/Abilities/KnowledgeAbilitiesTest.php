<?php
/**
 * Test case for KnowledgeAbilities class.
 *
 * @package AiAgent
 * @subpackage Tests
 */

namespace AiAgent\Tests\Abilities;

use AiAgent\Abilities\KnowledgeAbilities;
use WP_UnitTestCase;

/**
 * Test KnowledgeAbilities handler methods.
 */
class KnowledgeAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Test handle_knowledge_search with empty query returns error.
	 */
	public function test_handle_knowledge_search_empty_query() {
		$result = KnowledgeAbilities::handle_knowledge_search( [ 'query' => '' ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'required', $result['error'] );
	}

	/**
	 * Test handle_knowledge_search with missing query returns error.
	 */
	public function test_handle_knowledge_search_missing_query() {
		$result = KnowledgeAbilities::handle_knowledge_search( [] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test handle_knowledge_search with no results returns message.
	 */
	public function test_handle_knowledge_search_no_results() {
		// Search for something that won't exist in an empty knowledge base.
		$result = KnowledgeAbilities::handle_knowledge_search( [
			'query' => 'xyzzy_nonexistent_query_' . uniqid(),
		] );

		$this->assertIsArray( $result );
		// Either returns a message (no results) or results array.
		$this->assertTrue(
			isset( $result['message'] ) || isset( $result['results'] ),
			'Result should have either message or results key.'
		);
	}

	/**
	 * Test handle_knowledge_search result structure when results exist.
	 *
	 * This test verifies the structure of the response when results are returned.
	 * Since the knowledge base may be empty in test environment, we test the
	 * no-results path and verify the structure contract.
	 */
	public function test_handle_knowledge_search_result_structure_contract() {
		$result = KnowledgeAbilities::handle_knowledge_search( [
			'query' => 'test query',
		] );

		$this->assertIsArray( $result );

		if ( isset( $result['results'] ) ) {
			// Has results — verify structure.
			$this->assertIsArray( $result['results'] );
			$this->assertArrayHasKey( 'count', $result );
			$this->assertIsInt( $result['count'] );

			if ( ! empty( $result['results'] ) ) {
				$item = $result['results'][0];
				$this->assertArrayHasKey( 'text', $item );
				$this->assertArrayHasKey( 'source', $item );
				$this->assertArrayHasKey( 'collection', $item );
			}
		} else {
			// No results — verify message.
			$this->assertArrayHasKey( 'message', $result );
			$this->assertIsString( $result['message'] );
		}
	}

	/**
	 * Test handle_knowledge_search with collection filter.
	 */
	public function test_handle_knowledge_search_with_collection() {
		$result = KnowledgeAbilities::handle_knowledge_search( [
			'query'      => 'test',
			'collection' => 'nonexistent-collection',
		] );

		$this->assertIsArray( $result );
		// Should return message or results without error.
		$this->assertFalse( isset( $result['error'] ) );
	}
}
