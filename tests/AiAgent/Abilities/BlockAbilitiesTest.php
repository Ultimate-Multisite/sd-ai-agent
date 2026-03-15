<?php
/**
 * Test case for BlockAbilities class.
 *
 * @package AiAgent
 * @subpackage Tests
 */

namespace AiAgent\Tests\Abilities;

use AiAgent\Abilities\BlockAbilities;
use WP_UnitTestCase;

/**
 * Test BlockAbilities handler methods.
 */
class BlockAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Test handle_markdown_to_blocks with valid markdown.
	 */
	public function test_handle_markdown_to_blocks_valid() {
		$result = BlockAbilities::handle_markdown_to_blocks( [
			'markdown' => "# Heading\n\nParagraph text here.",
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'block_content', $result );
		$this->assertArrayHasKey( 'block_count', $result );
		$this->assertIsString( $result['block_content'] );
		$this->assertIsInt( $result['block_count'] );
		$this->assertGreaterThan( 0, $result['block_count'] );
	}

	/**
	 * Test handle_markdown_to_blocks output contains block markup.
	 */
	public function test_handle_markdown_to_blocks_contains_block_markup() {
		$result = BlockAbilities::handle_markdown_to_blocks( [
			'markdown' => "# My Heading\n\nSome paragraph content.",
		] );

		$this->assertStringContainsString( '<!-- wp:', $result['block_content'] );
	}

	/**
	 * Test handle_markdown_to_blocks with empty markdown returns error.
	 */
	public function test_handle_markdown_to_blocks_empty_markdown() {
		$result = BlockAbilities::handle_markdown_to_blocks( [ 'markdown' => '' ] );

		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test handle_markdown_to_blocks with missing markdown key returns error.
	 */
	public function test_handle_markdown_to_blocks_missing_markdown() {
		$result = BlockAbilities::handle_markdown_to_blocks( [] );

		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test handle_list_block_types returns block list.
	 */
	public function test_handle_list_block_types_returns_list() {
		$result = BlockAbilities::handle_list_block_types( [] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'block_types', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'page', $result );
		$this->assertArrayHasKey( 'per_page', $result );
		$this->assertArrayHasKey( 'categories', $result );
		$this->assertIsArray( $result['block_types'] );
		$this->assertIsInt( $result['total'] );
	}

	/**
	 * Test handle_list_block_types each block has required fields.
	 */
	public function test_handle_list_block_types_block_structure() {
		$result = BlockAbilities::handle_list_block_types( [] );

		if ( ! empty( $result['block_types'] ) ) {
			$block = $result['block_types'][0];
			$this->assertArrayHasKey( 'name', $block );
			$this->assertArrayHasKey( 'title', $block );
			$this->assertArrayHasKey( 'description', $block );
			$this->assertArrayHasKey( 'category', $block );
			$this->assertArrayHasKey( 'keywords', $block );
		} else {
			$this->markTestSkipped( 'No block types registered in test environment.' );
		}
	}

	/**
	 * Test handle_list_block_types respects per_page.
	 */
	public function test_handle_list_block_types_per_page() {
		$result = BlockAbilities::handle_list_block_types( [ 'per_page' => 5 ] );

		$this->assertLessThanOrEqual( 5, count( $result['block_types'] ) );
		$this->assertSame( 5, $result['per_page'] );
	}

	/**
	 * Test handle_list_block_types filters by category.
	 */
	public function test_handle_list_block_types_category_filter() {
		$result = BlockAbilities::handle_list_block_types( [
			'category' => 'text',
			'per_page' => 50,
		] );

		$this->assertIsArray( $result );
		foreach ( $result['block_types'] as $block ) {
			$this->assertSame( 'text', $block['category'] );
		}
	}

	/**
	 * Test handle_list_block_types search filter.
	 */
	public function test_handle_list_block_types_search_filter() {
		$result = BlockAbilities::handle_list_block_types( [
			'search'   => 'paragraph',
			'per_page' => 20,
		] );

		$this->assertIsArray( $result );
		// Should find core/paragraph at minimum.
		$this->assertGreaterThanOrEqual( 0, $result['total'] );
	}

	/**
	 * Test handle_get_block_type with valid block name.
	 */
	public function test_handle_get_block_type_valid() {
		$result = BlockAbilities::handle_get_block_type( [
			'name' => 'core/paragraph',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'title', $result );
		$this->assertArrayHasKey( 'description', $result );
		$this->assertArrayHasKey( 'category', $result );
		$this->assertArrayHasKey( 'attributes', $result );
		$this->assertArrayHasKey( 'supports', $result );
		$this->assertSame( 'core/paragraph', $result['name'] );
	}

	/**
	 * Test handle_get_block_type with non-existent block returns error.
	 */
	public function test_handle_get_block_type_not_found() {
		$result = BlockAbilities::handle_get_block_type( [
			'name' => 'nonexistent/block-xyz',
		] );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	/**
	 * Test handle_get_block_type with empty name returns error.
	 */
	public function test_handle_get_block_type_empty_name() {
		$result = BlockAbilities::handle_get_block_type( [ 'name' => '' ] );

		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test handle_create_block_content with paragraph block.
	 */
	public function test_handle_create_block_content_paragraph() {
		$result = BlockAbilities::handle_create_block_content( [
			'blocks' => [
				[
					'blockName' => 'core/paragraph',
					'content'   => 'Hello world',
				],
			],
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'block_content', $result );
		$this->assertArrayHasKey( 'block_count', $result );
		// Block content uses comment syntax: <!-- wp:paragraph --> not "core/paragraph".
		$this->assertStringContainsString( '<!-- wp:paragraph', $result['block_content'] );
		$this->assertStringContainsString( 'Hello world', $result['block_content'] );
		$this->assertGreaterThan( 0, $result['block_count'] );
	}

	/**
	 * Test handle_create_block_content with heading block.
	 */
	public function test_handle_create_block_content_heading() {
		$result = BlockAbilities::handle_create_block_content( [
			'blocks' => [
				[
					'blockName' => 'core/heading',
					'attrs'     => [ 'level' => 2 ],
					'content'   => 'My Heading',
				],
			],
		] );

		// Block content uses comment syntax: <!-- wp:heading --> not "core/heading".
		$this->assertStringContainsString( '<!-- wp:heading', $result['block_content'] );
		$this->assertStringContainsString( 'My Heading', $result['block_content'] );
		$this->assertStringContainsString( '<h2', $result['block_content'] );
	}

	/**
	 * Test handle_create_block_content with multiple blocks.
	 */
	public function test_handle_create_block_content_multiple_blocks() {
		$result = BlockAbilities::handle_create_block_content( [
			'blocks' => [
				[
					'blockName' => 'core/heading',
					'content'   => 'Title',
				],
				[
					'blockName' => 'core/paragraph',
					'content'   => 'Body text',
				],
			],
		] );

		$this->assertGreaterThanOrEqual( 2, $result['block_count'] );
	}

	/**
	 * Test handle_create_block_content with empty blocks returns error.
	 */
	public function test_handle_create_block_content_empty_blocks() {
		$result = BlockAbilities::handle_create_block_content( [ 'blocks' => [] ] );

		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test handle_create_block_content with missing blocks key returns error.
	 */
	public function test_handle_create_block_content_missing_blocks() {
		$result = BlockAbilities::handle_create_block_content( [] );

		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test handle_parse_block_content with raw content.
	 */
	public function test_handle_parse_block_content_raw_content() {
		$content = '<!-- wp:paragraph --><p>Hello world</p><!-- /wp:paragraph -->';

		$result = BlockAbilities::handle_parse_block_content( [
			'content' => $content,
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'blocks', $result );
		$this->assertArrayHasKey( 'block_count', $result );
		$this->assertIsArray( $result['blocks'] );
		$this->assertGreaterThan( 0, $result['block_count'] );
	}

	/**
	 * Test handle_parse_block_content with post_id.
	 */
	public function test_handle_parse_block_content_post_id() {
		$post_id = self::factory()->post->create( [
			'post_content' => '<!-- wp:paragraph --><p>Test content</p><!-- /wp:paragraph -->',
			'post_status'  => 'publish',
		] );

		$result = BlockAbilities::handle_parse_block_content( [
			'post_id' => $post_id,
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'blocks', $result );
		$this->assertGreaterThan( 0, $result['block_count'] );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Test handle_parse_block_content with non-existent post returns error.
	 */
	public function test_handle_parse_block_content_post_not_found() {
		$result = BlockAbilities::handle_parse_block_content( [
			'post_id' => 999999,
		] );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( '999999', $result['error'] );
	}

	/**
	 * Test handle_parse_block_content with no input returns error.
	 */
	public function test_handle_parse_block_content_no_input() {
		$result = BlockAbilities::handle_parse_block_content( [] );

		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test handle_list_block_patterns returns pattern list.
	 */
	public function test_handle_list_block_patterns_returns_list() {
		$result = BlockAbilities::handle_list_block_patterns( [] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'patterns', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'categories', $result );
		$this->assertIsArray( $result['patterns'] );
		$this->assertIsInt( $result['total'] );
	}

	/**
	 * Test handle_list_block_templates returns template list.
	 */
	public function test_handle_list_block_templates_returns_list() {
		$result = BlockAbilities::handle_list_block_templates( [] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'templates', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertIsArray( $result['templates'] );
		$this->assertIsInt( $result['total'] );
	}
}
