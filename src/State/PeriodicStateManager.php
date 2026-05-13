<?php

namespace Limenet\LaravelBaseline\State;

use Limenet\LaravelBaseline\PhpFile\PhpFileWriter;

class PeriodicStateManager
{
    public static function getLastRun(string $checkName): ?\DateTimeImmutable
    {
        $config = self::readConfig();

        $timestamp = $config['periodic'][$checkName] ?? null;

        if ($timestamp === null) {
            return null;
        }

        return new \DateTimeImmutable($timestamp);
    }

    public static function setLastRun(string $checkName, \DateTimeImmutable $time): void
    {
        $config = self::readConfig();
        $config['periodic'][$checkName] = $time->format(\DateTimeInterface::ATOM);

        PhpFileWriter::writeConfig(config_path('baseline.php'), $config);
    }

    /** @return array<string,mixed> */
    private static function readConfig(): array
    {
        $path = config_path('baseline.php');

        if (!file_exists($path)) {
            return [];
        }

        $config = require $path;

        return is_array($config) ? $config : [];
    }
}
