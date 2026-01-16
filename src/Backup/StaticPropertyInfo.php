<?php

namespace Limenet\LaravelBaseline\Backup;

/**
 * Represents a static property fetch found in config AST.
 */
readonly class StaticPropertyInfo
{
    public function __construct(
        public string $class,
        public string $property,
    ) {}
}
