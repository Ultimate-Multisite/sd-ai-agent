<?php //phpcs:disable Squiz.Commenting

namespace XWP\Helper\Functions;

class Array_Extra {
    final public static function mergemap( callable $callback, array $arr ): array {
        $result = array();

        foreach ( $arr as $value ) {
            $mapped = $callback( $value );

            if ( ! \is_array( $mapped ) ) {
                continue;
            }

            $result[] = $mapped;
        }

        return \array_merge( ...\array_values( \array_filter( $result ) ) );
    }

    final public static function rekey( array $arr, string $key ): array {
        $result = array();

        foreach ( $arr as $item ) {
            if ( ! isset( $item[ $key ] ) ) {
                continue; // Skip items without the key
            }

            $new = $item[ $key ];
            unset( $item[ $key ] ); // Remove the key
            $result[ $new ] = $item;
        }

        return $result;
    }

    final public static function flatmap( callable $callback, $arr ): array {
        $res = array();

        foreach ( $arr as $v ) {
            $mapped = $callback( $v );

            $res[] = (array) $mapped;
        }

        return \array_merge( ...$res );
    }

    final public static function diff_assoc( array $input_array, array $keys ): array {
        return $keys
            ? \array_diff_key( $input_array, \array_flip( $keys ) )
            : $input_array;
    }

    /**
     * Slice the array
     *
     * @template TArr of array
     * @template TKey of string
     *
     * @param  TArr $input_array     The input array.
     * @param  array<int,TKey> $keys The keys to extract.
     * @return TArr
     */
    final public static function slice_assoc( array $input_array, array $keys ): array {
        return $keys
            ? \array_intersect_key( $input_array, \array_flip( $keys ) )
            : $input_array;
    }

    final public static function from_string( string|array $target, string $delim ): array {
        if ( \is_array( $target ) ) {
            return $target;
        }

        return \array_values(
            \array_filter(
                \array_map( 'trim', \explode( $delim, $target ) ),
                static fn( $v ) => '' !== $v,
            ),
        );
    }
}
