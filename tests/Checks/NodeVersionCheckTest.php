<?php

use Limenet\LaravelBaseline\Checks\Checks\NodeVersionCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('nodeVersion implements FixableInterface', function (): void {
    expect(makeCheck(NodeVersionCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('nodeVersion passes when engines.node and .nvmrc agree', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson('^24'),
        '.nvmrc' => "24\n",
    ]);

    expect(makeCheck(NodeVersionCheck::class)->check())->toBe(CheckResult::PASS);
});

it('nodeVersion passes when engines.node and .nvmrc pin a newer major', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson('^26'),
        '.nvmrc' => "26\n",
    ]);

    expect(makeCheck(NodeVersionCheck::class)->check())->toBe(CheckResult::PASS);
});

it('nodeVersion fails when package.json is missing', function (): void {
    $this->withTempBasePath([
        '.nvmrc' => "24\n",
    ]);

    expect(makeCheck(NodeVersionCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('nodeVersion fails when engines.node is missing', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson(null),
        '.nvmrc' => "24\n",
    ]);

    expect(makeCheck(NodeVersionCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('nodeVersion fails when .nvmrc is missing', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson('^24'),
    ]);

    expect(makeCheck(NodeVersionCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('nodeVersion fails when the declared version is below the floor', function (): void {
    // Consistent with each other, but both on a line the baseline no longer allows.
    $this->withTempBasePath([
        'package.json' => nodePackageJson('^22'),
        '.nvmrc' => "22\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(NodeVersionCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('engines.node (^22) allows Node < 24; require Node >= 24 (e.g. "^24")');
});

it('nodeVersion fails when engines.node is open-ended below the floor', function (): void {
    // ">=20" has a lower bound below the floor even though it also permits 24+.
    $this->withTempBasePath([
        'package.json' => nodePackageJson('>=20'),
        '.nvmrc' => "24\n",
    ]);

    expect(makeCheck(NodeVersionCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('nodeVersion fails when engines.node and .nvmrc are incompatible', function (): void {
    // Both clear the floor — this is a genuine disagreement, not a stale version.
    $this->withTempBasePath([
        'package.json' => nodePackageJson('^26'),
        '.nvmrc' => "24\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(NodeVersionCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Node version mismatch: package.json engines.node (^26) and .nvmrc (24) disagree');
});

it('nodeVersion comment names the default when no constraint exists', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson(null),
    ]);

    [$check, $collector] = makeCheckWithCollector(NodeVersionCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('package.json missing engines.node: add "engines": { "node": "^24" }');
});

it('nodeVersion fix establishes Node 24 when nothing is set', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson(null),
    ]);

    $check = makeCheck(NodeVersionCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $packageJson = json_decode(file_get_contents(base_path('package.json')), true);
    expect($packageJson['engines']['node'])->toBe('^24');
    expect(trim(file_get_contents(base_path('.nvmrc'))))->toBe('24');
});

it('nodeVersion fix creates .nvmrc from the existing engines.node', function (): void {
    // A newer-than-floor line is preserved, not forced down to the floor.
    $this->withTempBasePath([
        'package.json' => nodePackageJson('^26'),
    ]);

    $check = makeCheck(NodeVersionCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect(trim(file_get_contents(base_path('.nvmrc'))))->toBe('26');
});

it('nodeVersion fix creates engines.node from the existing .nvmrc', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson(null),
        '.nvmrc' => "26\n",
    ]);

    $check = makeCheck(NodeVersionCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $packageJson = json_decode(file_get_contents(base_path('package.json')), true);
    expect($packageJson['engines']['node'])->toBe('^26');
});

it('nodeVersion fix bumps a below-floor version in both files', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson('^22'),
        '.nvmrc' => "22\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(NodeVersionCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $packageJson = json_decode(file_get_contents(base_path('package.json')), true);
    expect($packageJson['engines']['node'])->toBe('^24');
    expect(trim(file_get_contents(base_path('.nvmrc'))))->toBe('24');

    expect($collector->all())->toContain('engines.node (^22) allows Node < 24; require Node >= 24 (e.g. "^24")');
    expect($collector->all())->toContain('.nvmrc (22) pins Node < 24: set it to "24"');
});

it('nodeVersion fix does not auto-resolve a version conflict', function (): void {
    // Both clear the floor, so choosing between them is a human decision.
    $this->withTempBasePath([
        'package.json' => nodePackageJson('^26'),
        '.nvmrc' => "24\n",
    ]);

    $check = makeCheck(NodeVersionCheck::class);
    expect($check->fix())->toBe(CheckResult::FAIL);

    // Files remain untouched — the developer must resolve the conflict.
    $packageJson = json_decode(file_get_contents(base_path('package.json')), true);
    expect($packageJson['engines']['node'])->toBe('^26');
    expect(trim(file_get_contents(base_path('.nvmrc'))))->toBe('24');
});
