<?php
/**
 * Reflection class file.
 *
 * @package eXtended WordPress
 */

namespace XWP\Helper\Classes;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Reflector;

/**
 * Reflection utilities.
 */
class Reflection {
    /**
     * Get the class name for a target.
     *
     * @param  string|object $target The target to get the class name for.
     * @return string
     */
    public static function get_classname( string|object $target ): string {
        if ( \is_string( $target ) ) {
            return $target;
        }

        return $target instanceof ReflectionClass
            ? $target->getName()
            : $target::class;
    }

    /**
     * Get a reflector for the target.
     *
     * @template T of object
     *
     * @param  Reflector|class-string<T>|T|callable|\Closure|array{T, string} $target The target to get a reflector for.
     * @return ReflectionClass<T>|ReflectionMethod|ReflectionFunction
     *
     * @throws \InvalidArgumentException If the target is invalid.
     */
    public static function get_reflector( callable|array|string|object $target ): ReflectionClass|ReflectionFunction|ReflectionMethod {
        return match ( true ) {
            $target instanceof ReflectionClass,
            $target instanceof ReflectionMethod,
            $target instanceof ReflectionFunction => $target,
            static::is_valid_class( $target )     => new ReflectionClass( $target ),
            static::is_valid_method( $target )    => new ReflectionMethod( ...$target ),
            static::is_valid_function( $target )  => new ReflectionFunction( $target ),
            default => throw new \InvalidArgumentException( 'Invalid target' ),
        };
    }

    /**
     * Is the target callable.
     *
     * @template T of object
     *
     * @param  class-string<T>|T|callable|\Closure|array{object, string} $target The target to get a reflector for.
     * @return bool
     */
    public static function is_callable( callable|array|string|object $target ): bool {
        return static::is_valid_method( $target ) || static::is_valid_function( $target );
    }

    /**
     * Is the target a valid class.
     *
     * @template T of object
     *
     * @param  class-string<T>|T|callable|\Closure|array{object, string}|object $target The target to get a reflector for.
     * @return bool
     */
    public static function is_valid_class( callable|array|string|object $target ): bool {
        return \is_object( $target ) || ( \is_string( $target ) && \class_exists( $target ) );
    }

    /**
     * Is the target a valid method.
     *
     * @template T of object
     *
     * @param  class-string<T>|T|callable|\Closure|array{object, string} $target The target to get a reflector for.
     * @return bool
     */
    public static function is_valid_method( callable|array|string|object $target ): bool {
        return \is_array( $target ) && \is_callable( $target );
    }

    /**
     * Is the target a valid function.
     *
     * @template T of object
     *
     * @param  class-string<T>|T|callable|\Closure|array{object, string} $target The target to get a reflector for.
     * @return bool
     */
    public static function is_valid_function( callable|array|string|object $target ): bool {
        return \is_string( $target ) && ( \function_exists( $target ) || \is_callable( $target ) );
    }

    /**
     * Check if a class implements an interface.
     *
     * @param  string|object $thing    The class to check.
     * @param  string        $iname    The interface to check for.
     * @param  bool          $autoload Whether to allow this function to load the class automatically through the __autoload() magic method.
     * @return bool
     */
    public static function class_implements( string|object $thing, string $iname, bool $autoload = true ): bool {
        $cname = static::get_classname( $thing );

        return \class_exists( $cname ) && \in_array( $iname, \class_implements( $thing, $autoload ), true );
    }

    /**
     * Get decorators for a target
     *
     * @template T of object
     * @param  Reflector|class-string<T>|T|callable|\Closure|array{T, string} $target The target to get decorators for.
     * @param  class-string<T>                                                $decorator The decorator to get.
     * @param  int|null                                                       $flags     Flags to pass to getAttributes.
     * @return ReflectionAttribute<T>[]
     */
    public static function get_attributes(
        callable|array|string|object $target,
        string $decorator,
        ?int $flags = ReflectionAttribute::IS_INSTANCEOF,
	): array {
        return static::get_reflector( $target )
            ->getAttributes( $decorator, $flags );
    }

