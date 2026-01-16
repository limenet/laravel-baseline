<?php

use Illuminate\Console\OutputStyle;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer as IlluminateComposer;
use Limenet\LaravelBaseline\Checks\CheckInterface;
use Limenet\LaravelBaseline\Checks\CommentCollector;
use Limenet\LaravelBaseline\Commands\LaravelBaselineCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Helper: create a LaravelBaselineCommand with initialized Output and container.
 */
function makeCommand(): LaravelBaselineCommand
{
    /** @var \Illuminate\Contracts\Foundation\Application $app */
    $app = app();

    $command = new LaravelBaselineCommand();
    $command->setLaravel($app);
    $output = new OutputStyle(new ArrayInput([]), new BufferedOutput());
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
    return new $checkClass(new CommentCollector());
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
    $collector = new CommentCollector();

    return [new $checkClass($collector), $collector];
}

/**
 * Helper: bind a fake Composer instance with predefined package availability map.
 *
 * @param  array<string,bool>  $map
 */
function bindFakeComposer(array $map): void
{
    $app = app();

    $fake = new class(new Filesystem(), $map) extends IlluminateComposer
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
