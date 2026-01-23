<?php

use Illuminate\Console\Command;
use Limenet\LaravelBaseline\Checks\CheckInterface;
use Limenet\LaravelBaseline\Checks\CheckRegistry;
use Limenet\LaravelBaseline\Checks\CommentCollector;
use Limenet\LaravelBaseline\Commands\LaravelBaselineCommand;

it('registers all check classes that implement CheckInterface', function (): void {
    $registeredChecks = CheckRegistry::all();

    // Verify all registered classes implement CheckInterface
    foreach ($registeredChecks as $checkClass) {
        expect(is_a($checkClass, CheckInterface::class, true))->toBeTrue(
            "Class {$checkClass} must implement CheckInterface",
        );
    }

    expect($registeredChecks)->not->toBeEmpty();
})->group('command');

it('all check classes have unique names', function (): void {
    $names = array_map(
        fn (string $class) => $class::name(),
        CheckRegistry::all(),
    );

    $uniqueNames = array_unique($names);

    expect(count($names))->toBe(count($uniqueNames));
})->group('command');

it('has correct command signature', function (): void {
    $command = new LaravelBaselineCommand();
    expect($command->signature)->toBe('limenet:laravel-baseline:check');
})->group('command');

it('has correct command description', function (): void {
    $command = new LaravelBaselineCommand();
    expect($command->description)->toBe('Checks the project against a highly opinionated set of coding standards.');
})->group('command');

it('has expected number of checks registered', function (): void {
    expect(CheckRegistry::all())->toHaveCount(36);
})->group('command');

it('createAll returns check instances with shared comment collector', function (): void {
    $collector = new CommentCollector();
    $checks = CheckRegistry::createAll($collector);

    expect($checks)->toHaveCount(36);
    expect($checks[0])->toBeInstanceOf(CheckInterface::class);
})->group('command');

it('command handle returns failure when checks fail', function (): void {
    bindFakeComposer([]);

    $gitlabCi = <<<'YML'
build:
  extends: [.build]
php:
  extends: [.lint_php]
js:
  extends: [.lint_js]
test:
  extends: [.test]
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.gitlab-ci.yml' => $gitlabCi,
    ]);

    $result = $this->artisan('limenet:laravel-baseline:check');

    $result->assertExitCode(Command::FAILURE);
})->group('command');

it('command displays excluded checks when configured', function (): void {
    bindFakeComposer([]);

    $gitlabCi = <<<'YML'
build:
  extends: [.build]
php:
  extends: [.lint_php]
js:
  extends: [.lint_js]
test:
  extends: [.test]
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.gitlab-ci.yml' => $gitlabCi,
    ]);

    config(['baseline.excludes' => ['bumpsComposer']]);

    $result = $this->artisan('limenet:laravel-baseline:check');

    $result->expectsOutputToContain('bumps Composer (excluded)');
})->group('command');

it('command displays check names with verbose output', function (): void {
    bindFakeComposer([]);

    $gitlabCi = <<<'YML'
build:
  extends: [.build]
php:
  extends: [.lint_php]
js:
  extends: [.lint_js]
test:
  extends: [.test]
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.gitlab-ci.yml' => $gitlabCi,
    ]);

    $result = $this->artisan('limenet:laravel-baseline:check', ['-v' => true]);

    $result->assertExitCode(Command::FAILURE);
})->group('command');

it('command displays comments with very verbose output', function (): void {
    bindFakeComposer([]);

    $gitlabCi = <<<'YML'
build:
  extends: [.build]
php:
  extends: [.lint_php]
js:
  extends: [.lint_js]
test:
  extends: [.test]
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.gitlab-ci.yml' => $gitlabCi,
    ]);

    $result = $this->artisan('limenet:laravel-baseline:check', ['-vv' => true]);

    $result->assertExitCode(Command::FAILURE);
})->group('command');
