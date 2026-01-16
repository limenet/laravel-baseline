<?php

namespace Limenet\LaravelBaseline\Backup;

/**
 * Represents a function call found in config AST.
 */
readonly class FuncCallInfo
{
    /**
     * @param  list<mixed>  $args
     */
    public function __construct(
        public string $name,
        public array $args,
    ) {}

    /**
     * Check if this is a specific function with expected first argument.
     */
    public function isCall(string $funcName, ?string $expectedFirstArg = null): bool
    {
        if ($this->name !== $funcName) {
            return false;
        }

        return !($expectedFirstArg !== null && ($this->args[0] ?? null) !== $expectedFirstArg);
    }

    /**
     * Get the first argument value.
     */
    public function getFirstArg(): mixed
    {
        return $this->args[0] ?? null;
    }

    /**
     * Get the second argument value (often the default for env()).
     */
    public function getSecondArg(): mixed
    {
        return $this->args[1] ?? null;
    }
}
