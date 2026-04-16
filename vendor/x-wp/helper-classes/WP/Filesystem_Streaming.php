<?php //phpcs:disable WordPress.WP.AlternativeFunctions
/**
 * WP_Filesystem_Streaming class.
 *
 * File intentionally left without namespace.
 *
 * @package WooCommerce Sync Service
 * @subpackage WP
 */

/**
 * Custom WP filesystem class
 */
class WP_Filesystem_Streaming extends WP_Filesystem_Direct {
    /**
     * Current file path
     *
     * @var string
     */
    private $file_path;

    /**
     * Current file handle.
     *
     * @var null|resource $handle
     */
    private $handle;

    /**
     * Class constructor
     */
    public function __construct() {
        $this->file_path = '';
        $this->handle    = null;
        $this->method    = 'streaming';
        $this->errors    = new \WP_Error();
    }

    /**
     * Opens the file handle
     *
     * @param  string $file   File path.
     * @param  string $method Optional - Either 'r' or 'w'.
     * @return WP_Error|bool  True on success, WP_Error on failure.
     */
    public function open_file( $file, $method = 'a+' ) {
        if ( ! is_null( $this->handle ) ) {
            return new \WP_Error( 'fs_already_open', 'Filesystem is already open' );
        }

        $handle = fopen( $file, $method );

        if ( ! $handle ) {
            return new \WP_Error( 'fs_no_file', 'Could not open file' );
        }

        $this->handle    = $handle;
        $this->file_path = $file;

        return true;
    }

    /**
     * Closes the current open file handle
     *
     * @return bool True on success, false on failure.
     */
    public function close_file() {
        return fclose( $this->handle )
            ? true
            : new \WP_Error( 'fs_no_file', 'Could not close file' );
    }

    /**
     * Writes to the file handle
     *
     * @param  string $data Data to write.
     * @return int|WP_Error Number of bytes written on success, WP_Error on failure.
     */
    public function write( $data ) {
        return fwrite( $this->handle, $data );
    }

    /**
     * Writes a CSV Line to the file handle
     *
     * @param  mixed  $data      Fields to write.
     * @param  string $separator Field separator.
     * @param  string $enclosure Field enclosure.
     * @param  string $escape    Field escape.
     * @param  string $eol       End of line.
     * @return int|false         Number of bytes written on success, false on failure.
     */
    public function write_csv( $data, $separator = ',', $enclosure = '"', $escape = '\\', $eol = "\n" ) {
        return fputcsv( $this->handle, $data, $separator, $enclosure, $escape );
    }

    /**
     * Checks if the file handle is at the end of the file
     *
     * @return bool
     */
    public function end_of_file(): bool {
        return feof( $this->handle );
    }

    /**
     * Reads a line from the file handle
     *
     * @return string
     */
    public function read_line(): string {
        if ( ! feof( $this->handle ) ) {
            return fgets( $this->handle );
        }

        return new \WP_Error( 'fs_eof', 'End of file reached' );
    }

    /**
     * Reads from the file handle
     *
     * @param  int $length Number of lines to read.
     * @param  int $offset Offset to start reading from.
     * @return array|WP_Error Array of lines read on success, WP_Error on failure.
     */
    public function read_lines( int $length, int $offset ): array {
        $current_line = 0;
        $lines        = array();

        while ( $current_line < $offset && ! feof( $this->handle ) ) {
            fgets( $this->handle );
            ++$current_line;
        }

        while ( $current_line < $offset + $length && ! feof( $this->handle ) ) {
            $lines[] = fgets( $this->handle );
            ++$current_line;
        }

        return $lines;
    }

    /**
     * Gets the number of lines in the file handle
     *
     * @return int
     */
    public function get_number_of_lines(): int {
        $current_line = 0;

        while ( ! feof( $this->handle ) ) {
            fgets( $this->handle );
            ++$current_line;
        }

        rewind( $this->handle );

        return $current_line;
    }

    /**
     * Reads from the file handle
     *
     * @return string|WP_Error String of bytes read on success, WP_Error on failure.
     */
    public function read_file(): string|\WP_Error {
        $file_size = filesize( $this->file_path );

        if ( ! $file_size ) {
            return new \WP_Error( 'fs_no_file', 'Could not read file' );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fread
        $data = fread( $this->handle, $file_size );

        $this->close_file();

        return $data;
    }
}
