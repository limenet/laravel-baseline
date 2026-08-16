<?php

use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer as IlluminateComposer;
use Limenet\LaravelBaseline\Checks\CheckInterface;
use Limenet\LaravelBaseline\Checks\CommentCollector;
use Limenet\LaravelBaseline\Commands\CheckCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function makeCommand(): CheckCommand
{
    /** @var Application $app */
    $app = app();

    $command = new CheckCommand;
    $command->setLaravel($app);
    $output = new OutputStyle(new ArrayInput([]), new BufferedOutput);
    $command->setOutput($output);

    return $command;
}

/**
 * Helper: create a check instance with a comment collector.
 *
 * @template T of CheckInterface
 *
 * @param  class-string<T>  $checkClass
 * @return T
 */
function makeCheck(string $checkClass): CheckInterface
{
    return new $checkClass(new CommentCollector);
}

/**
 * Helper: create a check instance with a shared comment collector.
 * Returns both the check and the collector for tests that need to inspect comments.
 *
 * @template T of CheckInterface
 *
 * @param  class-string<T>  $checkClass
 * @return array{0: T, 1: CommentCollector}
 */
function makeCheckWithCollector(string $checkClass): array
{
    $collector = new CommentCollector;

    return [new $checkClass($collector), $collector];
}

/**
 * Helper: temp base path for the Pulse cache serializable-classes check, which needs
 * both a faked composer package and a real composer.json version constraint
 * (composerPackageSatisfies() reads require, not Composer::hasPackage()).
 *
 * @param  array<string, string>  $files
 */
function pulseCacheBasePath(object $test, array $files, string $laravel = '^13.0'): void
{
    $test->withTempBasePath([
        'composer.json' => json_encode([
            'name' => 'tmp/app',
            'require' => ['laravel/framework' => $laravel],
        ]),
        ...$files,
    ]);
}

/**
 * Helper: a config/cache.php resembling the Laravel skeleton, with an injectable
 * serializable_classes value and optional use statements.
 */
function pulseCacheConfig(string $serializableClasses, string $uses = ''): string
{
    return <<<PHP
    <?php

    {$uses}
    return [

        /*
        |--------------------------------------------------------------------------
        | Default Cache Store
        |--------------------------------------------------------------------------
        */

        'default' => env('CACHE_STORE', 'database'),

        'stores' => [
            'array' => [
                'driver' => 'array',
                'serialize' => false,
            ],
        ],

        'serializable_classes' => {$serializableClasses},

    ];
    PHP;
}

/**
 * Helper: bind a fake Composer instance with predefined package availability map.
 *
 * @param  array<string,bool>  $map
 */
function bindFakeComposer(array $map): void
{
    $app = app();

    $fake = new class(new Filesystem, $map) extends IlluminateComposer
    {
        /** @var array<string,bool> */
        private array $map;

        public function __construct(Filesystem $files, array $map)
        {
            parent::__construct($files);
            $this->map = $map;
        }

        public function setWorkingPath($path)
        {
            return $this;
        }

        public function hasPackage($package)
        {
            return $this->map[$package] ?? false;
        }
    };

    $app->bind(IlluminateComposer::class, fn () => $fake);
}
