<?php

use Limenet\LaravelBaseline\Checks\Checks\FollowsModernLaravelIdiomsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('followsModernLaravelIdioms is not applicable on Laravel older than 12.45', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['require' => ['laravel/framework' => '~12.40.0']]),
    ]);

    expect(makeCheck(FollowsModernLaravelIdiomsCheck::class)->isApplicable())->toBeFalse();
});

it('followsModernLaravelIdioms is applicable on Laravel 12.45+', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['require' => ['laravel/framework' => '^12.45']]),
    ]);

    expect(makeCheck(FollowsModernLaravelIdiomsCheck::class)->isApplicable())->toBeTrue();
});

it('followsModernLaravelIdioms is applicable on Laravel 13.x', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['require' => ['laravel/framework' => '^13.0']]),
    ]);

    expect(makeCheck(FollowsModernLaravelIdiomsCheck::class)->isApplicable())->toBeTrue();
});

it('followsModernLaravelIdioms fails when no periodic state exists', function (): void {
    bindFakeComposer(['laravel/framework' => true]);
    $this->withTempBasePath([]);

    expect(makeCheck(FollowsModernLaravelIdiomsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('followsModernLaravelIdioms fails when state is older than 30 days', function (): void {
    bindFakeComposer(['laravel/framework' => true]);
    $old = now()->subDays(31)->toAtomString();
    $this->withTempBasePath([
        'config/baseline.php' => "<?php\n\nreturn ['excludes' => [], 'periodic' => ['followsModernLaravelIdioms' => '{$old}']];\n",
    ]);

    expect(makeCheck(FollowsModernLaravelIdiomsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('followsModernLaravelIdioms passes when state is within 30 days', function (): void {
    bindFakeComposer(['laravel/framework' => true]);
    $recent = now()->subDays(1)->toAtomString();
    $this->withTempBasePath([
        'config/baseline.php' => "<?php\n\nreturn ['excludes' => [], 'periodic' => ['followsModernLaravelIdioms' => '{$recent}']];\n",
    ]);

    expect(makeCheck(FollowsModernLaravelIdiomsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('followsModernLaravelIdioms provides a helpful comment when expired', function (): void {
    bindFakeComposer(['laravel/framework' => true]);
    $this->withTempBasePath([]);

    [$check, $collector] = makeCheckWithCollector(FollowsModernLaravelIdiomsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Run `ddev artisan limenet:laravel-baseline:periodic` to complete this periodic check');
});
