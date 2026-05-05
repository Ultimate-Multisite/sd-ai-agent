<?php
/**
 * Test case for ModelsCommand documentation.
 *
 * @package SdAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Tests\CLI;

use WP_UnitTestCase;

/**
 * Test ModelsCommand documentation accuracy.
 */
class ModelsCommandTest extends WP_UnitTestCase {

	/**
	 * Test the file-level docblock documents in-process provider listing.
	 */
	public function test_file_docblock_documents_in_process_provider_listing(): void {
		$contents = file_get_contents( dirname( __DIR__, 3 ) . '/includes/CLI/ModelsCommand.php' );

		$this->assertIsString( $contents );
		$this->assertStringContainsString( 'Mirrors the logic of SettingsController::handle_providers() in-process', $contents );
		$this->assertStringNotContainsString( 'Reuses the /providers REST endpoint', $contents );
	}
}
