<?php

use Limenet\LaravelBaseline\Checks\Checks\PhpstanCoversAllPhpFilesCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Project\Project;

function phpstanCoverageProject(string $neon, array $files = []): Project
{
    return makeProject(Profile::WordPress, [
        'phpstan.neon' => $neon,
        'functions.php' => "<?php\n",
        'app/index.php' => "<?php\n",
        'rector.php' => "<?php\n",
        'vendor/acme/lib.php' => "<?php\n",
        ...$files,
    ]);
}

it('phpstanCoversAllPhpFiles fails when a root file is not analysed', function (): void {
    $project = phpstanCoverageProject("parameters:\n    level: 8\n    paths:\n        - app\n");

    [$check, $collector] = makeCheckWithCollector(PhpstanCoversAllPhpFilesCheck::class, $project);

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toContain('PHPStan does not cover functions.php: add them (or their directory) to parameters.paths in phpstan.neon, or to excludePaths if leaving them unanalysed is deliberate');
});

it('phpstanCoversAllPhpFiles passes when every file is analysed', function (string $paths): void {
    $project = phpstanCoverageProject("parameters:\n    paths:\n{$paths}");

    expect(makeCheck(PhpstanCoversAllPhpFilesCheck::class, $project)->check())->toBe(CheckResult::PASS);
})->with([
    'listed' => ["        - app\n        - functions.php\n"],
    'root' => ["        - .\n"],
    'current working directory' => ["        - %currentWorkingDirectory%/app\n        - %currentWorkingDirectory%/functions.php\n"],
    'glob' => ["        - app\n        - '*.php'\n"],
]);

it('phpstanCoversAllPhpFiles accepts a deliberate exclude', function (string $excludes): void {
    $project = phpstanCoverageProject("parameters:\n    paths:\n        - app\n    excludePaths:\n{$excludes}");

    expect(makeCheck(PhpstanCoversAllPhpFilesCheck::class, $project)->check())->toBe(CheckResult::PASS);
})->with([
    'list' => ["        - functions.php\n"],
    'analyse' => ["        analyse:\n            - functions.php\n"],
    'optional' => ["        - 'functions.php (?)'\n"],
]);

it('phpstanCoversAllPhpFiles reads phpstan.neon.dist', function (): void {
    $project = makeProject(Profile::Php, [
        'phpstan.neon.dist' => "parameters:\n    paths:\n        - src\n",
        'src/A.php' => "<?php\n",
        'bin/tool.php' => "<?php\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(PhpstanCoversAllPhpFilesCheck::class, $project);

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and(implode("\n", $collector->all()))->toContain('PHPStan does not cover bin/tool.php')->toContain('phpstan.neon.dist');
});

it('phpstanCoversAllPhpFiles lists at most ten files', function (): void {
    $files = [];

    for ($i = 1; $i <= 12; $i++) {
        $files[sprintf('templates/t%02d.php', $i)] = "<?php\n";
    }

    $project = phpstanCoverageProject("parameters:\n    paths:\n        - app\n        - functions.php\n", $files);

    [$check, $collector] = makeCheckWithCollector(PhpstanCoversAllPhpFilesCheck::class, $project);

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and(implode("\n", $collector->all()))->toContain('templates/t10.php and 2 more:');
});

it('phpstanCoversAllPhpFiles warns without a PHPStan configuration', function (): void {
    expect(makeCheck(PhpstanCoversAllPhpFilesCheck::class, makeProject(Profile::Php, ['a.php' => "<?php\n"]))->check())
        ->toBe(CheckResult::WARN);
});

it('phpstanCoversAllPhpFiles fails on a configuration it cannot parse', function (): void {
    $project = phpstanCoverageProject("parameters:\n    paths: [app\n");

    expect(makeCheck(PhpstanCoversAllPhpFilesCheck::class, $project)->check())->toBe(CheckResult::FAIL);
});

it('phpstanCoversAllPhpFiles applies outside Laravel only', function (): void {
    expect(PhpstanCoversAllPhpFilesCheck::profiles())->toBe([Profile::Php, Profile::WordPress]);
});
