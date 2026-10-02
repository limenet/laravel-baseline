<?php

namespace Limenet\LaravelBaseline\Cli;

use Composer\InstalledVersions;
use Limenet\LaravelBaseline\Cli\Command\CheckCommand;
use Limenet\LaravelBaseline\Cli\Command\PeriodicCommand;
use Symfony\Component\Console\Application as ConsoleApplication;

/**
 * `vendor/bin/baseline` — the PHP runner for projects without Laravel. Same
 * checks, same output as the artisan commands, minus everything that needs
 * an application.
 */
final class Application extends ConsoleApplication
{
    public function __construct()
    {
        parent::__construct('baseline', self::version());

        $this->addCommands([
            new CheckCommand,
            new PeriodicCommand,
        ]);
    }

    private static function version(): string
    {
        try {
            return InstalledVersions::getPrettyVersion('limenet/laravel-baseline') ?? 'dev';
        } catch (\OutOfBoundsException) {
            return 'dev';
        }
    }
}
