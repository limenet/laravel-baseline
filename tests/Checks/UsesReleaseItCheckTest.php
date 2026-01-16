<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesReleaseItCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesReleaseIt passes when all requirements are met', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
        'devDependencies' => [
            'release-it' => '^17.0.0',
            '@release-it/bumper' => '^6.0.0',
        ],
        'scripts' => [
            'release' => 'release-it',
        ],
    ];

    $releaseItConfig = [
        'plugins' => [
            '@release-it/bumper' => [
                'out' => [
                    'file' => 'composer.json',
                    'path' => 'version',
                ],
            ],
        ],
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
        '.release-it.json' => json_encode($releaseItConfig),
    ]);

    $check = makeCheck(UsesReleaseItCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('usesReleaseIt fails when package.json is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    $check = makeCheck(UsesReleaseItCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesReleaseIt fails when release-it is not in devDependencies', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
        'devDependencies' => [
            '@release-it/bumper' => '^6.0.0',
        ],
        'scripts' => [
            'release' => 'release-it',
        ],
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
    ]);

    $check = makeCheck(UsesReleaseItCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesReleaseIt fails when @release-it/bumper is not in devDependencies', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
        'devDependencies' => [
            'release-it' => '^17.0.0',
        ],
        'scripts' => [
            'release' => 'release-it',
        ],
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
    ]);

    $check = makeCheck(UsesReleaseItCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesReleaseIt fails when release script is missing', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
        'devDependencies' => [
            'release-it' => '^17.0.0',
            '@release-it/bumper' => '^6.0.0',
        ],
        'scripts' => [],
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
    ]);

    $check = makeCheck(UsesReleaseItCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesReleaseIt fails when release script does not contain release-it', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
        'devDependencies' => [
            'release-it' => '^17.0.0',
            '@release-it/bumper' => '^6.0.0',
        ],
        'scripts' => [
            'release' => 'echo "release"',
        ],
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
    ]);

    $check = makeCheck(UsesReleaseItCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesReleaseIt fails when .release-it.json is missing', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
        'devDependencies' => [
            'release-it' => '^17.0.0',
            '@release-it/bumper' => '^6.0.0',
        ],
        'scripts' => [
            'release' => 'release-it',
        ],
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
    ]);

    $check = makeCheck(UsesReleaseItCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesReleaseIt fails when @release-it/bumper plugin is not configured', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
        'devDependencies' => [
            'release-it' => '^17.0.0',
            '@release-it/bumper' => '^6.0.0',
        ],
        'scripts' => [
            'release' => 'release-it',
        ],
    ];

    $releaseItConfig = [
        'plugins' => [],
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
        '.release-it.json' => json_encode($releaseItConfig),
    ]);

    $check = makeCheck(UsesReleaseItCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesReleaseIt fails when bumper out.file is incorrect', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
        'devDependencies' => [
            'release-it' => '^17.0.0',
            '@release-it/bumper' => '^6.0.0',
        ],
        'scripts' => [
            'release' => 'release-it',
        ],
    ];

    $releaseItConfig = [
        'plugins' => [
            '@release-it/bumper' => [
                'out' => [
                    'file' => 'package.json',
                    'path' => 'version',
                ],
            ],
        ],
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
        '.release-it.json' => json_encode($releaseItConfig),
    ]);

    $check = makeCheck(UsesReleaseItCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesReleaseIt fails when bumper out.path is incorrect', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
        'devDependencies' => [
            'release-it' => '^17.0.0',
            '@release-it/bumper' => '^6.0.0',
        ],
        'scripts' => [
            'release' => 'release-it',
        ],
    ];

    $releaseItConfig = [
        'plugins' => [
            '@release-it/bumper' => [
                'out' => [
                    'file' => 'composer.json',
                    'path' => 'extra.version',
                ],
            ],
        ],
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
        '.release-it.json' => json_encode($releaseItConfig),
    ]);

    $check = makeCheck(UsesReleaseItCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesReleaseIt fails when bumper out configuration is missing', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
        'devDependencies' => [
            'release-it' => '^17.0.0',
            '@release-it/bumper' => '^6.0.0',
        ],
        'scripts' => [
            'release' => 'release-it',
        ],
    ];

    $releaseItConfig = [
        'plugins' => [
            '@release-it/bumper' => [],
        ],
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
        '.release-it.json' => json_encode($releaseItConfig),
    ]);

    $check = makeCheck(UsesReleaseItCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});
