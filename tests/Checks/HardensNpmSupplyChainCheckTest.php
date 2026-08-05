<?php

use Limenet\LaravelBaseline\Checks\Checks\HardensNpmSupplyChainCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

function npmPackageJson(?string $npm): string
{
    $data = ['name' => 'test-project'];

    if ($npm !== null) {
        $data['engines'] = ['npm' => $npm];
    }

    return json_encode($data);
}

it('hardensNpmSupplyChain implements FixableInterface', function (): void {
    expect(makeCheck(HardensNpmSupplyChainCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('hardensNpmSupplyChain passes when npm >= 12, engine-strict and min-release-age are set', function (): void {
    $this->withTempBasePath([
        'package.json' => npmPackageJson('^12'),
        '.npmrc' => "engine-strict=true\nmin-release-age=7\n",
    ]);

    expect(makeCheck(HardensNpmSupplyChainCheck::class)->check())->toBe(CheckResult::PASS);
});

it('hardensNpmSupplyChain fails when package.json is missing', function (): void {
    $this->withTempBasePath([
        '.npmrc' => "engine-strict=true\nmin-release-age=7\n",
    ]);

    expect(makeCheck(HardensNpmSupplyChainCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('hardensNpmSupplyChain fails when engines.npm is missing', function (): void {
    $this->withTempBasePath([
        'package.json' => npmPackageJson(null),
        '.npmrc' => "engine-strict=true\nmin-release-age=7\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(HardensNpmSupplyChainCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('package.json missing engines.npm: add "engines": { "npm": "^12" } (npm 12 blocks dependency lifecycle scripts by default)');
});

it('hardensNpmSupplyChain fails when engines.npm allows npm < 12', function (): void {
    $this->withTempBasePath([
        'package.json' => npmPackageJson('^11'),
        '.npmrc' => "engine-strict=true\nmin-release-age=7\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(HardensNpmSupplyChainCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('engines.npm (^11) allows npm < 12; require npm >= 12 (e.g. "^12")');
});

it('hardensNpmSupplyChain fails when engine-strict is not set', function (): void {
    $this->withTempBasePath([
        'package.json' => npmPackageJson('^12'),
        '.npmrc' => "min-release-age=7\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(HardensNpmSupplyChainCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('.npmrc missing engine-strict=true: add "engine-strict=true" so the required npm version is enforced, not just advised');
});

it('hardensNpmSupplyChain fails when min-release-age is missing', function (): void {
    $this->withTempBasePath([
        'package.json' => npmPackageJson('^12'),
        '.npmrc' => "engine-strict=true\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(HardensNpmSupplyChainCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('.npmrc missing min-release-age: add "min-release-age=7" for a 7-day dependency install cooldown');
});

it('hardensNpmSupplyChain fails when min-release-age is below 7', function (): void {
    $this->withTempBasePath([
        'package.json' => npmPackageJson('^12'),
        '.npmrc' => "engine-strict=true\nmin-release-age=3\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(HardensNpmSupplyChainCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('.npmrc min-release-age (3) is below the recommended 7-day cooldown');
});

it('hardensNpmSupplyChain fix establishes npm ^12, engine-strict and min-release-age from an empty project', function (): void {
    $this->withTempBasePath([
        'package.json' => npmPackageJson(null),
    ]);

    $check = makeCheck(HardensNpmSupplyChainCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $packageJson = json_decode(file_get_contents(base_path('package.json')), true);
    expect($packageJson['engines']['npm'])->toBe('^12');

    $npmrc = file_get_contents(base_path('.npmrc'));
    expect($npmrc)->toContain('engine-strict=true');
    expect($npmrc)->toContain('min-release-age=7');
});

it('hardensNpmSupplyChain fix bumps a too-low min-release-age to 7', function (): void {
    $this->withTempBasePath([
        'package.json' => npmPackageJson('^12'),
        '.npmrc' => "engine-strict=true\nmin-release-age=3\n",
    ]);

    $check = makeCheck(HardensNpmSupplyChainCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $npmrc = file_get_contents(base_path('.npmrc'));
    expect($npmrc)->toContain('min-release-age=7');
    expect($npmrc)->not->toContain('min-release-age=3');
});

it('hardensNpmSupplyChain fix preserves unrelated .npmrc lines', function (): void {
    $this->withTempBasePath([
        'package.json' => npmPackageJson('^12'),
        '.npmrc' => "save-exact=true\nengine-strict=true\n",
    ]);

    $check = makeCheck(HardensNpmSupplyChainCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $npmrc = file_get_contents(base_path('.npmrc'));
    expect($npmrc)->toContain('save-exact=true');
    expect($npmrc)->toContain('engine-strict=true');
    expect($npmrc)->toContain('min-release-age=7');
});

it('hardensNpmSupplyChain fix leaves an already-sufficient engines.npm untouched', function (): void {
    $this->withTempBasePath([
        'package.json' => npmPackageJson('^13'),
        '.npmrc' => "engine-strict=true\n",
    ]);

    $check = makeCheck(HardensNpmSupplyChainCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $packageJson = json_decode(file_get_contents(base_path('package.json')), true);
    expect($packageJson['engines']['npm'])->toBe('^13');
});
