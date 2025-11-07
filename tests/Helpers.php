<?php

use Illuminate\Console\OutputStyle;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer as IlluminateComposer;
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
