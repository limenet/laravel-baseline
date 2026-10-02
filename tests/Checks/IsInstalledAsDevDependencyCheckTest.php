<?php

use Limenet\LaravelBaseline\Checks\Checks\IsInstalledAsDevDependencyCheck;
use Limenet\LaravelBaseline\Checks\Checks\IsInstalledAsRegularDependencyCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

it('isInstalledAsDevDependency passes when the package is in require-dev', function (Profile $profile): void {
    $project = makeProject($profile, ['composer.json' => json_encode(['require-dev' => ['limenet/laravel-baseline' => '^2.14']])]);

    expect(makeCheck(IsInstalledAsDevDependencyCheck::class, $project)->check())->toBe(CheckResult::PASS);
})->with([Profile::Php, Profile::WordPress]);

it('isInstalledAsDevDependency moves the package from require to require-dev', function (): void {
    $project = makeProject(Profile::WordPress, ['composer.json' => json_encode([
        'require' => ['php' => '^8.5', 'limenet/laravel-baseline' => '^2.14'],
        'require-dev' => ['phpstan/phpstan' => '^2.0'],
    ])]);

    [$check, $collector] = makeCheckWithCollector(IsInstalledAsDevDependencyCheck::class, $project);

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toContain('limenet/laravel-baseline is in require: Move it to require-dev in composer.json — it is only used during development')
        ->and($check->fix())->toBe(CheckResult::PASS);

    $composer = json_decode((string) file_get_contents($project->path('composer.json')), true);

    expect($composer['require'])->toBe(['php' => '^8.5'])
        ->and($composer['require-dev'])->toBe(['phpstan/phpstan' => '^2.0', 'limenet/laravel-baseline' => '^2.14']);
});

it('isInstalledAsDevDependency fails when the package is missing', function (): void {
    $project = makeProject(Profile::Php, ['composer.json' => json_encode(['require' => []])]);

    expect(makeCheck(IsInstalledAsDevDependencyCheck::class, $project)->fix())->toBe(CheckResult::FAIL);
});

it('splits the dependency rule by profile', function (): void {
    expect(IsInstalledAsDevDependencyCheck::profiles())->toBe([Profile::Php, Profile::WordPress])
        ->and(IsInstalledAsRegularDependencyCheck::profiles())->toBe([Profile::Laravel]);
});
