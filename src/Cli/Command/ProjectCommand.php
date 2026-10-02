<?php

namespace Limenet\LaravelBaseline\Cli\Command;

use Laravel\Prompts\Prompt;
use Limenet\LaravelBaseline\Project\FilesystemProject;
use Limenet\LaravelBaseline\Project\ProfileDetectionException;
use Limenet\LaravelBaseline\Project\ProfileDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;

/**
 * A command that runs against the project in the working directory (or
 * --cwd), detecting its profile first.
 */
abstract class ProjectCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('cwd', null, InputOption::VALUE_REQUIRED, 'The project root (defaults to the current directory)');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // What Laravel's ConfiguresPrompts does for artisan commands.
        Prompt::setOutput($output);
        Prompt::interactive($input->isInteractive());
    }

    /**
     * The project to run against, or null after reporting why there is none
     * (a missing directory, a Laravel app, an invalid profile override).
     */
    protected function project(InputInterface $input): ?FilesystemProject
    {
        $cwd = $input->getOption('cwd');
        $root = is_string($cwd) ? $cwd : (getcwd() ?: '.');
        $resolved = realpath($root);

        if ($resolved === false || !is_dir($resolved)) {
            error("Project directory \"{$root}\" does not exist.");

            return null;
        }

        try {
            return new FilesystemProject($resolved, ProfileDetector::detect($resolved));
        } catch (ProfileDetectionException $e) {
            error($e->getMessage());

            return null;
        }
    }
}
