<?php

namespace XWP\Helper\Functions;

use WP_Post;

class Post {
    public static function get_by_type_and_meta( string $meta_key, mixed $value, string $post_type, string $retval = 'ID' ): int|WP_Post|null {
        global $wpdb;
        $res = (int) $wpdb->get_var(
            $wpdb->prepare(
                <<<'SQL'
                SELECT p.ID FROM %i AS p
                INNER JOIN %i AS pm
                    ON p.ID = pm.post_id
                    AND pm.meta_key = %s
                    AND pm.meta_value = %s
                WHERE p.post_type = %s
                LIMIT 1
                SQL,
                $wpdb->posts,
                $wpdb->postmeta,
                $meta_key,
                $value,
                $post_type,
            ),
        );

        if ( 'ID' === $retval ) {
            return $res;
        }

        return $res ? \get_post( $res ) : null;
    }

    public static function get_by_meta( string $meta_key, mixed $value, string $retval = 'ID' ): int|WP_Post|null {
        global $wpdb;

        $res = (int) $wpdb->get_var(
            $wpdb->prepare(
                <<<'SQL'
                SELECT post_id FROM %i
                WHERE meta_key = %s
                AND meta_value = %s
                LIMIT 1
                SQL,
                $wpdb->postmeta,
                $meta_key,
                $value,
            ),
        );

        if ( 'ID' === $retval ) {
            return $res;
        }

        return $res ? \get_post( $res ) : null;
    }
}
