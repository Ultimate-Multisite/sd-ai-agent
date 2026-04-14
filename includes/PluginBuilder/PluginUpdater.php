<?php

declare(strict_types=1);
/**
 * Plugin Updater — sandboxed live updates for AI-generated plugins.
 *
 * Flow: backup → stage → test → swap → verify → rollback on failure.
 *
 * Directories used (relative to wp-content/):
 *   gratis-ai-backups/{slug}-{timestamp}/  — pre-update snapshots
 *   gratis-ai-staging/{slug}/              — staged new files
 *
 * @package GratisAiAgent\PluginBuilder
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\PluginBuilder;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PluginUpdater — sandboxed updates for running AI-generated plugins.
 *
 * @since 1.5.0
 */
class PluginUpdater {

	/**
	 * Backup an installed plugin directory.
	 *
	 * Copies wp-content/plugins/{slug}/ to
	 * wp-content/gratis-ai-backups/{slug}-{timestamp}/.
	 *
	 * @param string $slug Plugin slug (directory name under wp-content/plugins/).
	 * @return string Absolute path to the created backup directory on success.
	 *                Returns WP_Error on failure.
	 */
	public function backup( string $slug ): string|WP_Error {
		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			return new WP_Error(
				'gratis_ai_agent_invalid_slug',
				__( 'Plugin slug must not be empty.', 'gratis-ai-agent' )
			);
		}

		$plugin_dir = $this->plugin_dir( $slug );
		if ( ! is_dir( $plugin_dir ) ) {
			return new WP_Error(
				'gratis_ai_agent_plugin_not_found',
				/* translators: %s: plugin directory */
				sprintf( __( 'Plugin directory not found: %s', 'gratis-ai-agent' ), $plugin_dir )
			);
		}

		$backup_base = WP_CONTENT_DIR . '/gratis-ai-backups/';
		$backup_dir  = $backup_base . $slug . '-' . gmdate( 'Y-m-d-His' ) . '/';

		$copied = $this->copy_directory( $plugin_dir, $backup_dir );
		if ( is_wp_error( $copied ) ) {
			return $copied;
		}

