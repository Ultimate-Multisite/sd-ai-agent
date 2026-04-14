<?php

declare(strict_types=1);
/**
 * Test case for PluginUpdater class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\PluginBuilder;

use GratisAiAgent\PluginBuilder\PluginUpdater;
use WP_UnitTestCase;

/**
 * Tests for the PluginUpdater sandboxed update flow.
 *
 * These tests operate on real wp-content subdirectories created under the
 * wp-env test environment. Each test cleans up after itself.
 *
 * @since 1.5.0
 */
class PluginUpdaterTest extends WP_UnitTestCase {

	/**
	 * Slug for the dummy plugin used in tests.
	 *
	 * @var string
	 */
	private string $slug = 'gratis-ai-test-updater-plugin';

	/**
	 * Absolute path to the dummy plugin directory.
	 *
	 * @var string
	 */
	private string $plugin_dir;

	/**
	 * Absolute path to the gratis-ai-backups directory.
	 *
	 * @var string
	 */
	private string $backup_base;

	/**
	 * Absolute path to the gratis-ai-staging directory.
	 *
	 * @var string
	 */
	private string $staging_base;

	/**
	 * Subject under test.
	 *
	 * @var PluginUpdater
	 */
	private PluginUpdater $updater;

	/**
	 * Minimal valid PHP plugin file content.
	 *
	 * @var string
	 */
	private string $plugin_php = <<<'PHP'
<?php
/**
 * Plugin Name: Gratis AI Test Updater Plugin
 * Description: Dummy plugin for PluginUpdater tests.
 * Version: 1.0.0
 */
// intentionally empty
PHP;

	/**
	 * Set up: create a minimal dummy plugin on disk.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->plugin_dir   = WP_CONTENT_DIR . '/plugins/' . $this->slug . '/';
		$this->backup_base  = WP_CONTENT_DIR . '/gratis-ai-backups/';
		$this->staging_base = WP_CONTENT_DIR . '/gratis-ai-staging/';
		$this->updater      = new PluginUpdater();

		// Create dummy plugin directory with a minimal plugin file.
		wp_mkdir_p( $this->plugin_dir );
		file_put_contents( $this->plugin_dir . $this->slug . '.php', $this->plugin_php );
	}

	/**
	 * Tear down: remove test artefacts.
	 */
	public function tearDown(): void {
		$this->remove_directory( $this->plugin_dir );
		$this->remove_directory( $this->staging_base . $this->slug . '/' );

		// Remove any backup dirs created for this slug.
		if ( is_dir( $this->backup_base ) ) {
			$entries = scandir( $this->backup_base );
			if ( false !== $entries ) {
				foreach ( $entries as $entry ) {
					if ( str_starts_with( $entry, $this->slug . '-' ) ) {
						$this->remove_directory( $this->backup_base . $entry . '/' );
					}
				}
			}
		}

		parent::tearDown();
	}

	// ── backup() ─────────────────────────────────────────────────────────

	/**
	 * backup() returns a backup directory path when plugin exists.
	 */
	public function test_backup_returns_path_for_existing_plugin(): void {
		$result = $this->updater->backup( $this->slug );

		$this->assertIsString( $result );
		$this->assertTrue( is_dir( $result ), "Backup directory should exist: $result" );
		$this->assertStringContainsString( 'gratis-ai-backups', $result );
		$this->assertStringContainsString( $this->slug, $result );
	}

	/**
	 * backup() creates a copy that contains the original plugin file.
	 */
	public function test_backup_copies_plugin_files(): void {
		$result = $this->updater->backup( $this->slug );

		$this->assertIsString( $result );
		$backup_file = $result . $this->slug . '.php';
		$this->assertFileExists( $backup_file );
	}

