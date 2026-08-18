<?php

use Limenet\LaravelBaseline\Checks\Checks\DdevNodeVersionIsAutoCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Yaml\Yaml;

it('ddevNodeVersionIsAuto implements FixableInterface', function (): void {
    expect(makeCheck(DdevNodeVersionIsAutoCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('ddevNodeVersionIsAuto passes when nodejs_version is auto', function (): void {
    $this->withTempBasePath([
        '.ddev/config.yaml' => "name: test-project\nnodejs_version: auto\n",
    ]);

    expect(makeCheck(DdevNodeVersionIsAutoCheck::class)->check())->toBe(CheckResult::PASS);
});

it('ddevNodeVersionIsAuto fails when .ddev/config.yaml is missing', function (): void {
    $this->withTempBasePath([]);

    expect(makeCheck(DdevNodeVersionIsAutoCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('ddevNodeVersionIsAuto fails when nodejs_version is pinned', function (): void {
    $this->withTempBasePath([
        '.ddev/config.yaml' => "name: test-project\nnodejs_version: \"24\"\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(DdevNodeVersionIsAutoCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('DDEV nodejs_version should be "auto" to derive the Node version from the project: set "nodejs_version: auto" in .ddev/config.yaml');
});

it('ddevNodeVersionIsAuto fails when nodejs_version is absent', function (): void {
    $this->withTempBasePath([
        '.ddev/config.yaml' => "name: test-project\n",
    ]);

    expect(makeCheck(DdevNodeVersionIsAutoCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('ddevNodeVersionIsAuto fix sets nodejs_version to auto', function (): void {
    $this->withTempBasePath([
        '.ddev/config.yaml' => "name: test-project\nnodejs_version: \"24\"\n",
    ]);

    $check = makeCheck(DdevNodeVersionIsAutoCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $ddev = Yaml::parseFile(base_path('.ddev/config.yaml'));
    expect($ddev['nodejs_version'])->toBe('auto');
});

it('ddevNodeVersionIsAuto fix preserves comments and formatting when replacing nodejs_version', function (): void {
    // Mirrors DDEV's real layout: live keys grouped at the top with no
    // interleaved comments, followed by a large commented-out
    // documentation block (which includes an example of the same key).
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.3"
nodejs_version: "22"
webimage_extra_packages: [cron]

# Key features of DDEV's config.yaml:

# nodejs_version: "22"
# change from the default system Node.js version to any other version.
YML;

    $this->withTempBasePath([
        '.ddev/config.yaml' => $ddevConfig,
    ]);

    $check = makeCheck(DdevNodeVersionIsAutoCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $raw = file_get_contents(base_path('.ddev/config.yaml'));
    expect($raw)->toContain("# Key features of DDEV's config.yaml:");
    expect($raw)->toContain('# nodejs_version: "22"');
    expect($raw)->toContain('# change from the default system Node.js version to any other version.');
    expect($raw)->toContain('php_version: "8.3"');
    expect($raw)->toContain('webimage_extra_packages: [cron]');
    expect(substr_count($raw, "\nnodejs_version:"))->toBe(1);

    $ddev = Yaml::parseFile(base_path('.ddev/config.yaml'));
    expect($ddev['nodejs_version'])->toBe('auto');
});

it('ddevNodeVersionIsAuto fix preserves comments when appending nodejs_version', function (): void {
    $ddevConfig = <<<'YML'
name: test-project
type: php

# Key features of DDEV's config.yaml:

# nodejs_version: "22"
# change from the default system Node.js version to any other version.
YML;

    $this->withTempBasePath([
        '.ddev/config.yaml' => $ddevConfig,
    ]);

    $check = makeCheck(DdevNodeVersionIsAutoCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $raw = file_get_contents(base_path('.ddev/config.yaml'));
    expect($raw)->toContain("# Key features of DDEV's config.yaml:");
    expect($raw)->toContain('# nodejs_version: "22"');

    // The new key is inserted before the trailing documentation block,
    // not appended after it.
    $nodejsVersionPos = strpos($raw, 'nodejs_version: auto');
    $commentBlockPos = strpos($raw, "# Key features of DDEV's config.yaml:");
    expect($nodejsVersionPos)->not->toBeFalse();
    expect($commentBlockPos)->not->toBeFalse();
    expect($nodejsVersionPos)->toBeLessThan($commentBlockPos);

    $ddev = Yaml::parseFile(base_path('.ddev/config.yaml'));
    expect($ddev['nodejs_version'])->toBe('auto');
});
