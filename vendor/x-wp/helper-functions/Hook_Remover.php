<?php

namespace XWP\Helper\Functions;

class Hook_Remover {
    private static function get_classname( string|object|bool|null $target = '' ): string|bool {
        $classname = match ( true ) {
            \is_null( $target ),
            false === $target     => '',
            \is_object( $target ) => $target::class,
            \is_string( $target ) => $target,
            default               => false,
        };

        return \class_exists( $classname ) ? $classname : false;
    }

    private static function callback_matches( callable|array $callback, string $classname, string|bool $method = false ): bool {
        if ( ! \is_array( $callback['function'] ) ) {
            return false;
        }

        if ( $classname !== self::get_classname( $callback['function'][0] ?? false ) ) {
            return false;
        }

        return ! $method || ! ( $method !== $callback['function'][1] ?? false );
    }

    private static function get_callbacks( string $hook_name, int|bool $priority = false ): array {
        return $priority
            ? $GLOBALS['wp_filter'][ $hook_name ][ $priority ] ?? array()
            : $GLOBALS['wp_filter'][ $hook_name ]->callbacks ?? array();
    }

    final public static function remove_callback( string $hook_id, string $hook_name, int $priority = 10 ): bool {
        if ( ! isset( $GLOBALS['wp_filter'][ $hook_name ][ $priority ][ $hook_id ] ) ) {
            return false;
        }

        unset( $GLOBALS['wp_filter'][ $hook_name ]->callbacks[ $priority ][ $hook_id ] );

        return true;
    }

    final public static function get_callback_id( string $classname, string $method, string $hook_name, int $priority = 10 ): ?string {
        $callbacks = self::get_callbacks( $hook_name, $priority );

        foreach ( $callbacks as $id => $callback ) {
            if ( self::callback_matches( $callback, $classname, $method ) ) {
                return $id;
            }
        }

        return null;
    }

    final public static function remove_callbacks(
        string $classname,
        string|bool $target_hook = false,
        string|bool $method = false,
        int|bool $priority = false,
	): array {
        $removed = array();

        $callbacks = $target_hook
            ? array( $target_hook => self::get_callbacks( $target_hook ) )
            : $GLOBALS['wp_filter'];

        foreach ( $callbacks as $hook_name => $grouped_cbs ) {
            if ( $priority ) {
                $grouped_cbs = array( $priority => $grouped_cbs[ $priority ] ?? array() );
            }
            foreach ( $grouped_cbs as $cb_prio => $cbs ) {
                foreach ( $cbs as $id => $cb ) {
                    if ( ! self::callback_matches( $cb, $classname, $method ) ) {
                        continue;
                    }

                    $fname  = $cb['function'][1];
                    $status = self::remove_callback( $id, $hook_name, $cb_prio );

                    $removed[ $hook_name ][ $cb_prio ]         ??= array();
                    $removed[ $hook_name ][ $cb_prio ][ $fname ] = $status;
                }
            }
        }

		return $removed;
    }
}
