<?php

namespace Limenet\LaravelBaseline\Project;

final class ProfileDetectionException extends \RuntimeException
{
    public static function laravel(string $reason): self
    {
        return new self(
            "This is a Laravel project ({$reason}). Run the baseline through artisan instead: "
            .'`ddev artisan limenet:laravel-baseline:check`.',
        );
    }

    public static function invalidOverride(string $value): self
    {
        return new self(sprintf(
            'Unknown "profile" "%s" in .baseline.json: use "%s" or "%s", or remove the key to detect it.',
            $value,
            Profile::Php->value,
            Profile::WordPress->value,
        ));
    }
}
