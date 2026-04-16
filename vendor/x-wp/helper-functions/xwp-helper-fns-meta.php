<?php
/**
 * Meta helper functions definition file.
 *
 * @package eXtended WordPress
 * @subpackage Helper\Functions
 */

use XWP\Helper\Functions as f;

if ( ! function_exists( 'xwp_get_post_by_meta' ) ) :
    /**
     * Gets a post by its meta key and value.
     *
     * @param  string        $meta_key Meta key to search for.
     * @param  mixed         $value    Meta value to search for.
     * @param  'ID'|'object' $retval   Return value type, either 'ID' for post ID or 'object' for WP_Post object.
     * @return ($retval is 'ID ? int<0,max> : null|WP_Post)
     */
    function xwp_get_post_by_meta( string $meta_key, mixed $value, string $retval = 'ID' ): int|WP_Post|null {
        return f\Post::get_by_meta( $meta_key, $value, $retval );
    }
endif;

if ( ! function_exists( 'xwp_get_post_by_type_and_meta' ) ) :
    /**
     * Gets a post by its type and meta key and value.
     *
     * @param  string        $meta_key Meta key to search for.
     * @param  mixed         $value    Meta value to search for.
     * @param  string        $post_type Post type to search for.
     * @param  'ID'|'object' $retval   Return value type, either 'ID' for post ID or 'object' for WP_Post object.
     * @return ($retval is 'ID ? int<0,max> : null|WP_Post)
     */
    function xwp_get_post_by_type_and_meta( string $meta_key, mixed $value, string $post_type, string $retval = 'ID' ): int|WP_Post|null {
        return f\Post::get_by_type_and_meta( $meta_key, $value, $post_type, $retval );
    }
endif;


if ( ! function_exists( 'xwp_get_term_by_meta' ) ) :
    /**
     * Gets a term by its meta key and value.
     *
     * @param  string        $meta_key Meta key to search for.
     * @param  mixed         $value    Meta value to search for.
     * @param  'ID'|'object' $retval   Return value type, either 'ID' for term ID or 'object' for WP_Term object.
     * @return ($retval is 'ID ? int<0,max> : null|WP_Term)
     */
    function xwp_get_term_by_meta( string $meta_key, mixed $value, string $retval = 'ID' ): int|WP_Term|null {
        return f\Term::get_by_meta( $meta_key, $value, $retval );
    }
endif;

if ( ! function_exists( 'xwp_get_term_by_tax_and_meta' ) ) :
    /**
     * Gets a term by its taxonomy, meta key and value.
     *
     * @param  string        $meta_key Meta key to search for.
     * @param  mixed         $value    Meta value to search for.
     * @param  string        $taxonomy Taxonomy to search for.
     * @param  'ID'|'object' $retval   Return value type, either 'ID' for term ID or 'object' for WP_Term object.
     * @return ($retval is 'ID ? int<0,max> : null|WP_Term)
     */
	function xwp_get_term_by_tax_and_meta( string $meta_key, mixed $value, string $taxonomy, string $retval = 'ID' ): int|WP_Term|null {
		return f\Term::get_by_tax_and_meta( $meta_key, $value, $taxonomy, $retval );
	}
endif;
