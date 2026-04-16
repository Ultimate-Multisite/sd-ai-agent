<?php //phpcs:disable WordPress.NamingConventions
namespace XWP\Helper\Traits;

/**
 * Allows a class to be accessed as an array.
 *
 * @template TKey
 * @template TValue
 *
 * @template-implements \ArrayAccess<TKey, TValue>
 * @template-implements \Iterator<TKey, TValue>
 * @template-implements \Countable
 * @template-implements \JsonSerializable
 *
 * @phpstan-require-implements \ArrayAccess<TKey, TValue>
 * @phpstan-require-implements \Iterator<TKey, TValue>
 * @phpstan-require-implements \Countable
 * @phpstan-require-implements \JsonSerializable
 */
trait Array_Access {
    /**
     * Traversible data array.
     *
     * @var array<TKey, TValue>
     */
    protected array $arr_data = array();

    /**
     * Array of keys for the data array.
     *
     * @var array<int,TKey>
     */
    protected array $arr_data_keys = array();

    /**
     * Current iterator position.
     *
     * @var int
     */
    protected int $arr_position = 0;

    /**
     * Counts the number of items in the data array.
     *
     * Used by the Countable interface.
     *
     * @return int<0, max>
     */
    public function count(): int {
        return \count( $this->arr_data_keys );
    }

    /**
     * Returns the current item in the data array.
     *
     * Used by the Iterator interface.
     *
     * @return TValue Can return any type.
     */
    public function current(): mixed {
        return $this->arr_data[ $this->arr_data_keys[ $this->arr_position ] ];
    }

    /**
     * Returns the key of the current item in the data array.
     *
     * Used by the Iterator interface.
     *
     * @return TKey|null TKey on success, or null on failure.
     */
    public function key(): mixed {
        return $this->arr_data_keys[ $this->arr_position ];
    }

    /**
     * Moves the iterator to the next item in the data array.
     *
     * Used by the Iterator interface.
     *
     * @return void
     */
    public function next(): void {
        ++$this->arr_position;
    }

    /**
     * Resets the iterator to the first item in the data array.
     *
     * Used by the Iterator interface.
     *
     * @return void
     */
    public function rewind(): void {
        $this->arr_position = 0;
    }

    /**
     * Checks if the current iterator position is valid.
     *
     * Used by the Iterator interface.
     *
     * @return bool
     */
    public function valid(): bool {
        return isset( $this->arr_data_keys[ $this->arr_position ] );
    }

    /**
     * Assigns a value to the specified offset.
     *
     * Used by the ArrayAccess interface.
     *
     * @param TKey   $offset The offset to assign the value to.
     * @param TValue $value The value to set.
     * @return void
     */
    public function offsetSet( $offset, $value ): void {
        if ( \is_null( $offset ) ) {
            $this->arr_data[]      = $value;
            $this->arr_data_keys[] = \array_key_last( $this->arr_data );

            return;
        }

        $this->arr_data[ $offset ] = $value;

        if ( \in_array( $offset, $this->arr_data_keys, true ) ) {
            return;
        }

        $this->arr_data_keys[] = $offset;
    }

    /**
     * Returns the value at the specified offset.
     *
     * Used by the ArrayAccess interface.
     *
     * @param TKey $offset The offset to retrieve.
     * @return TValue Can return any type.
     */
    public function &offsetGet( $offset ): mixed {
        $this->arr_data[ $offset ] ??= array();

        return $this->arr_data[ $offset ];
	}

    /**
     * Checks if the specified offset exists.
     *
     * Used by the ArrayAccess interface.
     *
     * @param TKey $offset The offset to check.
     * @return bool
     */
    public function offsetExists( $offset ): bool {
        return isset( $this->arr_data[ $offset ] );
    }

    /**
     * Unsets the value at the specified offset.
     *
     * Used by the ArrayAccess interface.
     *
     * @param TKey $offset The offset to unset.
     * @return void
     */
    public function offsetUnset( $offset ): void {
        unset( $this->arr_data[ $offset ] );
        unset( $this->arr_data_keys[ \array_search( $offset, $this->arr_data_keys, true ) ] );

        $this->arr_data_keys = \array_values( $this->arr_data_keys );
    }

    /**
     * Returns the data array as an array.
     *
     * @return array<TKey, TValue>
     */
    public function __debugInfo() {
        return $this->arr_data;
    }

    /**
     * Serialize the data array.
     *
     * @return array<TKey, TValue>
     */
    public function __serialize() {
        return $this->arr_data;
    }

    /**
     * Unserialize the data array.
     *
     * @param array<TKey, TValue> $data The data to unserialize.
     * @return void
     */
    public function __unserialize( $data ) {
        foreach ( $data as $k => $v ) {
            $this[ $k ] = $v;
        }
    }

    /**
     * Json Serialize the data array.
     *
     * @return array<TKey, TValue>
     */
    public function jsonSerialize(): mixed {
        return $this->arr_data;
    }

    /**
     * Set the state of the object.
     *
     * @param array{arr_data: array<TKey, TValue>} $data The data to set.
     * @return static
     */
    public static function __set_state( array $data ): static {
        $obj = new static();

        foreach ( $data['arr_data'] as $k => $v ) {
            $obj[ $k ] = $v;
        }

        return $obj;
    }
}
