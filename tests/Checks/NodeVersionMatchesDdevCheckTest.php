<?php

use Limenet\LaravelBaseline\Checks\Checks\NodeVersionMatchesDdevCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Yaml\Yaml;

function nodePackageJson(?string $engines): string
{
    $data = ['name' => 'test-project'];

    if ($engines !== null) {
        $data['engines'] = ['node' => $engines];
    }

    return json_encode($data);
}

it('nodeVersionMatchesDdev implements FixableInterface', function (): void {
    expect(makeCheck(NodeVersionMatchesDdevCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('nodeVersionMatchesDdev passes when engines.node, .nvmrc and nodejs_version: auto agree', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson('^22'),
        '.nvmrc' => "22\n",
        '.ddev/config.yaml' => "name: test-project\nnodejs_version: auto\n",
    ]);

    expect(makeCheck(NodeVersionMatchesDdevCheck::class)->check())->toBe(CheckResult::PASS);
});

it('nodeVersionMatchesDdev fails when package.json is missing', function (): void {
    $this->withTempBasePath([
        '.nvmrc' => "22\n",
        '.ddev/config.yaml' => "name: test-project\nnodejs_version: auto\n",
    ]);

    expect(makeCheck(NodeVersionMatchesDdevCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('nodeVersionMatchesDdev fails when engines.node is missing', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson(null),
        '.nvmrc' => "22\n",
        '.ddev/config.yaml' => "name: test-project\nnodejs_version: auto\n",
    ]);

    expect(makeCheck(NodeVersionMatchesDdevCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('nodeVersionMatchesDdev fails when .nvmrc is missing', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson('^22'),
        '.ddev/config.yaml' => "name: test-project\nnodejs_version: auto\n",
    ]);

    expect(makeCheck(NodeVersionMatchesDdevCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('nodeVersionMatchesDdev fails when engines.node and .nvmrc are incompatible', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson('^22'),
        '.nvmrc' => "20\n",
        '.ddev/config.yaml' => "name: test-project\nnodejs_version: auto\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(NodeVersionMatchesDdevCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Node version mismatch: package.json engines.node (^22) and .nvmrc (20) disagree');
});

it('nodeVersionMatchesDdev fails when .ddev nodejs_version is not auto', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson('^22'),
        '.nvmrc' => "22\n",
        '.ddev/config.yaml' => "name: test-project\nnodejs_version: \"22\"\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(NodeVersionMatchesDdevCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('DDEV nodejs_version should be "auto" to derive the Node version from the project: set "nodejs_version: auto" in .ddev/config.yaml');
});

it('nodeVersionMatchesDdev comment names the default when no constraint exists', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson(null),
        '.ddev/config.yaml' => "name: test-project\nnodejs_version: auto\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(NodeVersionMatchesDdevCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('package.json missing engines.node: add "engines": { "node": "^26" }');
});

it('nodeVersionMatchesDdev fix establishes Node 26 when nothing is set', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson(null),
        '.ddev/config.yaml' => "name: test-project\n",
    ]);

    $check = makeCheck(NodeVersionMatchesDdevCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $packageJson = json_decode(file_get_contents(base_path('package.json')), true);
    expect($packageJson['engines']['node'])->toBe('^26');
    expect(trim(file_get_contents(base_path('.nvmrc'))))->toBe('26');

    $ddev = Yaml::parseFile(base_path('.ddev/config.yaml'));
    expect($ddev['nodejs_version'])->toBe('auto');
});

it('nodeVersionMatchesDdev fix creates .nvmrc from the existing engines.node', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson('^22'),
        '.ddev/config.yaml' => "name: test-project\nnodejs_version: auto\n",
    ]);

    $check = makeCheck(NodeVersionMatchesDdevCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect(trim(file_get_contents(base_path('.nvmrc'))))->toBe('22');
});

it('nodeVersionMatchesDdev fix creates engines.node from the existing .nvmrc', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson(null),
        '.nvmrc' => "22\n",
        '.ddev/config.yaml' => "name: test-project\nnodejs_version: auto\n",
    ]);

    $check = makeCheck(NodeVersionMatchesDdevCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $packageJson = json_decode(file_get_contents(base_path('package.json')), true);
    expect($packageJson['engines']['node'])->toBe('^22');
});

it('nodeVersionMatchesDdev fix sets nodejs_version to auto', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson('^22'),
        '.nvmrc' => "22\n",
        '.ddev/config.yaml' => "name: test-project\nnodejs_version: \"22\"\n",
    ]);

    $check = makeCheck(NodeVersionMatchesDdevCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $ddev = Yaml::parseFile(base_path('.ddev/config.yaml'));
    expect($ddev['nodejs_version'])->toBe('auto');
});

it('nodeVersionMatchesDdev fix does not auto-resolve a version conflict', function (): void {
    $this->withTempBasePath([
        'package.json' => nodePackageJson('^22'),
        '.nvmrc' => "20\n",
        '.ddev/config.yaml' => "name: test-project\nnodejs_version: auto\n",
    ]);

    $check = makeCheck(NodeVersionMatchesDdevCheck::class);
    expect($check->fix())->toBe(CheckResult::FAIL);

    // Files remain untouched — the developer must resolve the conflict.
    $packageJson = json_decode(file_get_contents(base_path('package.json')), true);
    expect($packageJson['engines']['node'])->toBe('^22');
    expect(trim(file_get_contents(base_path('.nvmrc'))))->toBe('20');
});
