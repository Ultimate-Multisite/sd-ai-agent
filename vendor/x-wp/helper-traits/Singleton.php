<?php
/**
 * Singleton_Trait trait.
 *
 * @package WP Polyfills
 * @subpackage Traits
 */

namespace XWP\Helper\Traits;

/**
 * Enables the singleton pattern.
 */
trait Singleton {
    /**
     * Class instance
     *
     * @var static|null
     */
    protected static $instance = null;

    /**
     * Returns the singleton instance
     *
     * @return static
     */
    public static function instance(): static {
        return static::$instance ??= new static();
    }

    /**
     * Disallow cloning
     */
    final public function __clone() {
        \_doing_it_wrong( __FUNCTION__, 'Cloning is disabled', 'XWP Utils' );
    }

    /**
     * Disallow serialization
     */
    final public function __wakeup() {
        \_doing_it_wrong( __FUNCTION__, 'Unserializing is disabled', 'XWP Utils' );
    }
}
