<?php //phpcs:disable SlevomatCodingStandard.Operators.SpreadOperatorSpacing.IncorrectSpacesAfterOperator

namespace XWP\Helper\Functions;

use Closure;
use WP_Error;
use WP_Term;

/**
 * Taxonomy and term helper functions.
 */
final class Term {
    public static function get_by_tax_and_meta( string $meta_key, mixed $value, string $taxonomy, string $retval = 'ID' ): int|WP_Term|null {
        global $wpdb;

        $res = (int) $wpdb->get_var(
            $wpdb->prepare(
                <<<'SQL'
                SELECT tt.term_id
                FROM %i AS tt
                INNER JOIN %i AS tm
                    ON tt.term_id    = tm.term_id
                    AND tm.meta_key   = %s
                    AND tm.meta_value = %s
                WHERE tt.taxonomy = %s
                LIMIT 1
                SQL,
                $wpdb->term_taxonomy,
                $wpdb->termmeta,
                $meta_key,
                $value,
                $taxonomy,
            ),
        );

        if ( 'ID' === $retval ) {
            return $res;
        }

        return $res ? \get_term( $res ) : null;
    }

    public static function get_by_meta( string $meta_key, mixed $value, string $retval = 'ID' ): int|WP_Term|null {
        global $wpdb;

        $res = (int) $wpdb->get_var(
            $wpdb->prepare(
                <<<'SQL'
                SELECT term_id FROM %i
                WHERE meta_key = %s
                AND meta_value = %s
                LIMIT 1
                SQL,
                $wpdb->termmeta,
                $meta_key,
                $value,
            ),
        );

        if ( 'ID' === $retval ) {
            return $res;
        }

        return $res ? \get_term( $res ) : null;
    }

    /**
     * Formats a term name with its ancestors.
     *
     * @param  WP_Term|int|string|null|array|\WP_Error $term WP_Term object, Term ID, Term slug, or Term name.
     * @param  array<string, mixed>                    $args Formatting arguments. Default empty array.
     *   - formatter (callable) Custom formatter for the displayed term name. Default null.
     *   - count (bool) Whether to include the term count in the formatted name. Default false.
     *   - link_format (string|callable|array|bool) URL Link format for the term link. Default false.
     *   - link_items (bool) Whether to link the term items. Default false.
     *   - link_final (bool) Whether to link the final term. Default true.
     *   - separator (string) Separator between terms. Default ' > '.
     *   - taxonomy (string) Taxonomy name. Default null. Mandatory if $term is a string. Optional otherwise.
     * @return string Formatted term name with ancestors.
     */
    public static function format_hierarhical_name( WP_Term|int|string|null|array|\WP_Error $term, array $args = array() ): string {
        $args = self::parse_format_args( $args );
        $term = self::get_term_object( $term, $args['taxonomy'] ?? null );

        if ( ! $term ) {
            return '';
        }

        $formatter   = self::get_name_formatter( $args );
        $formatted   = \array_map( 'get_term', \get_ancestors( $term->term_id, $term->taxonomy ) );
        $formatted   = \array_map( $formatter, \array_reverse( $formatted ) );
        $formatted[] = $args['link_final'] ? $formatter( $term ) : $term->name;

        $formatted = \implode( $args['separator'], $formatted );

        return $args['show_count'] ? \sprintf( '%s (%d)', $formatted, $term->count ) : $formatted;
    }

    /**
     * Parse the arguments for the term name formatter.
     *
     * @param  array $args The arguments for the term name formatter.
     * @return array
     */
    private static function parse_format_args( array $args ): array {
        $defs = array(
            'formatter'   => null,
            'link_final'  => true,
            'link_format' => true,
            'link_items'  => true,
            'separator'   => ' > ',
            'show_count'  => false,
            'taxonomy'    => null,
        );

        if ( ! ( $args['link_format'] ?? false ) ) {
            $args['link_items'] = false;
            $args['link_final'] = false;
        }

        return \wp_parse_args( $args, $defs );
    }

    /**
     * Get the formatter for the term name.
     *
     * @param  array $args The arguments for the term name formatter.
     * @return Closure
     */
    private static function get_name_formatter( array $args ): Closure {
        if ( \is_callable( $args['formatter'] ?? null ) ) {
            return static fn( $t ) => $args['formatter']( $t );
        }

        $formatter ??= $args['link_items'] && $args['link_format']
            ? self::get_link_formatter( $args['link_format'] )
            : null;

        if ( ! $formatter ) {
            return static fn( WP_Term $term ) => \esc_html( $term->name );
        }

        return static fn( WP_Term $term ) => \sprintf(
            '<a href="%s">%s</a>',
            \esc_url( $formatter( $term ) ),
            \esc_html( $term->name ),
        );
    }

    /**
     * Get the link formatter for the term name.
     *
     * @param  string|callable|array|bool $fmt The link format for the term name.
     * @return Closure|null
     */
    private static function get_link_formatter( string|callable|array|bool $fmt ): ?Closure {
        return match ( true ) {
            \is_bool( $fmt ) && $fmt => static fn( $t ) => \get_term_link( $t ),
            \is_array( $fmt )        => static fn( $t ) => \call_user_func( $fmt, $t ),
            \is_string( $fmt )       => static fn( $t ) => \add_query_arg( $t->taxonomy, $t->slug, $fmt ),
            \is_callable( $fmt )     => static fn( $t ) => $fmt( $t ),
            default                  => null,
        };
    }

    /**
     * Get a term object from a variety of inputs.
     *
     * @param  WP_Term|\WP_Error|int|string|null|array $from The term object, term ID, term slug, term name, or term array.
     * @param  string|null                             $tax The taxonomy name.
     */
    public static function get_term_object( WP_Term|\WP_Error|int|string|null|array $from, ?string $tax = null ): ?WP_Term {
        $term = match ( true ) {
            $from instanceof WP_Term    => $from,
            \is_numeric( $from )        => \get_term( \absint( $from ) ),
            \is_string( $from ) && $tax => \get_term_by( 'slug', $from, $tax ),
            \is_array( $from )          => self::get_term_object_from_array( $from, $tax ),
            \is_wp_error( $from )       => null,
            default                     => null,
        };

        return $term instanceof WP_Term ? $term : null;
    }

    /**
     * Get a term object from an array.
     *
     * @param  array       $arr      The term array.
     * @param  string|null $taxonomy The taxonomy name.
     * @return WP_Term|\WP_Error|null|bool The term object or error.
     */
    private static function get_term_object_from_array( array $arr, ?string $taxonomy = null ): WP_Term|\WP_Error|null|bool {
        $tax = $taxonomy ?? $arr['taxonomy'] ?? $arr['tax'] ?? null;
        $id  = $arr['term_id'] ?? $arr['id'] ?? $arr['ID'] ?? false;

        return match ( true ) {
            false !== $id && 0 > $id    => \get_term_by( 'id', $id, $tax ),
            isset( $arr['slug'], $tax ) => \get_term_by( 'slug', $arr['slug'], $tax ),
            isset( $arr['name'], $tax ) => \get_term_by( 'name', $arr['name'], $tax ),
            default                     => null,
        };
    }
}
