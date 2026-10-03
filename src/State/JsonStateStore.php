<?php

namespace Limenet\LaravelBaseline\State;

use DateTimeImmutable;
use DateTimeInterface;
use Limenet\LaravelBaseline\Support\JsonFile;

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

    public function excludeHint(): string
    {
        return 'the <info>excludes</info> in <info>'.self::FILE.'</info>';
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
        JsonFile::write($this->file, $state);
    }
}
