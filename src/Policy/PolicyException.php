<?php

namespace Limenet\LaravelBaseline\Policy;

class PolicyException extends \RuntimeException
{
    public static function missing(string $key): self
    {
        return new self("Policy key \"{$key}\" is not defined in policy/policy.json");
    }

    public static function wrongType(string $key, string $expected): self
    {
        return new self("Policy key \"{$key}\" is not a {$expected}");
    }

    public static function unreadable(string $path): self
    {
        return new self("Policy file \"{$path}\" is missing or unreadable. It ships with the package — check that policy/ is not excluded from the distribution archive.");
    }
}
