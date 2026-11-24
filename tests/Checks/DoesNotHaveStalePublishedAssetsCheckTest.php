<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotHaveStalePublishedAssetsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

function lockWith(array $packages, array $devPackages = []): string
{
    $entries = static fn (array $list): array => array_map(
        static fn (string $name, string $version): array => ['name' => $name, 'version' => $version],
        array_keys($list),
        $list,
    );

    return json_encode(['packages' => $entries($packages), 'packages-dev' => $entries($devPackages)]);
}

it('doesNotHaveStalePublishedAssets passes when no published assets exist', function (): void {
    $this->withTempBasePath(['composer.lock' => lockWith(['laravel/telescope' => 'v5.29.0'])]);

    expect(makeCheck(DoesNotHaveStalePublishedAssetsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotHaveStalePublishedAssets passes without a composer.lock', function (): void {
    $this->withTempBasePath(['public/vendor/telescope/app.js' => '']);

    expect(makeCheck(DoesNotHaveStalePublishedAssetsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotHaveStalePublishedAssets passes while the installed Telescope still serves published assets', function (): void {
    $this->withTempBasePath([
        'composer.lock' => lockWith([], ['laravel/telescope' => 'v5.9.1']),
        'public/vendor/telescope/app.js' => '',
    ]);

    expect(makeCheck(DoesNotHaveStalePublishedAssetsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotHaveStalePublishedAssets leaves a branch alias alone', function (): void {
    $this->withTempBasePath([
        'composer.lock' => lockWith(['laravel/telescope' => '5.x-dev']),
        'public/vendor/telescope/app.js' => '',
    ]);

    expect(makeCheck(DoesNotHaveStalePublishedAssetsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotHaveStalePublishedAssets fails when Telescope inlines its assets', function (): void {
    $this->withTempBasePath([
        'composer.lock' => lockWith([], ['laravel/telescope' => 'v5.28.0']),
        'public/vendor/telescope/app.js' => '',
    ]);

    [$check, $collector] = makeCheckWithCollector(DoesNotHaveStalePublishedAssetsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Remove public/vendor/telescope — laravel/telescope 5.28.0 inlines its dashboard assets (since 5.10.0), so the published copy is stale and unused');
});

it('doesNotHaveStalePublishedAssets fails when Horizon inlines its assets', function (): void {
    $this->withTempBasePath([
        'composer.lock' => lockWith(['laravel/horizon' => 'v5.25.0']),
        'public/vendor/horizon/app.js' => '',
    ]);

    [$check, $collector] = makeCheckWithCollector(DoesNotHaveStalePublishedAssetsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Remove public/vendor/horizon — laravel/horizon 5.25.0 inlines its dashboard assets (since 5.25.0), so the published copy is stale and unused');
});

it('doesNotHaveStalePublishedAssets fails when the package is no longer installed', function (): void {
    $this->withTempBasePath([
        'composer.lock' => lockWith(['laravel/framework' => 'v12.0.0']),
        'public/vendor/horizon/app.js' => '',
    ]);

    [$check, $collector] = makeCheckWithCollector(DoesNotHaveStalePublishedAssetsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Remove public/vendor/horizon — laravel/horizon is no longer installed');
});

it('doesNotHaveStalePublishedAssets fix deletes only the stale directories', function (): void {
    $this->withTempBasePath([
        'composer.lock' => lockWith(['laravel/horizon' => 'v5.24.0'], ['laravel/telescope' => 'v5.29.0']),
        'public/vendor/telescope/app.js' => '',
        'public/vendor/telescope/mix-manifest.json' => '{}',
        'public/vendor/horizon/app.js' => '',
        'public/vendor/livewire/livewire.js' => '',
    ]);

    expect(makeCheck(DoesNotHaveStalePublishedAssetsCheck::class)->fix())->toBe(CheckResult::PASS);

    expect(is_dir(base_path('public/vendor/telescope')))->toBeFalse();
    expect(is_dir(base_path('public/vendor/horizon')))->toBeTrue();
    expect(is_file(base_path('public/vendor/livewire/livewire.js')))->toBeTrue();
});
