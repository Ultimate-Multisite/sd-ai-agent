<?php
/**
 * Test case for FileAbilities class.
 *
 * @package AiAgent
 * @subpackage Tests
 */

namespace AiAgent\Tests\Abilities;

use AiAgent\Abilities\FileAbilities;
use WP_UnitTestCase;

/**
 * Test FileAbilities handler methods.
 *
 * All file operations are scoped to WP_CONTENT_DIR.
 * Tests use a dedicated subdirectory to avoid polluting the real wp-content.
 */
class FileAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Test subdirectory within wp-content for isolation.
	 *
	 * @var string
	 */
	private string $test_dir = 'ai-agent-test-files';

	/**
	 * Set up test directory and initialize WP_Filesystem.
	 */
	public function set_up(): void {
		parent::set_up();

		// Initialize WP_Filesystem so FS_CHMOD_FILE is defined.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		// WP_Filesystem() defines FS_CHMOD_FILE internally.
		// Force direct filesystem method for tests.
		add_filter( 'filesystem_method', function () { return 'direct'; } );
		WP_Filesystem();
		remove_all_filters( 'filesystem_method' );

		// Fallback: define FS_CHMOD_FILE if WP_Filesystem() didn't define it.
		if ( ! defined( 'FS_CHMOD_FILE' ) ) {
			define( 'FS_CHMOD_FILE', 0644 );
		}

		$dir = WP_CONTENT_DIR . '/' . $this->test_dir;
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
	}

	/**
	 * Tear down: remove test directory.
	 */
	public function tear_down(): void {
		parent::tear_down();
		$dir = WP_CONTENT_DIR . '/' . $this->test_dir;
		if ( file_exists( $dir ) ) {
			$this->remove_dir( $dir );
		}
	}

	/**
	 * Recursively remove a directory.
	 *
	 * @param string $dir Directory path.
	 */
	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$entries = scandir( $dir );
		if ( false !== $entries ) {
			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					continue;
				}
				$path = $dir . '/' . $entry;
				if ( is_dir( $path ) ) {
					$this->remove_dir( $path );
				} else {
					unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				}
			}
		}
		rmdir( $dir );
	}

	/**
	 * Test handle_write_file creates a new file.
	 */
	public function test_handle_write_file_creates_file() {
		$path = $this->test_dir . '/test-write.txt';

		$result = FileAbilities::handle_write_file( [
			'path'    => $path,
			'content' => 'Hello, world!',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'path', $result );
		$this->assertArrayHasKey( 'action', $result );
		$this->assertArrayHasKey( 'size', $result );
		$this->assertSame( 'created', $result['action'] );
		$this->assertSame( 13, $result['size'] );
		$this->assertFileExists( WP_CONTENT_DIR . '/' . $path );
	}

	/**
	 * Test handle_write_file updates existing file.
	 */
	public function test_handle_write_file_updates_existing() {
		$path = $this->test_dir . '/test-update.txt';

		// Create first.
		FileAbilities::handle_write_file( [
			'path'    => $path,
			'content' => 'Original',
		] );

		// Update.
		$result = FileAbilities::handle_write_file( [
			'path'    => $path,
			'content' => 'Updated content',
		] );

		$this->assertSame( 'updated', $result['action'] );
	}

	/**
	 * Test handle_write_file rejects path traversal.
	 */
	public function test_handle_write_file_path_traversal() {
		$result = FileAbilities::handle_write_file( [
			'path'    => '../../../etc/passwd',
			'content' => 'malicious',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_path_traversal', $result->get_error_code() );
	}

	/**
	 * Test handle_write_file rejects PHP with syntax errors.
	 */
	public function test_handle_write_file_php_syntax_error() {
		$path = $this->test_dir . '/bad-syntax.php';

		$result = FileAbilities::handle_write_file( [
			'path'    => $path,
			'content' => '<?php this is not valid php !!!',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_php_syntax_error', $result->get_error_code() );
	}

	/**
	 * Test handle_write_file accepts valid PHP.
	 */
	public function test_handle_write_file_valid_php() {
		$path = $this->test_dir . '/valid.php';

		$result = FileAbilities::handle_write_file( [
			'path'    => $path,
			'content' => '<?php echo "hello";',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'action', $result );
	}

	/**
	 * Test handle_read_file reads existing file.
	 */
	public function test_handle_read_file_existing() {
		$path    = $this->test_dir . '/test-read.txt';
		$content = 'Read test content';

		// Write first.
		FileAbilities::handle_write_file( [
			'path'    => $path,
			'content' => $content,
		] );

		$result = FileAbilities::handle_read_file( [ 'path' => $path ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'path', $result );
		$this->assertArrayHasKey( 'content', $result );
		$this->assertArrayHasKey( 'size', $result );
		$this->assertArrayHasKey( 'modified', $result );
		$this->assertSame( $content, $result['content'] );
		$this->assertSame( strlen( $content ), $result['size'] );
	}

	/**
	 * Test handle_read_file with non-existent file returns WP_Error.
	 */
	public function test_handle_read_file_not_found() {
		$result = FileAbilities::handle_read_file( [
			'path' => $this->test_dir . '/nonexistent-file.txt',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_file_not_found', $result->get_error_code() );
	}

	/**
	 * Test handle_read_file rejects path traversal.
	 */
	public function test_handle_read_file_path_traversal() {
		$result = FileAbilities::handle_read_file( [
			'path' => '../../../etc/passwd',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_path_traversal', $result->get_error_code() );
	}

	/**
	 * Test handle_edit_file applies search-replace edits.
	 */
	public function test_handle_edit_file_applies_edits() {
		$path = $this->test_dir . '/test-edit.txt';

		FileAbilities::handle_write_file( [
			'path'    => $path,
			'content' => 'Hello world, this is a test.',
		] );

		$result = FileAbilities::handle_edit_file( [
			'path'  => $path,
			'edits' => [
				[
					'search'  => 'Hello world',
					'replace' => 'Goodbye world',
				],
			],
		] );

		$this->assertIsArray( $result );
		$this->assertSame( 1, $result['edits_applied'] );
		$this->assertSame( 0, $result['edits_failed'] );

		// Verify content changed.
		$read = FileAbilities::handle_read_file( [ 'path' => $path ] );
		$this->assertStringContainsString( 'Goodbye world', $read['content'] );
	}

	/**
	 * Test handle_edit_file fails when search string not found.
	 */
	public function test_handle_edit_file_search_not_found() {
		$path = $this->test_dir . '/test-edit-fail.txt';

		FileAbilities::handle_write_file( [
			'path'    => $path,
			'content' => 'Some content here.',
		] );

		$result = FileAbilities::handle_edit_file( [
			'path'  => $path,
			'edits' => [
				[
					'search'  => 'This string does not exist',
					'replace' => 'replacement',
				],
			],
		] );

		$this->assertSame( 0, $result['edits_applied'] );
		$this->assertSame( 1, $result['edits_failed'] );
	}

	/**
	 * Test handle_edit_file fails when search string is not unique.
	 */
	public function test_handle_edit_file_search_not_unique() {
		$path = $this->test_dir . '/test-edit-dup.txt';

		FileAbilities::handle_write_file( [
			'path'    => $path,
			'content' => 'duplicate duplicate',
		] );

		$result = FileAbilities::handle_edit_file( [
			'path'  => $path,
			'edits' => [
				[
					'search'  => 'duplicate',
					'replace' => 'unique',
				],
			],
		] );

		$this->assertSame( 0, $result['edits_applied'] );
		$this->assertSame( 1, $result['edits_failed'] );
	}

	/**
	 * Test handle_edit_file with non-existent file returns WP_Error.
	 */
	public function test_handle_edit_file_file_not_found() {
		$result = FileAbilities::handle_edit_file( [
			'path'  => $this->test_dir . '/nonexistent.txt',
			'edits' => [ [ 'search' => 'x', 'replace' => 'y' ] ],
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_file_not_found', $result->get_error_code() );
	}

	/**
	 * Test handle_delete_file removes a file.
	 */
	public function test_handle_delete_file_removes_file() {
		$path = $this->test_dir . '/test-delete.txt';

		FileAbilities::handle_write_file( [
			'path'    => $path,
			'content' => 'To be deleted',
		] );

		$result = FileAbilities::handle_delete_file( [ 'path' => $path ] );

		$this->assertIsArray( $result );
		$this->assertSame( 'deleted', $result['action'] );
		$this->assertFileDoesNotExist( WP_CONTENT_DIR . '/' . $path );
	}

	/**
	 * Test handle_delete_file with non-existent file returns WP_Error.
	 */
	public function test_handle_delete_file_not_found() {
		$result = FileAbilities::handle_delete_file( [
			'path' => $this->test_dir . '/nonexistent.txt',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_file_not_found', $result->get_error_code() );
	}

	/**
	 * Test handle_list_directory lists files.
	 */
	public function test_handle_list_directory_lists_files() {
		// Create some files.
		FileAbilities::handle_write_file( [
			'path'    => $this->test_dir . '/file1.txt',
			'content' => 'File 1',
		] );
		FileAbilities::handle_write_file( [
			'path'    => $this->test_dir . '/file2.txt',
			'content' => 'File 2',
		] );

		$result = FileAbilities::handle_list_directory( [
			'path' => $this->test_dir,
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'path', $result );
		$this->assertArrayHasKey( 'items', $result );
		$this->assertArrayHasKey( 'count', $result );
		$this->assertGreaterThanOrEqual( 2, $result['count'] );
	}

	/**
	 * Test handle_list_directory item structure.
	 */
	public function test_handle_list_directory_item_structure() {
		FileAbilities::handle_write_file( [
			'path'    => $this->test_dir . '/structure-test.txt',
			'content' => 'content',
		] );

		$result = FileAbilities::handle_list_directory( [
			'path' => $this->test_dir,
		] );

		$this->assertNotEmpty( $result['items'] );
		$item = $result['items'][0];
		$this->assertArrayHasKey( 'name', $item );
		$this->assertArrayHasKey( 'type', $item );
		$this->assertArrayHasKey( 'modified', $item );
	}

	/**
	 * Test handle_list_directory with non-existent directory returns WP_Error.
	 */
	public function test_handle_list_directory_not_found() {
		$result = FileAbilities::handle_list_directory( [
			'path' => 'nonexistent-directory-xyz',
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_dir_not_found', $result->get_error_code() );
	}

	/**
	 * Test handle_search_files finds matching files.
	 */
	public function test_handle_search_files_finds_matches() {
		// Create test files.
		FileAbilities::handle_write_file( [
			'path'    => $this->test_dir . '/match-a.txt',
			'content' => 'content a',
		] );
		FileAbilities::handle_write_file( [
			'path'    => $this->test_dir . '/match-b.txt',
			'content' => 'content b',
		] );

		$result = FileAbilities::handle_search_files( [
			'pattern' => $this->test_dir . '/match-*.txt',
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'pattern', $result );
		$this->assertArrayHasKey( 'matches', $result );
		$this->assertArrayHasKey( 'count', $result );
		$this->assertGreaterThanOrEqual( 2, $result['count'] );
	}

	/**
	 * Test handle_search_files with no matches returns empty.
	 */
	public function test_handle_search_files_no_matches() {
		$result = FileAbilities::handle_search_files( [
			'pattern' => $this->test_dir . '/nonexistent-*.xyz',
		] );

		$this->assertSame( 0, $result['count'] );
		$this->assertEmpty( $result['matches'] );
	}

	/**
	 * Test handle_search_content finds text in files.
	 */
	public function test_handle_search_content_finds_text() {
		$unique_needle = 'UNIQUE_SEARCH_STRING_' . uniqid();

		FileAbilities::handle_write_file( [
			'path'    => $this->test_dir . '/search-content.php',
			'content' => "<?php\n// {$unique_needle}\necho 'hello';",
		] );

		$result = FileAbilities::handle_search_content( [
			'needle'    => $unique_needle,
			'directory' => $this->test_dir,
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'needle', $result );
		$this->assertArrayHasKey( 'matches', $result );
		$this->assertArrayHasKey( 'count', $result );
		$this->assertGreaterThanOrEqual( 1, $result['count'] );
	}

	/**
	 * Test handle_search_content with empty needle returns WP_Error.
	 */
	public function test_handle_search_content_empty_needle() {
		$result = FileAbilities::handle_search_content( [ 'needle' => '' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ai_agent_empty_needle', $result->get_error_code() );
	}

	/**
	 * Test handle_search_content with missing needle returns WP_Error.
	 */
	public function test_handle_search_content_missing_needle() {
		$result = FileAbilities::handle_search_content( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}
}
