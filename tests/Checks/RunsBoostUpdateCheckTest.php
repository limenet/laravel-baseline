<?php

use Limenet\LaravelBaseline\Checks\Checks\RunsBoostUpdateCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('runsBoostUpdate is not applicable when laravel/boost is not installed', function (): void {
    bindFakeComposer(['laravel/boost' => false]);

    expect(makeCheck(RunsBoostUpdateCheck::class)->isApplicable())->toBeFalse();
});

it('runsBoostUpdate is applicable when laravel/boost is installed', function (): void {
    bindFakeComposer(['laravel/boost' => true]);

    expect(makeCheck(RunsBoostUpdateCheck::class)->isApplicable())->toBeTrue();
});

it('runsBoostUpdate fails when boost is installed but no state exists', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $this->withTempBasePath([]);

    expect(makeCheck(RunsBoostUpdateCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('runsBoostUpdate fails when boost is installed and state is older than 30 days', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $old = now()->subDays(31)->toAtomString();
    $this->withTempBasePath([
        'config/baseline.php' => "<?php\n\nreturn ['excludes' => [], 'periodic' => ['runsBoostUpdate' => '{$old}']];\n",
    ]);

    expect(makeCheck(RunsBoostUpdateCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('runsBoostUpdate passes when boost is installed and state is within 30 days', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $recent = now()->subDays(1)->toAtomString();
    $this->withTempBasePath([
        'config/baseline.php' => "<?php\n\nreturn ['excludes' => [], 'periodic' => ['runsBoostUpdate' => '{$recent}']];\n",
    ]);

    expect(makeCheck(RunsBoostUpdateCheck::class)->check())->toBe(CheckResult::PASS);
});

it('runsBoostUpdate provides helpful comment when expired', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $this->withTempBasePath([]);

    [$check, $collector] = makeCheckWithCollector(RunsBoostUpdateCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Run `php artisan limenet:laravel-baseline:periodic` to complete this periodic check');
});