		return $backup_dir;
	}

	/**
	 * Stage modified files for a plugin.
	 *
	 * Copies the current plugin directory to the staging area, then overlays
	 * the provided modified files on top of it so the staged copy is complete.
	 *
	 * @param string               $slug          Plugin slug.
	 * @param array<string,string> $modified_files Map of relative path → PHP/file source.
	 * @return string Absolute path to the staging directory on success.
	 *                Returns WP_Error on failure.
	 */
	public function stage( string $slug, array $modified_files ): string|WP_Error {
		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			return new WP_Error(
				'gratis_ai_agent_invalid_slug',
				__( 'Plugin slug must not be empty.', 'gratis-ai-agent' )
			);
		}

		$plugin_dir   = $this->plugin_dir( $slug );
		$staging_base = WP_CONTENT_DIR . '/gratis-ai-staging/';
		$staging_dir  = $staging_base . $slug . '/';

		// Start with a clean staging area.
		if ( is_dir( $staging_dir ) ) {
			$this->remove_directory( $staging_dir );
		}

		// Copy existing plugin to staging so the staged version is complete.
		if ( is_dir( $plugin_dir ) ) {
			$copied = $this->copy_directory( $plugin_dir, $staging_dir );
			if ( is_wp_error( $copied ) ) {
				return $copied;
			}
		} elseif ( ! wp_mkdir_p( $staging_dir ) ) {
			return new WP_Error(
				'gratis_ai_agent_staging_failed',
				/* translators: %s: directory */
				sprintf( __( 'Could not create staging directory: %s', 'gratis-ai-agent' ), $staging_dir )
			);
		}

		// Overlay the modified files.
		foreach ( $modified_files as $relative_path => $content ) {
			$relative_path = ltrim( $relative_path, '/\\' );
			$abs_path      = $staging_dir . $relative_path;

			if ( ! wp_mkdir_p( dirname( $abs_path ) ) ) {
				$this->remove_directory( $staging_dir );
				return new WP_Error(
					'gratis_ai_agent_staging_mkdir_failed',
					/* translators: %s: file path */
					sprintf( __( 'Could not create staging subdirectory for: %s', 'gratis-ai-agent' ), $relative_path )
				);
			}

			$fs = $this->get_filesystem();
			if ( is_wp_error( $fs ) ) {
				$this->remove_directory( $staging_dir );
				return $fs;
			}

			if ( ! $fs->put_contents( $abs_path, $content, FS_CHMOD_FILE ) ) {
				$this->remove_directory( $staging_dir );
				return new WP_Error(
					'gratis_ai_agent_staging_write_failed',
					/* translators: %s: file path */
					sprintf( __( 'Could not write staging file: %s', 'gratis-ai-agent' ), $relative_path )
				);
			}
		}

		return $staging_dir;
	}

	/**
	 * Run PluginSandbox layers 1 + 2 against a staged plugin directory.
	 *
	 * @param string $slug        Plugin slug.
	 * @param string $staging_dir Absolute path to the staging directory.
	 * @return array<string,mixed> Result array from PluginSandbox::run_all().
	 *                             Keys: layer1_passed, layer2_passed, errors, passed.
	 */
	public function test_staged( string $slug, string $staging_dir ): array {
		$main_file = $this->detect_main_file( $staging_dir, $slug );

		if ( '' === $main_file ) {
			return [
				'layer1_passed' => false,
				'layer2_passed' => false,
				'errors'        => [ __( 'Could not detect main plugin file in staging directory.', 'gratis-ai-agent' ) ],
				'passed'        => false,
			];
		}

		$result = PluginSandbox::run_all( $staging_dir, $main_file );
		if ( is_wp_error( $result ) ) {
			return [
				'layer1_passed' => false,
				'layer2_passed' => false,
				'errors'        => [ $result->get_error_message() ],
				'passed'        => false,
			];
		}

		return $result;
	}

	/**
	 * Swap the staged directory into the live plugin location.
	 *
	 * Deactivates the plugin, replaces the plugin directory with the staged
	 * copy, then reactivates. On reactivation failure the backup is restored.
	 *
	 * @param string $slug        Plugin slug.
	 * @param string $staging_dir Absolute path to the staging directory.
	 * @param string $backup_dir  Absolute path to the backup directory.
	 * @return array{swapped: bool, plugin_file: string, backup_dir: string}|\WP_Error
	 */
	public function swap( string $slug, string $staging_dir, string $backup_dir ): array|WP_Error {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugin_dir  = $this->plugin_dir( $slug );
		$main_file   = $this->detect_main_file( $staging_dir, $slug );
		$plugin_file = $slug . '/' . $main_file;

		// Deactivate if currently active.
		$was_active = is_plugin_active( $plugin_file );
		if ( $was_active ) {
			deactivate_plugins( $plugin_file, true );
		}

		// Remove live directory and move staged copy in.
		$this->remove_directory( $plugin_dir );
		$fs    = $this->get_filesystem();
		$moved = ! is_wp_error( $fs ) && $fs->move( $staging_dir, $plugin_dir, true );

		if ( ! $moved ) {
			// Restore backup on swap failure.
			$this->copy_directory( $backup_dir, $plugin_dir );
			if ( $was_active ) {
				activate_plugin( $plugin_file );
			}
			return new WP_Error(
				'gratis_ai_agent_swap_failed',
				__( 'Could not replace plugin directory. Backup restored.', 'gratis-ai-agent' )
			);
		}

		// Reactivate and verify.
		if ( $was_active ) {
			$activated = activate_plugin( $plugin_file );
			if ( is_wp_error( $activated ) ) {
				// Reactivation failed — restore backup.
				$this->remove_directory( $plugin_dir );
				$this->copy_directory( $backup_dir, $plugin_dir );
				activate_plugin( $plugin_file );
				return new WP_Error(
					'gratis_ai_agent_reactivation_failed',
					sprintf(
						/* translators: %s: activation error message */
						__( 'Plugin reactivation failed after swap; backup restored. Error: %s', 'gratis-ai-agent' ),
						$activated->get_error_message()
					)
				);
			}
		}

		return [
			'swapped'     => true,
			'plugin_file' => $plugin_file,
			'backup_dir'  => $backup_dir,
		];
	}

	/**
	 * Restore a plugin from a backup directory.
	 *
	 * @param string $slug       Plugin slug.
	 * @param string $backup_dir Absolute path to the backup directory.
	 * @return array{restored: bool, plugin_dir: string}|\WP_Error
	 */
	public function rollback( string $slug, string $backup_dir ): array|WP_Error {
		$backup_dir = trailingslashit( $backup_dir );

		if ( ! is_dir( $backup_dir ) ) {
			return new WP_Error(
				'gratis_ai_agent_backup_not_found',
				/* translators: %s: backup directory */
				sprintf( __( 'Backup directory not found: %s', 'gratis-ai-agent' ), $backup_dir )
			);
		}

		$plugin_dir = $this->plugin_dir( $slug );

		if ( is_dir( $plugin_dir ) ) {
			$this->remove_directory( $plugin_dir );
		}

		$copied = $this->copy_directory( $backup_dir, $plugin_dir );
		if ( is_wp_error( $copied ) ) {
			return $copied;
		}

		return [
			'restored'   => true,
			'plugin_dir' => $plugin_dir,
		];
	}

	/**
	 * Remove backup directories older than $max_age_days days.
	 *
	 * The most recent backup for each slug is always preserved regardless of age.
	 *
	 * Backup retention is configurable via the `gratis_ai_agent_backup_retention_days`
	 * filter.
	 *
	 * @param int $max_age_days Maximum backup age in days (default: 7).
	 * @return int Number of backup directories removed.
	 */
	public function cleanup_old_backups( int $max_age_days = 7 ): int {
		/**
		 * Filter the maximum age (in days) of plugin backups before they are removed.
		 *
		 * @param int $max_age_days Default retention period in days.
		 */
		$max_age_days = (int) apply_filters( 'gratis_ai_agent_backup_retention_days', $max_age_days );
		$max_age_days = max( 1, $max_age_days );

		$backup_base = WP_CONTENT_DIR . '/gratis-ai-backups/';
		if ( ! is_dir( $backup_base ) ) {
			return 0;
		}

		$cutoff  = time() - ( $max_age_days * DAY_IN_SECONDS );
		$removed = 0;

		// Build a list of all backup dirs, grouped by slug prefix.
		$entries = scandir( $backup_base );
		$by_slug = [];

		if ( false === $entries ) {
			return 0;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$full_path = $backup_base . $entry;
			if ( ! is_dir( $full_path ) ) {
				continue;
			}

			// Entry format: {slug}-YYYY-MM-DD-HHiiss
			// Extract slug by removing the trailing timestamp (last 4 segments: date + time).
			if ( preg_match( '/^(.+)-(\d{4}-\d{2}-\d{2}-\d{6})$/', $entry, $m ) ) {
				$slug_key = $m[1];
			} else {
				$slug_key = $entry;
			}

			$mtime                  = (int) filemtime( $full_path );
			$by_slug[ $slug_key ][] = [
				'path'  => $full_path,
				'mtime' => $mtime,
			];
		}

		foreach ( $by_slug as $slug_backups ) {
			// Sort descending by mtime so index 0 is the most recent.
			usort(
				$slug_backups,
				static function ( array $a, array $b ): int {
					return $b['mtime'] - $a['mtime'];
				}
			);

			foreach ( $slug_backups as $i => $backup ) {
				// Never remove the most recent backup (index 0).
				if ( 0 === $i ) {
					continue;
				}

				if ( $backup['mtime'] < $cutoff ) {
					$this->remove_directory( $backup['path'] );
					++$removed;
				}
			}
		}

		return $removed;
	}

	/**
	 * Orchestrate a full sandboxed update for a plugin.
	 *
	 * Steps:
	 *   1. Backup existing plugin directory.
	 *   2. Stage the modified files.
	 *   3. Run PluginSandbox layers 1 + 2 on the staged copy.
	 *   4. If tests pass: swap staged copy over live dir.
	 *   5. If tests fail: remove staging dir and return WP_Error.
	 *   6. Cleanup staging dir on success.
	 *
	 * @param string               $slug          Plugin slug (directory name under wp-content/plugins/).
	 * @param array<string,string> $modified_files Map of relative path → PHP/file source.
	 * @return array{updated: bool, plugin_file: string, backup_dir: string}|\WP_Error
	 */
	public function update( string $slug, array $modified_files ): array|WP_Error {
		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			return new WP_Error(
				'gratis_ai_agent_invalid_slug',
				__( 'Plugin slug must not be empty.', 'gratis-ai-agent' )
			);
		}

		// Step 1: Backup.
		$backup_dir = $this->backup( $slug );
		if ( is_wp_error( $backup_dir ) ) {
			return $backup_dir;
		}

		// Step 2: Stage.
		$staging_dir = $this->stage( $slug, $modified_files );
		if ( is_wp_error( $staging_dir ) ) {
			return $staging_dir;
		}

		// Step 3: Test staged copy.
		$test_result = $this->test_staged( $slug, $staging_dir );
		if ( ! $test_result['passed'] ) {
			$this->remove_directory( $staging_dir );
			return new WP_Error(
				'gratis_ai_agent_sandbox_failed',
				implode( '; ', $test_result['errors'] )
			);
		}

		// Step 4: Swap staged copy into live location.
		$swap_result = $this->swap( $slug, $staging_dir, $backup_dir );
		if ( is_wp_error( $swap_result ) ) {
			return $swap_result;
		}

		return [
			'updated'     => true,
			'plugin_file' => $swap_result['plugin_file'],
			'backup_dir'  => $backup_dir,
		];
	}

	// ── Private helpers ───────────────────────────────────────────────────

	/**
	 * Get absolute path to a plugin's live directory (with trailing slash).
	 *
	 * @param string $slug Plugin slug.
	 * @return string
	 */
	private function plugin_dir( string $slug ): string {
		return WP_CONTENT_DIR . '/plugins/' . $slug . '/';
	}

	/**
	 * Detect the main plugin file within a directory.
	 *
	 * Scans PHP files in the directory root for a WordPress Plugin Name header.
	 * Falls back to {slug}.php if no header-bearing file is found.
	 *
	 * @param string $plugin_dir Absolute path to the plugin directory.
	 * @param string $slug       Plugin slug used as fallback filename.
	 * @return string Filename relative to $plugin_dir (e.g. "my-plugin.php").
	 */
	private function detect_main_file( string $plugin_dir, string $slug ): string {
		$plugin_dir = trailingslashit( $plugin_dir );
		$candidates = glob( $plugin_dir . '*.php' );

		if ( false !== $candidates ) {
			foreach ( $candidates as $file ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file.
				$header = file_get_contents( $file, false, null, 0, 8192 );
				if ( false !== $header && false !== stripos( $header, 'Plugin Name:' ) ) {
					return basename( $file );
				}
			}
		}

		// Fallback to conventional slug.php.
		return $slug . '.php';
	}

	/**
	 * Copy a directory recursively.
	 *
	 * @param string $source      Absolute source directory path.
	 * @param string $destination Absolute destination directory path.
	 * @return true|\WP_Error
	 */
	private function copy_directory( string $source, string $destination ): bool|WP_Error {
		$source      = trailingslashit( $source );
		$destination = trailingslashit( $destination );

		if ( ! wp_mkdir_p( $destination ) ) {
			return new WP_Error(
				'gratis_ai_agent_mkdir_failed',
				/* translators: %s: directory */
				sprintf( __( 'Could not create directory: %s', 'gratis-ai-agent' ), $destination )
			);
		}

		$real_source = realpath( $source );
		if ( false === $real_source ) {
			return new WP_Error(
				'gratis_ai_agent_copy_source_invalid',
				/* translators: %s: source directory */
				sprintf( __( 'Could not resolve source directory: %s', 'gratis-ai-agent' ), $source )
			);
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$relative  = substr( (string) $item->getRealPath(), strlen( $real_source ) );
			$dest_path = $destination . ltrim( $relative, DIRECTORY_SEPARATOR );

			if ( $item->isDir() ) {
				wp_mkdir_p( $dest_path );
			} else {
				wp_mkdir_p( dirname( $dest_path ) );
				copy( (string) $item->getRealPath(), $dest_path );
			}
		}

		return true;
	}

	/**
	 * Remove a directory and all its contents recursively.
	 *
	 * @param string $dir Absolute path to the directory to remove.
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

	/**
	 * Initialise WP_Filesystem and return the global instance.
	 *
	 * @return \WP_Filesystem_Base|\WP_Error
	 */
	private function get_filesystem(): \WP_Filesystem_Base|WP_Error {
		global $wp_filesystem;
		/** @var \WP_Filesystem_Base|null $wp_filesystem */
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( empty( $wp_filesystem ) ) {
			return new WP_Error(
				'gratis_ai_agent_filesystem_init_failed',
				__( 'Could not initialise WP_Filesystem.', 'gratis-ai-agent' )
			);
		}

		return $wp_filesystem;
	}
}
