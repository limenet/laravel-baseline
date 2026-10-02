<?php

namespace Limenet\LaravelBaseline\State;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * .baseline.json — the same file and shape the npm runner uses, since a
 * project without Laravel has no config/ directory to write a PHP file into.
 * Writes keep every key they do not own (e.g. "profile").
 */
final class JsonStateStore implements StateStore
{
    public const FILE = '.baseline.json';

    public function __construct(private readonly string $file) {}

    public function location(): string
    {
        return self::FILE;
    }

    public function excludes(): array
    {
        return array_values(array_filter($this->storedExcludes(), is_string(...)));
    }

    public function storedExcludes(): array
    {
        $excludes = $this->read()['excludes'] ?? [];

        return is_array($excludes) ? $excludes : [];
    }

    public function setExcludes(array $excludes): void
    {
        $state = $this->read();
        $state['excludes'] = $excludes;

        $this->write($state);
    }

    public function lastRun(string $checkName): ?DateTimeImmutable
    {
        $periodic = $this->read()['periodic'] ?? [];
        $timestamp = is_array($periodic) ? ($periodic[$checkName] ?? null) : null;

        return is_string($timestamp) ? new DateTimeImmutable($timestamp) : null;
    }

    public function recordRun(string $checkName, DateTimeImmutable $time): void
    {
        $state = $this->read();
        $periodic = is_array($state['periodic'] ?? null) ? $state['periodic'] : [];
        $periodic[$checkName] = $time->format(DateTimeInterface::ATOM);
        $state['periodic'] = $periodic;

        $this->write($state);
    }

    /**
     * The raw decoded file, or [] when it is absent. Invalid JSON throws:
     * silently treating it as empty would drop every exclude on the next write.
     *
     * @return array<string,mixed>
     */
    public function read(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($this->file), true, flags: JSON_THROW_ON_ERROR);

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string,mixed>  $state
     */
    private function write(array $state): void
    {
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        // json_encode pretty-prints with 4 spaces, matching js/src/project.ts writeJson().
        file_put_contents($this->file, $json."\n");
    }
}
