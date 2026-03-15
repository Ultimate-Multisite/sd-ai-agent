<?php
/**
 * Test case for ContentAbilities class.
 *
 * @package AiAgent
 * @subpackage Tests
 */

namespace AiAgent\Tests\Abilities;

use AiAgent\Abilities\ContentAbilities;
use WP_UnitTestCase;

/**
 * Test ContentAbilities handler methods.
 */
class ContentAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Test handle_content_analyze with no posts returns empty message.
	 */
	public function test_handle_content_analyze_no_posts() {
		$result = ContentAbilities::handle_content_analyze( [
			'post_type' => 'ai_agent_nonexistent_type',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'total_posts', $result );
		$this->assertSame( 0, $result['total_posts'] );
		$this->assertArrayHasKey( 'message', $result );
	}

	/**
	 * Test handle_content_analyze with published posts.
	 */
	public function test_handle_content_analyze_with_posts() {
		// Create test posts.
		$post1 = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => 'This is a test post with some content for analysis. It has multiple words.',
			'post_title'   => 'Test Post One',
		] );
		$post2 = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => 'Another test post with different content for the analysis test.',
			'post_title'   => 'Test Post Two',
		] );

		$result = ContentAbilities::handle_content_analyze( [] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'post_type', $result );
		$this->assertArrayHasKey( 'total_analyzed', $result );
		$this->assertArrayHasKey( 'avg_word_count', $result );
		$this->assertArrayHasKey( 'min_word_count', $result );
		$this->assertArrayHasKey( 'max_word_count', $result );
		$this->assertArrayHasKey( 'category_distribution', $result );
		$this->assertArrayHasKey( 'posts_without_featured_image', $result );
		$this->assertArrayHasKey( 'posts_without_meta_description', $result );
		$this->assertArrayHasKey( 'thin_content_count', $result );

		$this->assertGreaterThanOrEqual( 2, $result['total_analyzed'] );
		$this->assertSame( 'post', $result['post_type'] );
		$this->assertIsInt( $result['avg_word_count'] );
		$this->assertIsInt( $result['thin_content_count'] );

		wp_delete_post( $post1, true );
		wp_delete_post( $post2, true );
	}

	/**
	 * Test handle_content_analyze respects limit parameter.
	 */
	public function test_handle_content_analyze_limit() {
		// Create 5 posts.
		$post_ids = [];
		for ( $i = 0; $i < 5; $i++ ) {
			$post_ids[] = self::factory()->post->create( [
				'post_status'  => 'publish',
				'post_content' => "Post content number {$i} with enough words to count properly.",
			] );
		}

		$result = ContentAbilities::handle_content_analyze( [ 'limit' => 2 ] );

		$this->assertLessThanOrEqual( 2, $result['total_analyzed'] );

		foreach ( $post_ids as $id ) {
			wp_delete_post( $id, true );
		}
	}

	/**
	 * Test handle_content_analyze defaults to post type.
	 */
	public function test_handle_content_analyze_default_post_type() {
		$result = ContentAbilities::handle_content_analyze( [] );

		$this->assertSame( 'post', $result['post_type'] );
	}

	/**
	 * Test handle_content_analyze with custom post type.
	 */
	public function test_handle_content_analyze_custom_post_type() {
		$result = ContentAbilities::handle_content_analyze( [
			'post_type' => 'page',
		] );

		$this->assertSame( 'page', $result['post_type'] );
	}

	/**
	 * Test handle_performance_report returns expected structure.
	 */
	public function test_handle_performance_report_structure() {
		$result = ContentAbilities::handle_performance_report( [] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'period_days', $result );
		$this->assertArrayHasKey( 'posts_published', $result );
		$this->assertArrayHasKey( 'previous_period_published', $result );
		$this->assertArrayHasKey( 'avg_word_count', $result );
		$this->assertArrayHasKey( 'posts_by_category', $result );
		$this->assertArrayHasKey( 'posts_by_author', $result );
		$this->assertArrayHasKey( 'all_posts_by_status', $result );
		$this->assertArrayHasKey( 'drafts_pending_review', $result );
		$this->assertArrayHasKey( 'drafts_pending_count', $result );

		$this->assertIsInt( $result['period_days'] );
		$this->assertIsInt( $result['posts_published'] );
		$this->assertIsArray( $result['posts_by_category'] );
		$this->assertIsArray( $result['posts_by_author'] );
		$this->assertIsArray( $result['drafts_pending_review'] );
	}

	/**
	 * Test handle_performance_report defaults to 30 days.
	 */
	public function test_handle_performance_report_default_days() {
		$result = ContentAbilities::handle_performance_report( [] );

		$this->assertSame( 30, $result['period_days'] );
	}

	/**
	 * Test handle_performance_report respects days parameter.
	 */
	public function test_handle_performance_report_custom_days() {
		$result = ContentAbilities::handle_performance_report( [ 'days' => 7 ] );

		$this->assertSame( 7, $result['period_days'] );
	}

	/**
	 * Test handle_performance_report clamps days to valid range.
	 */
	public function test_handle_performance_report_clamps_days() {
		$result_min = ContentAbilities::handle_performance_report( [ 'days' => 0 ] );
		$this->assertSame( 1, $result_min['period_days'] );

		$result_max = ContentAbilities::handle_performance_report( [ 'days' => 999 ] );
		$this->assertSame( 365, $result_max['period_days'] );
	}

	/**
	 * Test handle_performance_report counts published posts in period.
	 */
	public function test_handle_performance_report_counts_recent_posts() {
		// Create a post published today.
		$post_id = self::factory()->post->create( [
			'post_status' => 'publish',
			'post_date'   => gmdate( 'Y-m-d H:i:s' ),
		] );

		$result = ContentAbilities::handle_performance_report( [ 'days' => 1 ] );

		$this->assertGreaterThanOrEqual( 1, $result['posts_published'] );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Test handle_performance_report includes draft posts in pending list.
	 */
	public function test_handle_performance_report_includes_drafts() {
		$draft_id = self::factory()->post->create( [
			'post_status' => 'draft',
			'post_title'  => 'Draft Post For Test',
		] );

		$result = ContentAbilities::handle_performance_report( [] );

		$this->assertGreaterThanOrEqual( 1, $result['drafts_pending_count'] );

		// Verify draft structure.
		if ( ! empty( $result['drafts_pending_review'] ) ) {
			$draft = $result['drafts_pending_review'][0];
			$this->assertArrayHasKey( 'id', $draft );
			$this->assertArrayHasKey( 'title', $draft );
			$this->assertArrayHasKey( 'status', $draft );
			$this->assertArrayHasKey( 'date', $draft );
		}

		wp_delete_post( $draft_id, true );
	}
}
