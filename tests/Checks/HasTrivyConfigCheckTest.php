<?php

use Limenet\LaravelBaseline\Checks\Checks\HasTrivyConfigCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Yaml\Yaml;

function canonicalTrivyYaml(): string
{
    return <<<'YML'
ignorefile: .trivyignore.yaml
cache:
  dir: .trivycache
scan:
  skip-files:
    - .env
    - vendor/**/Dockerfile
  skip-dirs:
    - .ddev/
    - storage/logs/
  scanners:
    - misconfig
    - secret
    - vuln
  disable-telemetry: true
disable-vex-notice: true
dependency-tree: true
YML;
}

function canonicalCiYaml(): string
{
    return <<<'YML'
security:
  extends: ['.lint_security']
YML;
}

function canonicalLayout(array $overrides = []): array
{
    return array_merge([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.gitlab-ci.yml' => canonicalCiYaml(),
        'trivy.yaml' => canonicalTrivyYaml(),
        '.trivyignore.yaml' => '',
        '.gitignore' => ".trivycache/\n",
    ], $overrides);
}

it('hasTrivyConfig passes when all canonical files and config are present', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(canonicalLayout());

    expect(makeCheck(HasTrivyConfigCheck::class)->check())->toBe(CheckResult::PASS);
});

it('hasTrivyConfig fails when .gitlab-ci.yml is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('.gitlab-ci.yml not found');
});

it('hasTrivyConfig fails when security CI job is missing or misconfigured', function (): void {
    bindFakeComposer([]);
    $ciYaml = "build:\n  extends: ['.build']\n";

    $this->withTempBasePath(canonicalLayout(['.gitlab-ci.yml' => $ciYaml]));

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing or misconfigured CI job in .gitlab-ci.yml: Add job 'security' with 'extends: [.lint_security]'");
});

it('hasTrivyConfig fails when trivy.yaml is missing', function (): void {
    bindFakeComposer([]);
    $layout = canonicalLayout();
    unset($layout['trivy.yaml']);
    $this->withTempBasePath($layout);

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('trivy.yaml not found');
});

it('hasTrivyConfig fails when .gitignore is absent', function (): void {
    bindFakeComposer([]);
    $layout = canonicalLayout();
    unset($layout['.gitignore']);
    $this->withTempBasePath($layout);

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing .gitignore in project root: create it and add '.trivycache/'");
});

it('hasTrivyConfig fails when .gitignore is present but missing .trivycache/', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(canonicalLayout(['.gitignore' => "vendor/\nnode_modules/\n"]));

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing entry in .gitignore: add '.trivycache/' to ignore the Trivy cache directory");
});

it('hasTrivyConfig accepts .gitignore entry variants without leading or trailing slash', function (string $entry): void {
    bindFakeComposer([]);
    $this->withTempBasePath(canonicalLayout(['.gitignore' => "vendor/\n{$entry}\n"]));

    expect(makeCheck(HasTrivyConfigCheck::class)->check())->toBe(CheckResult::PASS);
})->with([
    '.trivycache/',
    '.trivycache',
    '/.trivycache/',
    '/.trivycache',
]);

it('hasTrivyConfig fails when .trivyignore.yaml is missing', function (): void {
    bindFakeComposer([]);
    $layout = canonicalLayout();
    unset($layout['.trivyignore.yaml']);
    $this->withTempBasePath($layout);

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing ignore file: create .trivyignore.yaml in project root (an empty file is acceptable)');
});

it('hasTrivyConfig fails when ignorefile scalar is missing', function (): void {
    bindFakeComposer([]);
    $config = Yaml::parse(canonicalTrivyYaml());
    unset($config['ignorefile']);
    $this->withTempBasePath(canonicalLayout(['trivy.yaml' => Yaml::dump($config, 4, 2)]));

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Invalid value in trivy.yaml: 'ignorefile' must equal '.trivyignore.yaml'");
});

