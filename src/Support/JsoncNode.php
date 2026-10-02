<?php

namespace Limenet\LaravelBaseline\Support;

/**
 * One value in a document read by {@see Jsonc}, together with the byte range it
 * occupies in the source — which is what lets a fix edit the file in place
 * instead of re-encoding it.
 */
final class JsoncNode
{
    public const OBJECT = 'object';

    public const ARRAY = 'array';

    public const SCALAR = 'scalar';

    /**
     * @param  self::OBJECT|self::ARRAY|self::SCALAR  $kind
     * @param  int  $start  Offset of the value's first byte.
     * @param  int  $end  Offset just past the value's last byte.
     * @param  array<array-key, JsoncNode>  $children  Keyed by name for an object, a list for an array.
     * @param  mixed  $value  The decoded value of a scalar; null otherwise.
     */
    public function __construct(
        public readonly string $kind,
        public readonly int $start,
        public readonly int $end,
        public readonly array $children = [],
        public readonly mixed $value = null,
    ) {}

    /**
     * The member of an object, or null when this is not an object or has no such key.
     */
    public function get(string $key): ?self
    {
        return $this->kind === self::OBJECT ? ($this->children[$key] ?? null) : null;
    }
}