	/**
	 * backup() returns WP_Error when plugin directory does not exist.
	 */
	public function test_backup_returns_wp_error_for_missing_plugin(): void {
		$result = $this->updater->backup( 'nonexistent-plugin-slug-xyz' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gratis_ai_agent_plugin_not_found', $result->get_error_code() );
	}

	/**
	 * backup() returns WP_Error for empty slug.
	 */
	public function test_backup_returns_wp_error_for_empty_slug(): void {
		$result = $this->updater->backup( '' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gratis_ai_agent_invalid_slug', $result->get_error_code() );
	}

	// ── stage() ──────────────────────────────────────────────────────────

	/**
	 * stage() creates the staging directory and writes modified files.
	 */
	public function test_stage_creates_staging_directory_with_modified_files(): void {
		$modified = [
			$this->slug . '.php' => $this->plugin_php . "\n// v2",
			'lib/helper.php'     => '<?php // helper',
		];

		$result = $this->updater->stage( $this->slug, $modified );

		$this->assertIsString( $result );
		$this->assertTrue( is_dir( $result ), "Staging directory should exist: $result" );
		$this->assertFileExists( $result . $this->slug . '.php' );
		$this->assertFileExists( $result . 'lib/helper.php' );
		$this->assertStringContainsString( '// v2', file_get_contents( $result . $this->slug . '.php' ) );
	}

	/**
	 * stage() includes existing plugin files not in the modified set.
	 */
	public function test_stage_includes_unmodified_existing_files(): void {
		// Add an extra file to the live plugin.
		file_put_contents( $this->plugin_dir . 'readme.txt', 'Readme text' );

		$modified = [
			$this->slug . '.php' => $this->plugin_php . "\n// updated",
		];

		$result = $this->updater->stage( $this->slug, $modified );

		$this->assertIsString( $result );
		// Unmodified file should also be present.
		$this->assertFileExists( $result . 'readme.txt' );
	}

	/**
	 * stage() returns WP_Error for empty slug.
	 */
	public function test_stage_returns_wp_error_for_empty_slug(): void {
		$result = $this->updater->stage( '', [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gratis_ai_agent_invalid_slug', $result->get_error_code() );
	}

	// ── test_staged() ────────────────────────────────────────────────────

	/**
	 * test_staged() returns a passing result for valid PHP.
	 */
	public function test_test_staged_passes_valid_php(): void {
		$staging_dir = $this->staging_base . $this->slug . '/';
		wp_mkdir_p( $staging_dir );
		file_put_contents( $staging_dir . $this->slug . '.php', $this->plugin_php );

		$result = $this->updater->test_staged( $this->slug, $staging_dir );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'passed', $result );
		// Layer 1 (syntax check) should pass for valid PHP.
		$this->assertTrue( $result['layer1_passed'], 'Layer 1 should pass for valid PHP' );
	}

	/**
	 * test_staged() fails for PHP with syntax errors.
	 */
	public function test_test_staged_fails_invalid_php(): void {
		$staging_dir = $this->staging_base . $this->slug . '/';
		wp_mkdir_p( $staging_dir );
		file_put_contents(
			$staging_dir . $this->slug . '.php',
			"<?php\n/**\n * Plugin Name: Bad Plugin\n */\n\$x = ;" // syntax error
		);

		$result = $this->updater->test_staged( $this->slug, $staging_dir );

		$this->assertIsArray( $result );
		$this->assertFalse( $result['passed'], 'Should fail for invalid PHP syntax' );
		$this->assertNotEmpty( $result['errors'] );
	}

	/**
	 * test_staged() returns failure when staging dir is missing.
	 */
	public function test_test_staged_returns_failure_for_missing_dir(): void {
		$result = $this->updater->test_staged( $this->slug, '/tmp/nonexistent-gratis-staging-xyz/' );

		$this->assertIsArray( $result );
		$this->assertFalse( $result['passed'] );
	}

	// ── rollback() ───────────────────────────────────────────────────────

	/**
	 * rollback() restores plugin files from a backup directory.
	 */
	public function test_rollback_restores_from_backup(): void {
		// Create a backup containing a specific version marker.
		$backup_dir = $this->backup_base . $this->slug . '-backup-test/';
		wp_mkdir_p( $backup_dir );
		file_put_contents( $backup_dir . $this->slug . '.php', $this->plugin_php . "\n// backup-version" );

		// Remove the live plugin dir.
		$this->remove_directory( $this->plugin_dir );

		$result = $this->updater->rollback( $this->slug, $backup_dir );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['restored'] );
		$this->assertFileExists( $this->plugin_dir . $this->slug . '.php' );
		$this->assertStringContainsString(
			'// backup-version',
			file_get_contents( $this->plugin_dir . $this->slug . '.php' )
		);

		// Cleanup extra backup dir.
		$this->remove_directory( $backup_dir );
	}

	/**
	 * rollback() returns WP_Error when backup directory does not exist.
	 */
	public function test_rollback_returns_wp_error_for_missing_backup(): void {
		$result = $this->updater->rollback( $this->slug, '/tmp/nonexistent-gratis-backup-xyz/' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gratis_ai_agent_backup_not_found', $result->get_error_code() );
	}

	// ── cleanup_old_backups() ─────────────────────────────────────────────

	/**
	 * cleanup_old_backups() removes directories older than the threshold.
	 */
	public function test_cleanup_old_backups_removes_old_entries(): void {
		wp_mkdir_p( $this->backup_base );

		$old_dir  = $this->backup_base . $this->slug . '-2020-01-01-120000/';
		$new_dir  = $this->backup_base . $this->slug . '-' . gmdate( 'Y-m-d-His' ) . '/';

		wp_mkdir_p( $old_dir );
		wp_mkdir_p( $new_dir );

		// Fake an old mtime for the old backup.
		touch( $old_dir, time() - ( 30 * DAY_IN_SECONDS ) );

		$removed = $this->updater->cleanup_old_backups( 7 );

		$this->assertGreaterThanOrEqual( 1, $removed );
		$this->assertDirectoryDoesNotExist( $old_dir );
		// Most recent backup must be preserved.
		$this->assertDirectoryExists( $new_dir );

		// Cleanup.
		$this->remove_directory( $new_dir );
	}

	/**
	 * cleanup_old_backups() preserves the most recent backup even if older than threshold.
	 */
	public function test_cleanup_old_backups_preserves_most_recent(): void {
		wp_mkdir_p( $this->backup_base );

		// Single backup dir that is 30 days old.
		$only_backup = $this->backup_base . $this->slug . '-2020-06-01-120000/';
		wp_mkdir_p( $only_backup );
		touch( $only_backup, time() - ( 30 * DAY_IN_SECONDS ) );

		$removed = $this->updater->cleanup_old_backups( 7 );

		// The only (most recent) backup should NOT be removed.
		$this->assertSame( 0, $removed );
		$this->assertDirectoryExists( $only_backup );

		// Cleanup.
		$this->remove_directory( $only_backup );
	}

	/**
	 * cleanup_old_backups() returns 0 when backup base does not exist.
	 */
	public function test_cleanup_old_backups_returns_zero_when_no_base_dir(): void {
		// Remove base dir if it exists.
		if ( is_dir( $this->backup_base ) ) {
			$this->remove_directory( $this->backup_base );
		}

		$removed = $this->updater->cleanup_old_backups();

		$this->assertSame( 0, $removed );
	}

	// ── update() ─────────────────────────────────────────────────────────

	/**
	 * update() returns WP_Error for empty slug.
	 */
	public function test_update_returns_wp_error_for_empty_slug(): void {
		$result = $this->updater->update( '', [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gratis_ai_agent_invalid_slug', $result->get_error_code() );
	}

	/**
	 * update() returns WP_Error when the plugin directory does not exist.
	 */
	public function test_update_returns_wp_error_when_plugin_missing(): void {
		$result = $this->updater->update( 'totally-nonexistent-plugin-xyz', [ 'main.php' => '<?php' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gratis_ai_agent_plugin_not_found', $result->get_error_code() );
	}

	/**
	 * update() returns WP_Error when staged files fail sandbox check.
	 */
	public function test_update_returns_wp_error_when_sandbox_fails(): void {
		$bad_php = "<?php\n/**\n * Plugin Name: Bad\n */\n\$x = ;" ;

		$result = $this->updater->update(
			$this->slug,
			[ $this->slug . '.php' => $bad_php ]
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gratis_ai_agent_sandbox_failed', $result->get_error_code() );
	}

	// ── Private helpers ───────────────────────────────────────────────────

	/**
	 * Remove a directory recursively.
	 *
	 * @param string $dir Path to remove.
	 * @return void
	 */
	private function remove_directory( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$fs = new \WP_Filesystem_Direct( [] );
		$fs->rmdir( $dir, true );
	}
}
