<?php
/**
 * View_Loader trait file
 *
 * @package eXtended WordPress
 * @subpackage Helpers
 */

namespace XWP\Helper\Traits;

use ReflectionClass as R;

/**
 * View loader trait.
 *
 * Add this trait to any class that needs to load php files as views.
 *
 * You can define the FILE constant in the class with `__FILE__` to get the path of the class using the trait.
 * By default - trait will look one folder up for the `Views` folder and look for the PHP file there.
 */
trait View_Loader {
    /**
     * The realpath of the class using the trait.
     *
     * @var string|null
     */
    private ?string $realpath = null;

    /**
     * Get the realpath of the class using the trait.
     *
     * @return string
     */
    private function realpath(): string {
        return $this->realpath ??= match ( true ) {
            \defined( $this::class . '::FILE' ) => \constant( $this::class . '::FILE' ),
            isset( $this->file )                => $this->file,
            default                             => ( new R( $this ) )->getFileName(),
        };
    }

    /**
     * Load the view.
     *
     * @param  string              $file View file path.
     * @param  array<string,mixed> $args View arguments. Will be extracted with `extract`.
     * @return void
     */
    protected function load_view( string $file, array $args = array() ): void {
        $file = \rtrim( $file, '.php' ) . '.php';
        $file = $this->get_template_root() . $file;

        if ( ! \file_exists( $file ) ) {
            return;
        }

        //phpcs:ignore
        \extract( $args );

        include $file;
    }

    /**
     * Get the template root directory.
     *
     * @return string The template root directory.
     */
    protected function get_template_root(): string {
        return \trailingslashit( \dirname( $this->realpath(), 2 ) ) . 'Views/';
    }
}
