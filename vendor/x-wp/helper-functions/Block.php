<?php

namespace XWP\Helper\Functions;

class Block {
    /**
     * Deregisters all registered blocks.
     */
    public static function deregister_all() {
        if ( \did_action( 'init' ) && ! \doing_action( 'init' ) ) {
            \_doing_it_wrong( __FUNCTION__, 'You need to call this function on init', '1.0.0' );
            return;
        }

        $btr = \WP_Block_Type_Registry::get_instance();
        return \array_filter(
            \array_keys( $btr->get_all_registered() ),
            array( $btr, 'unregister' ),
        );
    }
}
