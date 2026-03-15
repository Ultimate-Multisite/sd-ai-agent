<?php
/**
 * Test case for SeoAbilities class.
 *
 * @package AiAgent
 * @subpackage Tests
 */

namespace AiAgent\Tests\Abilities;

use AiAgent\Abilities\SeoAbilities;
use WP_UnitTestCase;

/**
 * Test SeoAbilities handler methods.
 */
class SeoAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Test handle_audit_url with empty URL returns error.
	 */
	public function test_handle_audit_url_empty_url() {
		$result = SeoAbilities::handle_audit_url( [ 'url' => '' ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test handle_audit_url with missing URL returns error.
	 */
	public function test_handle_audit_url_missing_url() {
		$result = SeoAbilities::handle_audit_url( [] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test handle_analyze_content with valid post.
	 */
	public function test_handle_analyze_content_valid_post() {
		$post_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_title'   => 'SEO Test Post Title That Is Long Enough',
			'post_content' => str_repeat( 'This is test content for SEO analysis. ', 20 ),
		] );

		$result = SeoAbilities::handle_analyze_content( [
			'post_id' => $post_id,
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'post_id', $result );
		$this->assertArrayHasKey( 'title', $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'word_count', $result );
		$this->assertArrayHasKey( 'title_length', $result );
		$this->assertArrayHasKey( 'heading_structure', $result );
		$this->assertArrayHasKey( 'internal_links', $result );
		$this->assertArrayHasKey( 'external_links', $result );
		$this->assertArrayHasKey( 'has_featured_image', $result );
		$this->assertArrayHasKey( 'recommendations', $result );
		$this->assertArrayHasKey( 'recommendation_count', $result );

		$this->assertSame( $post_id, $result['post_id'] );
		$this->assertIsInt( $result['word_count'] );
		$this->assertIsArray( $result['recommendations'] );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Test handle_analyze_content with focus keyword.
	 */
	public function test_handle_analyze_content_with_focus_keyword() {
		$post_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_title'   => 'WordPress SEO Guide for Beginners',
			'post_content' => str_repeat( 'WordPress SEO is important for your site. ', 15 ),
		] );

		$result = SeoAbilities::handle_analyze_content( [
			'post_id'       => $post_id,
			'focus_keyword' => 'WordPress SEO',
		] );

		$this->assertArrayHasKey( 'focus_keyword', $result );
		$this->assertArrayHasKey( 'keyword_count', $result );
		$this->assertArrayHasKey( 'keyword_density', $result );
		$this->assertArrayHasKey( 'keyword_in_title', $result );
		$this->assertSame( 'WordPress SEO', $result['focus_keyword'] );
		$this->assertTrue( $result['keyword_in_title'] );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Test handle_analyze_content with non-existent post returns error.
	 */
	public function test_handle_analyze_content_post_not_found() {
		$result = SeoAbilities::handle_analyze_content( [
			'post_id' => 999999,
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( '999999', $result['error'] );
	}

	/**
	 * Test handle_analyze_content with zero post_id returns error.
	 */
	public function test_handle_analyze_content_zero_post_id() {
		$result = SeoAbilities::handle_analyze_content( [
			'post_id' => 0,
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test handle_analyze_content with missing post_id returns error.
	 */
	public function test_handle_analyze_content_missing_post_id() {
		$result = SeoAbilities::handle_analyze_content( [] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test handle_analyze_content thin content recommendation.
	 */
	public function test_handle_analyze_content_thin_content_recommendation() {
		$post_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_title'   => 'Short',
			'post_content' => 'Very short content.',
		] );

		$result = SeoAbilities::handle_analyze_content( [
			'post_id' => $post_id,
		] );

		// Should recommend more content.
		$recommendations = implode( ' ', $result['recommendations'] );
		$this->assertStringContainsString( '300', $recommendations );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Test handle_analyze_content word count is accurate.
	 */
	public function test_handle_analyze_content_word_count() {
		$post_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => 'one two three four five',
		] );

		$result = SeoAbilities::handle_analyze_content( [
			'post_id' => $post_id,
		] );

		$this->assertSame( 5, $result['word_count'] );

		wp_delete_post( $post_id, true );
	}
}
