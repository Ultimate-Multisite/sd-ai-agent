<?php //phpcs:disable Squiz.Commenting

namespace XWP\Helper\Functions;

class WPFS {
    /**
     * Whether the class has been hooked.
     *
     * @var bool|null
     */
    private static ?bool $hooked = null;

	final public static function load(
        array|bool $args = false,
        string|bool $ctx = false,
        bool $ownr = false,
    ): \WP_Filesystem_Base|bool|null {
        self::$hooked ??= self::hook();

        if ( isset( $GLOBALS['wp_filesystem'] ) ) {
            return $GLOBALS['wp_filesystem'];
        }

		return match ( \WP_Filesystem( $args, $ctx, $ownr ) ) {
            true    => $GLOBALS['wp_filesystem'],
            false   => false,
            default => null
        };
	}

    private static function hook(): void {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        \add_filter( 'filesystem_method', array( self::class, 'fs_method' ), 99, 2 );
    }

    public static function fs_method( string $method, array|bool $args ) {
        if ( ! \is_array( $args ) || ! isset( $args['method'] ) ) {
            return $method;
        }

        $base  = \ucfirst( \str_replace( 'WP_Filesystem_', '', $args['method'] ) );
        $cname = 'WP_Filesystem_' . $base;

        return \class_exists( $cname ) ? $base : $method;
    }
}
