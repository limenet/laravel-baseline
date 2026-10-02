<?php

use Limenet\LaravelBaseline\Checks\Checks\RectorCoversAllPhpFilesCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Project\Project;

function rectorCoverageProject(string $chain, array $files = []): Project
{
    return makeProject(Profile::WordPress, [
        'rector.php' => "<?php\n\nuse Rector\\Config\\RectorConfig;\n\nreturn RectorConfig::configure(){$chain};\n",
        'functions.php' => "<?php\n",
        'app/index.php' => "<?php\n",
        'vendor/acme/lib.php' => "<?php\n",
        ...$files,
    ]);
}

it('rectorCoversAllPhpFiles passes when paths and root files cover everything', function (string $chain): void {
    expect(makeCheck(RectorCoversAllPhpFilesCheck::class, rectorCoverageProject($chain))->check())->toBe(CheckResult::PASS);
})->with([
    'dir paths + root files' => ["\n    ->withPaths([__DIR__ . '/app'])\n    ->withRootFiles()"],
    'whole project' => ["\n    ->withPaths([__DIR__])"],
    'listed files' => ["\n    ->withPaths([__DIR__ . '/app', __DIR__ . '/functions.php'])"],
    'relative strings' => ["\n    ->withPaths(['app', 'functions.php'])"],
    'skipped deliberately' => ["\n    ->withPaths([__DIR__ . '/app'])\n    ->withSkip([__DIR__ . '/functions.php'])"],
]);

it('rectorCoversAllPhpFiles reads the legacy closure config', function (): void {
    $project = makeProject(Profile::Php, [
        'rector.php' => "<?php\n\nreturn static function (Rector\\Config\\RectorConfig \$rectorConfig): void {\n    \$rectorConfig->paths([__DIR__ . '/src']);\n};\n",
        'src/A.php' => "<?php\n",
    ]);

    expect(makeCheck(RectorCoversAllPhpFilesCheck::class, $project)->check())->toBe(CheckResult::PASS);
});

it('rectorCoversAllPhpFiles does not treat a rule skip as a path skip', function (): void {
    $project = rectorCoverageProject("\n    ->withPaths([__DIR__ . '/app'])\n    ->withSkip([SomeRector::class => [__DIR__ . '/functions.php']])");

    expect(makeCheck(RectorCoversAllPhpFilesCheck::class, $project)->check())->toBe(CheckResult::FAIL);
});

it('rectorCoversAllPhpFiles adds withRootFiles when only root files are missing', function (): void {
    $project = rectorCoverageProject("\n    ->withPaths([__DIR__ . '/app'])");

    [$check, $collector] = makeCheckWithCollector(RectorCoversAllPhpFilesCheck::class, $project);

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toContain('Rector does not cover functions.php: add ->withRootFiles() to rector.php')
        ->and($check->fix())->toBe(CheckResult::PASS)
        ->and(file_get_contents($project->path('rector.php')))->toContain('->withRootFiles()');
});

it('rectorCoversAllPhpFiles leaves an uncovered directory to the developer', function (): void {
    $project = rectorCoverageProject("\n    ->withPaths([__DIR__ . '/app'])\n    ->withRootFiles()", ['inc/setup.php' => "<?php\n"]);
    $before = file_get_contents($project->path('rector.php'));

    [$check, $collector] = makeCheckWithCollector(RectorCoversAllPhpFilesCheck::class, $project);

    expect($check->fix())->toBe(CheckResult::FAIL)
        ->and(implode("\n", $collector->all()))->toContain('Rector does not cover inc/setup.php: add them (or their directory) to ->withPaths()')
        ->and(file_get_contents($project->path('rector.php')))->toBe($before);
});

it('rectorCoversAllPhpFiles fails without a rector.php', function (): void {
    expect(makeCheck(RectorCoversAllPhpFilesCheck::class, makeProject(Profile::Php, ['a.php' => "<?php\n"]))->check())
        ->toBe(CheckResult::FAIL);
});

it('rectorCoversAllPhpFiles fails on a rector.php it cannot parse', function (): void {
    $project = makeProject(Profile::Php, ['rector.php' => "<?php\nreturn RectorConfig::configure(\n"]);

    expect(makeCheck(RectorCoversAllPhpFilesCheck::class, $project)->check())->toBe(CheckResult::FAIL);
});

it('rectorCoversAllPhpFiles is fixable and applies outside Laravel only', function (): void {
    expect(makeCheck(RectorCoversAllPhpFilesCheck::class, makeProject(Profile::Php)))->toBeInstanceOf(FixableInterface::class)
        ->and(RectorCoversAllPhpFilesCheck::profiles())->toBe([Profile::Php, Profile::WordPress]);
});
