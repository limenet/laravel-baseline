<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesPhpstanWordpressCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

it('usesPhpstanWordpress passes when the WordPress stubs are installed', function (): void {
    $project = makeProject(Profile::WordPress, ['composer.json' => json_encode([
        'require-dev' => ['szepeviktor/phpstan-wordpress' => '^2.0'],
    ])]);

    expect(makeCheck(UsesPhpstanWordpressCheck::class, $project)->check())->toBe(CheckResult::PASS);
});

it('usesPhpstanWordpress fails and says how to install them', function (): void {
    $project = makeProject(Profile::WordPress, ['composer.json' => json_encode(['require-dev' => []])]);

    [$check, $collector] = makeCheckWithCollector(UsesPhpstanWordpressCheck::class, $project);

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toContain('Install szepeviktor/phpstan-wordpress: run `ddev composer require --dev szepeviktor/phpstan-wordpress` (phpstan/extension-installer loads it)');
});

it('usesPhpstanWordpress only applies to WordPress projects', function (): void {
    expect(UsesPhpstanWordpressCheck::profiles())->toBe([Profile::WordPress]);
});
