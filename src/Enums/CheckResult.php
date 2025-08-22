<?php

namespace Limenet\LaravelBaseline\Enums;

enum CheckResult: string
{
    case PASS = 'pass';
    case FAIL = 'fail';
    case WARN = 'warn';

    public function icon(): string
    {
        return match ($this) {
            self::PASS => '✅',
            self::FAIL => '❌',
            self::WARN => '⚠️',
        };
    }

    public function isError(): bool
    {
        return match ($this) {
            self::FAIL => true,
            default => false,
        };
    }
}
