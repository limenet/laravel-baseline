<?php

namespace Limenet\LaravelBaseline\Support;

/**
 * One member of an object literal read by {@see JsObjectLiteral}.
 */
final class JsMember
{
    /**
     * @param  int  $start  Offset of the member's first byte.
     * @param  int  $end  Offset just past the member's last byte, before any comma.
     * @param  ?string  $key  The key, or null for a spread, a computed key, a method or an accessor.
     * @param  ?JsNode  $value  The value after the colon, or null for a shorthand member or a keyless one.
     */
    public function __construct(
        public readonly int $start,
        public readonly int $end,
        public readonly ?string $key,
        public readonly ?JsNode $value,
    ) {}
}
