<?php

namespace Limenet\LaravelBaseline\State;

/**
 * Static access to config/baseline.php's periodic state, kept for callers that
 * predate StateStore. New code asks the project for its state() instead.
 */
class PeriodicStateManager
{
    public static function getLastRun(string $checkName): ?\DateTimeImmutable
    {
        return (new PhpConfigStateStore)->lastRun($checkName);
    }

    public static function setLastRun(string $checkName, \DateTimeImmutable $time): void
    {
        (new PhpConfigStateStore)->recordRun($checkName, $time);
    }
}