it('hasTrivyConfig fails when ignorefile scalar has wrong value', function (): void {
    bindFakeComposer([]);
    $config = Yaml::parse(canonicalTrivyYaml());
    $config['ignorefile'] = '.trivyignore';
    $this->withTempBasePath(canonicalLayout(['trivy.yaml' => Yaml::dump($config, 4, 2)]));

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Invalid value in trivy.yaml: 'ignorefile' must equal '.trivyignore.yaml'");
});

it('hasTrivyConfig fails when cache.dir is missing or wrong', function (): void {
    bindFakeComposer([]);
    $config = Yaml::parse(canonicalTrivyYaml());
    $config['cache']['dir'] = '.othercache';
    $this->withTempBasePath(canonicalLayout(['trivy.yaml' => Yaml::dump($config, 4, 2)]));

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Invalid value in trivy.yaml: 'cache.dir' must equal '.trivycache'");
});

it('hasTrivyConfig fails when scan.disable-telemetry is missing or false', function (): void {
    bindFakeComposer([]);
    $config = Yaml::parse(canonicalTrivyYaml());
    $config['scan']['disable-telemetry'] = false;
    $this->withTempBasePath(canonicalLayout(['trivy.yaml' => Yaml::dump($config, 4, 2)]));

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Invalid value in trivy.yaml: 'scan.disable-telemetry' must equal true");
});

it('hasTrivyConfig fails when disable-vex-notice is missing or false', function (): void {
    bindFakeComposer([]);
    $config = Yaml::parse(canonicalTrivyYaml());
    unset($config['disable-vex-notice']);
    $this->withTempBasePath(canonicalLayout(['trivy.yaml' => Yaml::dump($config, 4, 2)]));

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Invalid value in trivy.yaml: 'disable-vex-notice' must equal true");
});

it('hasTrivyConfig fails when dependency-tree is missing or false', function (): void {
    bindFakeComposer([]);
    $config = Yaml::parse(canonicalTrivyYaml());
    $config['dependency-tree'] = false;
    $this->withTempBasePath(canonicalLayout(['trivy.yaml' => Yaml::dump($config, 4, 2)]));

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Invalid value in trivy.yaml: 'dependency-tree' must equal true");
});

it('hasTrivyConfig fails when scan.skip-files is missing required entries', function (): void {
    bindFakeComposer([]);
    $config = Yaml::parse(canonicalTrivyYaml());
    $config['scan']['skip-files'] = ['.env'];
    $this->withTempBasePath(canonicalLayout(['trivy.yaml' => Yaml::dump($config, 4, 2)]));

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing entries in trivy.yaml: scan.skip-files must include vendor/**/Dockerfile');
});

it('hasTrivyConfig fails when scan.skip-dirs is missing required entries', function (): void {
    bindFakeComposer([]);
    $config = Yaml::parse(canonicalTrivyYaml());
    $config['scan']['skip-dirs'] = ['.ddev/'];
    $this->withTempBasePath(canonicalLayout(['trivy.yaml' => Yaml::dump($config, 4, 2)]));

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing entries in trivy.yaml: scan.skip-dirs must include storage/logs/');
});

it('hasTrivyConfig fails when scan.scanners is missing misconfig', function (): void {
    bindFakeComposer([]);
    $config = Yaml::parse(canonicalTrivyYaml());
    $config['scan']['scanners'] = ['secret', 'vuln'];
    $this->withTempBasePath(canonicalLayout(['trivy.yaml' => Yaml::dump($config, 4, 2)]));

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing entries in trivy.yaml: scan.scanners must include misconfig');
});

it('hasTrivyConfig passes when lists contain canonical entries plus extras', function (): void {
    bindFakeComposer([]);
    $config = Yaml::parse(canonicalTrivyYaml());
    $config['scan']['skip-files'][] = 'tmp/secret.txt';
    $config['scan']['skip-dirs'][] = 'cache/';
    $config['scan']['scanners'][] = 'license';
    $this->withTempBasePath(canonicalLayout(['trivy.yaml' => Yaml::dump($config, 4, 2)]));

    expect(makeCheck(HasTrivyConfigCheck::class)->check())->toBe(CheckResult::PASS);
});

