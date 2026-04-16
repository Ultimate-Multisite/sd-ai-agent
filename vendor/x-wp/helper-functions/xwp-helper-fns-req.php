<?php
/**
 * Request helper functions definition file.
 *
 * @package eXtended WordPress
 * @subpackage Helper\Functions
 */

use XWP\Helper\Functions as f;

if ( ! function_exists( 'xwp_can_load_rest_ns' ) ) :
    /**
     * Check if a REST namespace should be loaded. Useful to maintain site performance even when lots of REST namespaces are registered.
     *
     * @param  string      $space The namespace to check.
     * @param  string|null $route REST route being checked. Optional.
     * @param  array       $known Known namespaces that we know are safe to not load if the request is not for them.
     * @return bool
     */
    function xwp_can_load_rest_ns( string $space, ?string $route = null, array $known = array() ): bool {
        return f\Request::should_load_rest_ns( $space, $route, $known );
    }

endif;

if ( ! function_exists( 'xwp_fetch_get_var' ) ) :
    /**
     * Get an item of `GET` data if set, otherwise return a default value.
     *
     * @template T of string|int|bool|float|array|null
     *
     * @param  string $key GET key.
     * @param  T      $def Default value.
     * @return (T is string ? string : (T is int ? int : (T is array ? T : (T is float ? float : (T is bool ? bool : (T is null ? null : mixed))))))
     */
    function xwp_fetch_get_var( string $key, mixed $def = null ): mixed {
        return f\Request::fetch_get_var( $key, $def );
    }
endif;

if ( ! function_exists( 'xwp_fetch_post_var' ) ) :
    /**
     * Get an item of `POST` data if set, otherwise return a default value.
     *
     * @template T of string|int|bool|float|array|null
     *
     * @param  string $key POST key.
     * @param  T      $def Default value.
     * @return (T is string ? string : (T is int ? int : (T is array ? T : (T is float ? float : (T is bool ? bool : (T is null ? null : mixed))))))
     */
    function xwp_fetch_post_var( string $key, mixed $def = null ): mixed {
        return f\Request::fetch_post_var( $key, $def );
    }
endif;

if ( ! function_exists( 'xwp_fetch_req_var' ) ) :
    /**
     * Get an item of `REQUEST`data if set, otherwise return a default value.
     *
     * @template T of string|int|bool|float|array|null
     *
     * @param  string $key REQUEST key.
     * @param  T      $def Default value.
     * @return (T is string ? string : (T is int ? int : (T is array ? T : (T is float ? float : (T is bool ? bool : (T is null ? null : mixed))))))
     */
    function xwp_fetch_req_var( $key, $def = null ) {
        return f\Request::fetch_req_var( $key, $def );
    }
endif;

if ( ! function_exists( 'xwp_fetch_server_var' ) ) :
    /**
     * Get an item of `SERVER` data if set, otherwise return a default value.
     *
     * @template T of string|int|bool|float|array|null
     *
     * @param  string $key SERVER key.
     * @param  T      $def Default value.
     * @return (T is string ? string : (T is int ? int : (T is array ? T : (T is float ? float : (T is bool ? bool : (T is null ? null : mixed))))))
     */
    function xwp_fetch_server_var( string $key, mixed $def = null ): mixed {
        return f\Request::fetch_server_var( $key, $def );
    }
endif;

if ( ! function_exists( 'xwp_fetch_cookie_var' ) ) :
    /**
     * Get an item of `COOKIE` data if set, otherwise return a default value.
     *
     * @param  string $key COOKIE key.
     * @param  string $def Default value.
     * @return string
     */
    function xwp_fetch_cookie_var( string $key, string $def = '' ): string {
        return f\Request::fetch_cookie_var( $key, $def );
    }
endif;

if ( ! function_exists( 'xwp_fetch_files_var' ) ) :
    /**
     * Get an item of `FILES` data if set, otherwise return a default value.
     *
     * @param  string $key  FILES key.
     * @param  string $def  Default value.
     * @return mixed  Value sanitized by xwp_uclean.
     */
    function xwp_fetch_files_var( $key, $def = null ) {
        return f\Request::fetch_files_var( $key, $def );
    }
endif;

if ( ! function_exists( 'xwp_get_arr' ) ) :
    /**
     * Get the unslashed and cleaned $_GET array.
     *
     * @template T of array
     * @param  T $def Default value.
     * @return T
     */
    function xwp_get_arr( array $def = array() ): array {
        return f\Request::fetch_get_arr( $def );
    }
endif;

if ( ! function_exists( 'xwp_post_arr' ) ) :
    /**
     * Get the unslashed and cleaned $_POST array.
     *
     * @template T of array
     * @param  T $def Default value.
     * @return T
     */
    function xwp_post_arr( array $def = array() ): array {
        return f\Request::fetch_post_arr( $def );
    }
endif;

if ( ! function_exists( 'xwp_req_arr' ) ) :
    /**
     * Get the unslashed and cleaned $_REQUEST array.
     *
     * @template T of array
     * @param  T $def Default value.
     * @return T
     */
    function xwp_req_arr( array $def = array() ): array {
        return f\Request::fetch_req_arr( $def );
    }
endif;

if ( ! function_exists( 'xwp_server_arr' ) ) :
    /**
     * Get the unslashed and cleaned $_SERVER array.
     *
     * @template T of array
     * @param  T $def Default value.
     * @return T
     */
    function xwp_server_arr( array $def = array() ): array {
        return f\Request::fetch_server_arr( $def );
    }
endif;

if ( ! function_exists( 'xwp_cookie_arr' ) ) :
    /**
     * Get the unslashed and cleaned $_COOKIE array.
     *
     * @return array<string, mixed>
     */
    function xwp_cookie_arr(): array {
        return f\Request::fetch_cookie_arr();
    }
endif;

if ( ! function_exists( 'xwp_files_arr' ) ) :
    /**
     * Get the unslashed and cleaned $_FILES array.
     *
     * @return array<string, mixed>
     */
    function xwp_files_arr(): array {
        return f\Request::fetch_files_arr();
    }
endif;
