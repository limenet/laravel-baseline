<?php

namespace Limenet\LaravelBaseline\Backup;

/**
 * Represents an AST node type that couldn't be parsed into a value.
 */
readonly class UnparsedNode
{
    public function __construct(
        public string $nodeType,
    ) {}
}
