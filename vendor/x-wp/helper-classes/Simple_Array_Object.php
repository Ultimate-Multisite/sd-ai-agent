<?php
/**
 * Simple_Array_Object class file.
 *
 * @package eXtended WordPress
 * @subpackage Helper\Classes
 */

namespace XWP\Helper\Classes;

use XWP\Helper\Traits\Array_Access;

/**
 * Allows a class to be accessed as an array.
 *
 * @template TKey
 * @template TValue
 * @template-covariant TValue
 * @template-implements \ArrayAccess<TKey, TValue>, \Iterator<TKey, TValue>, \Countable, \JsonSerializable
 */
class Simple_Array_Object implements \ArrayAccess, \Iterator, \Countable, \JsonSerializable {
    use Array_Access;
}
