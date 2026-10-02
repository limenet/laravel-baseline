<?php

namespace Limenet\LaravelBaseline\State;

use DateTimeImmutable;
use DateTimeInterface;
use Limenet\LaravelBaseline\PhpFile\PhpFileWriter;

/**
 * config/baseline.php. Excludes a run honours go through config() so the
 * consumer's published file merges with the package default; everything else
 * reads the file with require, bypassing the config cache, because the
 * periodic command and the exclude cleanup rewrite it mid-run.
 */
final class PhpConfigStateStore implements StateStore
{
    public function location(): string
    {
        return 'config/baseline.php';
    }

    public function excludeHint(): string
    {
        return 'the <info>baseline.excludes</info> config';
    }

    public function excludes(): array
    {
        $excludes = config('baseline.excludes', []);

        return is_array($excludes) ? array_values(array_filter($excludes, is_string(...))) : [];
    }

    public function storedExcludes(): array
    {
        $excludes = $this->read()['excludes'] ?? [];

        return is_array($excludes) ? $excludes : [];
    }

    public function setExcludes(array $excludes): void
    {
        $config = $this->read();
        $config['excludes'] = $excludes;

        PhpFileWriter::writeConfig($this->file(), $config);
    }

    public function lastRun(string $checkName): ?DateTimeImmutable
    {
        $periodic = $this->read()['periodic'] ?? [];
        $timestamp = is_array($periodic) ? ($periodic[$checkName] ?? null) : null;

        return is_string($timestamp) ? new DateTimeImmutable($timestamp) : null;
    }

    public function recordRun(string $checkName, DateTimeImmutable $time): void
    {
        $config = $this->read();
        $periodic = is_array($config['periodic'] ?? null) ? $config['periodic'] : [];
        $periodic[$checkName] = $time->format(DateTimeInterface::ATOM);
        $config['periodic'] = $periodic;

        PhpFileWriter::writeConfig($this->file(), $config);
    }

    private function file(): string
    {
        return config_path('baseline.php');
    }

    /** @return array<string,mixed> */
    private function read(): array
    {
        $path = $this->file();

        if (!file_exists($path)) {
            return [];
        }

        $config = require $path;

        return is_array($config) ? $config : [];
    }
}
