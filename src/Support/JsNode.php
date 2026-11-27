<?php

namespace Limenet\LaravelBaseline\Support;

/**
 * One value in a config read by {@see JsObjectLiteral}, together with the byte
 * range it occupies in the source.
 */
final class JsNode
{
    public const OBJECT = 'object';

    public const ARRAY = 'array';

    public const STRING = 'string';

    /** Anything the scanner does not look into: a call, a variable, a spread, a number. */
    public const OTHER = 'other';

    /**
     * @param  self::OBJECT|self::ARRAY|self::STRING|self::OTHER  $kind
     * @param  int  $start  Offset of the value's first byte.
     * @param  int  $end  Offset just past the value's last byte.
     * @param  list<JsMember>  $members  The members of an object, in source order.
     * @param  list<JsNode>  $elements  The elements of an array, in source order.
     * @param  ?string  $value  The decoded value of a string; null otherwise.
     * @param  ?string  $quote  The quote character a string was written with.
     */
    public function __construct(
        public readonly string $kind,
        public readonly int $start,
        public readonly int $end,
        public readonly array $members = [],
        public readonly array $elements = [],
        public readonly ?string $value = null,
        public readonly ?string $quote = null,
    ) {}

    /**
     * Every member written under a plain key of that name.
     *
     * @return list<JsMember>
     */
    public function membersNamed(string $key): array
    {
        return array_values(array_filter($this->members, static fn (JsMember $member): bool => $member->key === $key));
    }

    /**
     * Whether the object has a member whose key cannot be read off the source —
     * a spread or a computed key — and which might therefore define any key.
     */
    public function hasOpaqueMembers(): bool
    {
        foreach ($this->members as $member) {
            if ($member->key === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * The byte range of each member or element.
     *
     * @return list<array{int, int}>
     */
    public function spans(): array
    {
        return $this->kind === self::OBJECT
            ? array_map(static fn (JsMember $member): array => [$member->start, $member->end], $this->members)
            : array_map(static fn (JsNode $element): array => [$element->start, $element->end], $this->elements);
    }
}
