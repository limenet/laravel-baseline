<?php

namespace Limenet\LaravelBaseline\State;

use DateTimeImmutable;

/**
 * Where a project keeps its excludes and the periodic checks' last-run times:
 * config/baseline.php in a Laravel app, .baseline.json everywhere else.
 */
interface StateStore
{
    /**
     * The project-relative file, for messages that tell the developer where to look.
     */
    public function location(): string;

    /**
     * The excludes a run honours.
     *
     * @return list<string>
     */
    public function excludes(): array;

    /**
     * The excludes exactly as the file holds them, for the check that cleans
     * them up — including entries that are not even strings.
     *
     * @return array<mixed>
     */
    public function storedExcludes(): array;

    /**
     * @param  list<string>  $excludes
     */
    public function setExcludes(array $excludes): void;

    public function lastRun(string $checkName): ?DateTimeImmutable;

    public function recordRun(string $checkName, DateTimeImmutable $time): void;
}