    /**
     * Get decorators for a target
     *
     * @template T of object
     * @param  Reflector|mixed $target    The target to get decorators for.
     * @param  class-string<T> $decorator The decorator to get.
     * @param  int|null        $flags     Flags to pass to getAttributes.
     * @return array<int,T>
     */
    public static function get_decorators(
        mixed $target,
        string $decorator,
        ?int $flags = ReflectionAttribute::IS_INSTANCEOF,
    ): array {
        return \array_map(
            static fn( $att ) => $att->newInstance(),
            static::get_attributes( $target, $decorator, $flags ),
        );
    }

    /**
     * Get decorators for a target class, and its parent classes.
     *
     * @template T of object
     * @param  Reflector|mixed $target The target to get decorators for.
     * @param  class-string<T> $decorator The decorator to get.
     * @param  int|null        $flags     Flags to pass to getAttributes.
     * @return array<int,T>
     */
    public static function get_decorators_deep(
        mixed $target,
        string $decorator,
        ?int $flags = ReflectionAttribute::IS_INSTANCEOF,
    ): array {
        $decorators = array();

        while ( $target ) {
            $decorators = \array_merge(
                $decorators,
                static::get_decorators( $target, $decorator, $flags ),
            );

            $target = $target instanceof ReflectionClass
                ? $target->getParentClass()
                : \get_parent_class( $target );
        }

        return $decorators;
    }

    /**
     * Get a **SINGLE** attribute for a target
     *
     * @template T of object
     * @template K of int
     *
     * @param  Reflector|mixed $target    The target to get decorators for.
     * @param  class-string<T> $decorator The decorator to get.
     * @param  int|null        $flags     Flags to pass to getAttributes.
     * @param  K               $index     The index of the decorator to get.
     * @return ReflectionAttribute<T>|null
     */
    public static function get_attribute(
        mixed $target,
        string $decorator,
        ?int $flags = ReflectionAttribute::IS_INSTANCEOF,
        int $index = 0,
    ): ?ReflectionAttribute {
        return static::get_attributes( $target, $decorator, $flags )[ $index ] ?? null;
    }

    /**
     * Get a **SINGLE** decorator for a target
     *
     * @template T of object
     * @param  Reflector|mixed $target    The target to get decorators for.
     * @param  class-string<T> $decorator The decorator to get.
     * @param  int|null        $flags     Flags to pass to getAttributes.
     * @param  int             $index     The index of the decorator to get.
     * @return T|null
     */
    public static function get_decorator(
        mixed $target,
        string $decorator,
        ?int $flags = ReflectionAttribute::IS_INSTANCEOF,
        int $index = 0,
    ): ?object {
        return static::get_attribute( $target, $decorator, $flags, $index )
            ?->newInstance()
            ?? null;
    }

    /**
     * Get traits used by a class.
     *
     * @param  string|object $target   The class to get the traits for.
     * @param  bool          $autoload Whether to allow this function to load the class automatically through the __autoload() magic method.
     * @return array<class-string,class-string>
     */
    public static function class_uses( string|object $target, bool $autoload = true ): array {
        return \array_reduce(
            \class_uses( $target, $autoload ),
            static fn( $ts, $t ) => $ts + array( $t => $t ) + \class_uses( $t, $autoload ),
            array(),
        );
    }

    /**
     * Get all the traits used by a class.
     *
     * @param  string|object $target Class or object to get the traits for.
     * @param  bool          $autoload        Whether to allow this function to load the class automatically through the __autoload() magic method.
     * @return array<class-string,class-string>
     */
	public static function class_uses_deep( string|object $target, bool $autoload = true ) {
        $target = static::get_classname( $target );
        $traits = array();

        do {
            $traits += static::class_uses( $target, $autoload );
            $target  = \get_parent_class( $target );
        } while ( $target );

        return $traits;
	}

    /**
     * Get the inheritance chain for a class.
     *
     * @template T of object
     *
     * @param  class-string<T>|ReflectionClass<T>|T $target    The class to get the inheritance chain for.
     * @param  bool                                 $inclusive Whether to include the target class in the chain.
     * @return array<class-string>
     */
    public static function get_inheritance_chain( string|object $target, bool $inclusive = false ): array {
        $target = static::get_classname( $target );
        $chain  = $inclusive ? array( $target ) : array();

        //phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
        while ( $target = \get_parent_class( $target ) ) {
            $chain[] = $target;
        }

        return $chain;
    }
}
