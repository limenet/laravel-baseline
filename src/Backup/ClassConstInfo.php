<?php

namespace Limenet\LaravelBaseline\Backup;

/**
 * Represents a class constant fetch found in config AST.
 */
readonly class ClassConstInfo
{
    public function __construct(
        public string $class,
        public string $constant,
    ) {}
}
