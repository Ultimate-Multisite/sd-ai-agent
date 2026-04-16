<?php
/**
 * Array helper functions definition file.
 *
 * @package eXtended WordPress
 * @subpackage Helper\Functions
 */

use XWP\Helper\Functions as f;

if ( ! function_exists( 'xwp_arr_mergemap' ) ) :
    /**
     * Applies the callback to the elements of the given array and merges the results into a single array.
     *
     * @template T
     * @template R
     * @template RKey of array-key
     * @template RArr of array<RKey,R>
     *
     * @param  callable(T): RArr $cbfn Callback function to run for each element in the array.
     * @param  array<T>          $arr   The input array.
     * @return RArr
     */
    function xwp_arr_mergemap( callable $cbfn, array $arr ): array {
        return f\Array_Extra::mergemap( $cbfn, $arr );
    }
endif;

if ( ! function_exists( 'xwp_array_flatmap' ) ) :
    /**
	 * Flattens and maps an array.
	 *
	 * @template T
	 * @template R
     *
	 * @param  callable(T): R|array<R> $callback    Function to apply to each element.
	 * @param  array<T>                $input_array  Array to flatten and map.
     *
	 * @return array<int,R>
	 */
	function xwp_array_flatmap( callable $callback, array $input_array ) {
        return f\Array_Extra::flatmap( $callback, $input_array );
    }
endif;

if ( ! function_exists( 'wp_array_flatmap' ) ) :
    /**
     * Flattens and maps an array.
     *
     * @template T The type of the elements in the input array.
     * @template R The type of the elements in the returned array.
     *
     * @param  array<array-key, T>|callable(T): R $callback    Function to apply to each element.
     * @param  array<array-key, T>|callable(T): R $input_array  Array to flatten and map.
     *
     * @return array<array-key, R>
     */
    function wp_array_flatmap( callable|array $callback, array|callable $input_array ) {
        return is_callable( $callback )
            ? xwp_array_flatmap( $callback, $input_array )
            : xwp_array_flatmap( $input_array, $callback );
    }
endif;


if ( ! function_exists( 'wp_array_diff_assoc' ) ) :
    /**
     * Extracts a slice of array not including the specified keys.
     *
     * @template TArr of array
     * @template TKey of key-of<TArr>
     *
     * @param  TArr            $arr     Input array.
     * @param  array<int,TKey> $keys Keys to remove.
     * @return TArr
     */
    function wp_array_diff_assoc( array $arr, array $keys ) {
        return xwp_array_diff_assoc( $arr, ...$keys );
    }
endif;

if ( ! function_exists( 'xwp_array_diff_assoc' ) ) :
    /**
     * Extracts a slice of array not including the specified keys.
     *
     * @template TArr of array
     * @template TKey of key-of<TArr>
     *
     * @param  TArr                 $arr     Input array.
     * @param  TKey|array<int,TKey> ...$keys Keys to remove.
     * @return TArr
     */
    function xwp_array_diff_assoc( array $arr, array|string ...$keys ) {
        /**
         * Validate the keys.
         *
         * @var array<int,TKey> $keys
         */
        $keys = is_array( current( $keys ) ) ? current( $keys ) : $keys;

        return $keys ? f\Array_Extra::diff_assoc( $arr, $keys ) : $arr;
    }

endif;

if ( ! function_exists( 'wp_array_rekey' ) ) :
    /**
     * Rekey an array of arrays by a specific key.
     *
     * @param  array<string, array<string, mixed>> $arr The input array.
     * @param  string                              $key The key to rekey by.
     * @return array<string, array<string, mixed>>      The rekeyed array.
     */
    function wp_array_rekey( array $arr, string $key ): array {
        return f\Array_Extra::rekey( $arr, $key );
    }
endif;

if ( ! function_exists( 'xwp_array_slice_assoc' ) ) :
    /**
     * Extracts a slice of an array.
     *
     * @template TArr of array
     * @template TKey of key-of<TArr>
     *
     * @param  TArr                 $arr   Input array.
     * @param  TKey|array<int,TKey> ...$keys     Keys to extract.
     * @return TArr
     */
    function xwp_array_slice_assoc( array $arr, string|array ...$keys ) {
        /**
         * Validate the keys.
         *
         * @var array<int,TKey> $keys
         */
        $keys = is_array( current( $keys ) ) ? current( $keys ) : $keys;

        return f\Array_Extra::slice_assoc( $arr, $keys );
    }
endif;

if ( ! function_exists( 'xwp_str_to_arr' ) ) :
    /**
     * Convert a string to an array.
     *
     * @param  null|string|array<int,string> $target The string to convert.
     * @param  null|string                   $delim              Optional. The delimiter to use. Default is ','.
     * @return array<int,string>
     */
    function xwp_str_to_arr( string|array|null $target = null, ?string $delim = null ): array {
        return f\Array_Extra::from_string( $target ?? array(), $delim ?? ',' );
    }
endif;
