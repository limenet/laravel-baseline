<?php

namespace Limenet\LaravelBaseline\State;

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

        file_put_contents(
            config_path('baseline.php'),
            "<?php\n\nreturn " . var_export($config, true) . ";\n",
        );
    }

    /** @return array<string,mixed> */
    private static function readConfig(): array
    {
        $path = config_path('baseline.php');

        if (! file_exists($path)) {
            return [];
        }

        $config = require $path;

        return is_array($config) ? $config : [];
    }
}
