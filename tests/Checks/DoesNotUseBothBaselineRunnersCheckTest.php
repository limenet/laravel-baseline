<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotUseBothBaselineRunnersCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('doesNotUseBothBaselineRunners passes when only this runner is installed', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        'package.json' => json_encode([
            'name' => 'test-project',
            'devDependencies' => ['vite' => '^7.0.0'],
        ]),
    ]);

    expect(makeCheck(DoesNotUseBothBaselineRunnersCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotUseBothBaselineRunners passes when the project has no package.json', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    expect(makeCheck(DoesNotUseBothBaselineRunnersCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotUseBothBaselineRunners fails when the npm runner is a devDependency', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        'package.json' => json_encode([
            'name' => 'test-project',
            'devDependencies' => ['@limenet-ch/baseline' => '^2.8'],
        ]),
    ]);

    [$check, $collector] = makeCheckWithCollector(DoesNotUseBothBaselineRunnersCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $collector->all()))
        ->toContain('npm uninstall @limenet-ch/baseline')
        ->toContain('limenet/laravel-baseline');
});

it('doesNotUseBothBaselineRunners fails when the npm runner is a runtime dependency', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        'package.json' => json_encode([
            'name' => 'test-project',
            'dependencies' => ['@limenet-ch/baseline' => '^2.8'],
        ]),
    ]);

    expect(makeCheck(DoesNotUseBothBaselineRunnersCheck::class)->check())->toBe(CheckResult::FAIL);
});
