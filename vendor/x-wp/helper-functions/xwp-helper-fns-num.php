<?php
/**
 * Numeric functions and helpers
 *
 * @package eXtended WordPress
 * @subpackage Functions
 */

if ( ! function_exists( 'xwp_is_int_str' ) ) :

	/**
	 * Check if a string is a integer
	 *
	 * @param  mixed $str The string to check.
	 * @return bool
	 */
	function xwp_is_int_str( mixed $str ): bool {
		if ( is_int( $str ) ) {
			return true;
		}

		return is_numeric( $str ) && ctype_digit( strval( abs( $str ) ) );
	}

endif;

if ( ! function_exists( 'xwp_is_float_str' ) ) :

	/**
	 * Check if a string is a float
	 *
	 * @param  mixed $str The string to check.
	 * @return bool
	 */
	function xwp_is_float_str( mixed $str ): bool {
		if ( is_float( $str ) ) {
			return true;
		}

		return is_numeric( $str ) && ( (string) (float) $str ) === $str;
	}

endif;