it('hasTrivyConfig passes when extra unrelated top-level keys are present', function (): void {
    bindFakeComposer([]);
    $config = Yaml::parse(canonicalTrivyYaml());
    $config['custom-flag'] = 'whatever';
    $this->withTempBasePath(canonicalLayout(['trivy.yaml' => Yaml::dump($config, 4, 2)]));

    expect(makeCheck(HasTrivyConfigCheck::class)->check())->toBe(CheckResult::PASS);
});

it('hasTrivyConfig auto-fix bootstraps from nothing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.gitlab-ci.yml' => canonicalCiYaml(),
    ]);

    $check = makeCheck(HasTrivyConfigCheck::class);
    $check->fix();

    expect(makeCheck(HasTrivyConfigCheck::class)->check())->toBe(CheckResult::PASS);
    expect(file_exists(base_path('trivy.yaml')))->toBeTrue();
    expect(file_exists(base_path('.trivyignore.yaml')))->toBeTrue();
    expect(file_get_contents(base_path('.gitignore')))->toContain('.trivycache/');
});

it('hasTrivyConfig auto-fix merges while preserving user extras', function (): void {
    bindFakeComposer([]);
    $config = Yaml::parse(canonicalTrivyYaml());
    $config['scan']['skip-dirs'][] = 'custom-dir/';
    $config['scan']['scanners'] = ['secret', 'vuln', 'license'];
    unset($config['ignorefile'], $config['dependency-tree']);
    $this->withTempBasePath(canonicalLayout(['trivy.yaml' => Yaml::dump($config, 4, 2)]));

    $check = makeCheck(HasTrivyConfigCheck::class);
    $check->fix();

    expect(makeCheck(HasTrivyConfigCheck::class)->check())->toBe(CheckResult::PASS);
    $fixed = Yaml::parseFile(base_path('trivy.yaml'));
    expect($fixed['scan']['skip-dirs'])->toContain('custom-dir/');
    expect($fixed['scan']['scanners'])->toContain('license');
    expect($fixed['scan']['scanners'])->toContain('misconfig');
    expect($fixed['ignorefile'])->toBe('.trivyignore.yaml');
    expect($fixed['dependency-tree'])->toBeTrue();
});

it('hasTrivyConfig auto-fix appends .trivycache/ without duplicating existing lines', function (): void {
    bindFakeComposer([]);
    $existing = "vendor/\nnode_modules/\n";
    $this->withTempBasePath(canonicalLayout(['.gitignore' => $existing]));

    $check = makeCheck(HasTrivyConfigCheck::class);
    $check->fix();

    $contents = file_get_contents(base_path('.gitignore'));
    expect($contents)->toContain('vendor/');
    expect($contents)->toContain('node_modules/');
    expect($contents)->toContain('.trivycache/');
    expect(substr_count($contents, '.trivycache/'))->toBe(1);
});

it('hasTrivyConfig passes when trivy.yaml has no severity key', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(canonicalLayout());

    expect(makeCheck(HasTrivyConfigCheck::class)->check())->toBe(CheckResult::PASS);
});

it('hasTrivyConfig fails when trivy.yaml has a severity key', function (): void {
    bindFakeComposer([]);
    $config = Yaml::parse(canonicalTrivyYaml());
    $config['severity'] = ['CRITICAL', 'HIGH'];
    $this->withTempBasePath(canonicalLayout(['trivy.yaml' => Yaml::dump($config, 4, 2)]));

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Forbidden key in trivy.yaml: 'severity' must not be set (use Trivy's default severity behavior)");
});

it('hasTrivyConfig auto-fix removes a severity key', function (): void {
    bindFakeComposer([]);
    $config = Yaml::parse(canonicalTrivyYaml());
    $config['severity'] = ['CRITICAL'];
    $this->withTempBasePath(canonicalLayout(['trivy.yaml' => Yaml::dump($config, 4, 2)]));

    $check = makeCheck(HasTrivyConfigCheck::class);
    $check->fix();

    expect(makeCheck(HasTrivyConfigCheck::class)->check())->toBe(CheckResult::PASS);
    $fixed = Yaml::parseFile(base_path('trivy.yaml'));
    expect($fixed)->not->toHaveKey('severity');
});
