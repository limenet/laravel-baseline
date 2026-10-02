<?php

use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Limenet\LaravelBaseline\Cli\Application;
use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Runner\PeriodicRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

// An artisan command run earlier in the same process leaves Laravel's prompt
// fallbacks registered (ConfiguresPrompts), pointing at that command's output.
// The standalone binary never shares a process with Laravel, so reset them.
beforeEach(function (): void {
    (function (): void {
        static::$shouldFallback = false;
        static::$fallbacks = [];
    })->bindTo(null, Prompt::class)();
});

function standaloneCommand(string $name): CommandTester
{
    return new CommandTester((new Application)->find($name));
}

it('registers the standalone commands', function (): void {
    $application = new Application;

    expect($application->has('check'))->toBeTrue()
        ->and($application->has('periodic'))->toBeTrue();
});

it('checks a WordPress theme under the wordpress profile', function (): void {
    $project = makeProject(Profile::Php, [
        'composer.json' => json_encode(['name' => 'acme/theme']),
        'style.css' => "/*\nTheme Name: Acme\n*/\n",
    ]);

    $tester = standaloneCommand('check');
    $exitCode = $tester->execute(['--cwd' => $project->path()], ['interactive' => false]);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('Running baseline checks (wordpress)')
        ->toContain('To exclude, add bumpsComposer to the excludes in .baseline.json')
        ->not->toContain('uses Laravel Horizon');
});

it('honours excludes from .baseline.json', function (): void {
    $project = makeProject(Profile::Php, [
        'composer.json' => json_encode(['name' => 'acme/lib']),
        '.baseline.json' => json_encode(['excludes' => ['bumpsComposer']]),
    ]);

    $tester = standaloneCommand('check');
    $tester->execute(['--cwd' => $project->path()], ['interactive' => false]);

    expect($tester->getDisplay())->toContain('⚪ bumps Composer (excluded)')
        ->toContain('Running baseline checks (php)');
});

it('fixes what it can with --fix', function (): void {
    $project = makeProject(Profile::Php, ['composer.json' => json_encode(['name' => 'acme/lib'])]);

    standaloneCommand('check')->execute(['--cwd' => $project->path(), '--fix' => true], ['interactive' => false]);

    expect(file_get_contents($project->path('.editorconfig')))->toStartWith('root = true')
        ->and(json_decode((string) file_get_contents($project->path('composer.json')), true)['scripts']['post-update-cmd'])
        ->toContain('@php vendor/bin/baseline check --fix');
});

it('refuses to run in a Laravel application', function (string $command): void {
    $project = makeProject(Profile::Php, ['artisan' => '#!/usr/bin/env php']);

    $tester = standaloneCommand($command);

    expect($tester->execute(['--cwd' => $project->path()], ['interactive' => false]))->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('This is a Laravel project (it has an artisan file)');
})->with(['check', 'periodic']);

it('fails on a project directory that does not exist', function (): void {
    $tester = standaloneCommand('check');

    expect($tester->execute(['--cwd' => '/does/not/exist'], ['interactive' => false]))->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('does not exist');
});

it('lists expired periodic checks without recording them when not interactive', function (): void {
    $project = makeProject(Profile::Php, ['composer.json' => json_encode(['name' => 'acme/lib'])]);

    $tester = standaloneCommand('periodic');
    $exitCode = $tester->execute(['--cwd' => $project->path()], ['interactive' => false]);

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('updates Dependencies')->toContain('Skipped')
        ->and(file_exists($project->path('.baseline.json')))->toBeFalse();
});

it('records a confirmed periodic check in .baseline.json', function (): void {
    $project = makeProject(Profile::Php, ['composer.json' => json_encode(['name' => 'acme/lib'])]);

    Prompt::fake(['y', Key::ENTER]);

    (new PeriodicRunner($project, new BufferedOutput))->run();

    expect(json_decode((string) file_get_contents($project->path('.baseline.json')), true)['periodic'])
        ->toHaveKey('updatesDependencies');
});

it('installs the skills with install-skills', function (): void {
    $project = makeProject(Profile::Php);

    $tester = standaloneCommand('install-skills');

    expect($tester->execute(['--cwd' => $project->path()], ['interactive' => false]))->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('3 skill(s) installed, 0 left untouched.')
        ->and(is_file($project->path('.claude/skills/updating-dependencies/SKILL.md')))->toBeTrue();
});

it('syncs the skills on check --fix', function (): void {
    $project = makeProject(Profile::Php, ['composer.json' => json_encode(['name' => 'acme/lib'])]);

    $tester = standaloneCommand('check');
    $tester->execute(['--cwd' => $project->path(), '--fix' => true], ['interactive' => false]);

    expect($tester->getDisplay())->toContain('🔧 Skill installed: .claude/skills/updating-dependencies/SKILL.md');
});
