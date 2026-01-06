<?php

use Illuminate\Support\Facades\Schedule;
use Limenet\LaravelBaseline\Checks\Checker;
use Limenet\LaravelBaseline\Enums\CheckResult;

afterEach(function (): void {
    ($this->tempDir ?? null)?->delete();
});

it('usesPest passes when pest packages are present and no disallowed packages', function (): void {
    bindFakeComposer([
        'pestphp/pest' => true,
        'pestphp/pest-plugin-laravel' => true,
        'pestphp/pest-plugin-drift' => false,
        'spatie/phpunit-watcher' => false,
    ]);

    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);
    $checker = new Checker(makeCommand());
    expect($checker->usesPest())->toBe(CheckResult::PASS);
});

it('usesPest fails only when both drift plugin and phpunit-watcher are present (current behavior)', function (): void {
    bindFakeComposer([
        'pestphp/pest' => true,
        'pestphp/pest-plugin-laravel' => true,
        'pestphp/pest-plugin-drift' => true, // disallowed
        'spatie/phpunit-watcher' => true,    // disallowed
    ]);

    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);
    $checker = new Checker(makeCommand());
    expect($checker->usesPest())->toBe(CheckResult::FAIL);
});

it('usesPest still passes when only one of drift or phpunit-watcher is present (documenting current behavior)', function (): void {
    bindFakeComposer([
        'pestphp/pest' => true,
        'pestphp/pest-plugin-laravel' => true,
        'pestphp/pest-plugin-drift' => true, // one present
        'spatie/phpunit-watcher' => false,
    ]);

    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $checker = new Checker(makeCommand());
    expect($checker->usesPest())->toBe(CheckResult::PASS);
});

it('usesIdeHelpers passes with package and post-update scripts', function (): void {
    bindFakeComposer(['barryvdh/laravel-ide-helper' => true]);
    $composer = [
        'scripts' => [
            'post-update-cmd' => [
                'php artisan ide-helper:generate',
                'php artisan ide-helper:meta',
            ],
        ],
    ];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $checker = new Checker(makeCommand());
    expect($checker->usesIdeHelpers())->toBe(CheckResult::PASS);
});

it('bumpsComposer passes when composer bump is in post-update scripts', function (): void {
    bindFakeComposer([]);
    $composer = ['scripts' => ['post-update-cmd' => ['composer bump']]];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $checker = new Checker(makeCommand());
    expect($checker->bumpsComposer())->toBe(CheckResult::PASS);
});

it('usesLaravelHorizon fails when package is missing or post-deploy script is missing', function (): void {
    // FAIL when package missing
    bindFakeComposer(['laravel/horizon' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelHorizon())->toBe(CheckResult::FAIL);

    // FAIL if present but no ci-deploy-post horizon:terminate
    bindFakeComposer(['laravel/horizon' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelHorizon())->toBe(CheckResult::FAIL);

    // PASS when script exists
    bindFakeComposer(['laravel/horizon' => true]);
    $composer = ['scripts' => ['ci-deploy-post' => ['php artisan horizon:terminate']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelHorizon())->toBe(CheckResult::PASS);
});

it('usesLaravelPennant warns when package is missing and fails when post-deploy script is missing', function (): void {
    // WARN when not installed
    bindFakeComposer(['laravel/pennant' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelPennant())->toBe(CheckResult::WARN);

    // FAIL when installed but missing script
    bindFakeComposer(['laravel/pennant' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelPennant())->toBe(CheckResult::FAIL);

    // PASS when script exists
    bindFakeComposer(['laravel/pennant' => true]);
    $composer = ['scripts' => ['ci-deploy-post' => ['php artisan pennant:purge']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelPennant())->toBe(CheckResult::PASS);
});

it('usesLaravelPulse checks scheduled pulse:trim', function (): void {
    // FAIL when not installed
    bindFakeComposer(['laravel/pulse' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelPulse())->toBe(CheckResult::FAIL);

    // FAIL when installed but not scheduled
    bindFakeComposer(['laravel/pulse' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    // no schedule
    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelPulse())->toBe(CheckResult::FAIL);

    // FAIL when scheduled but phpunit.xml missing PULSE_ENABLED = false
    bindFakeComposer(['laravel/pulse' => true]);
    $phpunitXml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <phpunit>
        <php>
            <env name="APP_KEY" value="base64:test"/>
        </php>
    </phpunit>
    XML;
    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'phpunit.xml' => $phpunitXml,
    ]);

    Schedule::command('pulse:trim');
    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelPulse())->toBe(CheckResult::FAIL);

    // PASS when scheduled and phpunit.xml has PULSE_ENABLED = false
    bindFakeComposer(['laravel/pulse' => true]);
    $phpunitXml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <phpunit>
        <php>
            <env name="APP_KEY" value="base64:test"/>
            <env name="PULSE_ENABLED" value="false"/>
        </php>
    </phpunit>
    XML;
    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'phpunit.xml' => $phpunitXml,
    ]);

    Schedule::command('pulse:trim');
    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelPulse())->toBe(CheckResult::PASS);
});

it('doesNotUseIgnition passes only when ignition is not installed', function (): void {
    bindFakeComposer(['spatie/laravel-ignition' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $checker = new Checker(makeCommand());
    expect($checker->doesNotUseIgnition())->toBe(CheckResult::PASS);

    bindFakeComposer(['spatie/laravel-ignition' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $checker = new Checker(makeCommand());
    expect($checker->doesNotUseIgnition())->toBe(CheckResult::FAIL);
});

it('doesNotUseSail passes when sail is not installed and docker-compose.yml is missing', function (): void {
    bindFakeComposer(['laravel/sail' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $checker = new Checker(makeCommand());
    expect($checker->doesNotUseSail())->toBe(CheckResult::PASS);
});

it('doesNotUseSail fails when sail package is installed', function (): void {
    bindFakeComposer(['laravel/sail' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $checker = new Checker(makeCommand());
    expect($checker->doesNotUseSail())->toBe(CheckResult::FAIL);
});

it('doesNotUseSail fails when docker-compose.yml exists', function (): void {
    bindFakeComposer(['laravel/sail' => false]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'docker-compose.yml' => 'version: "3"',
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->doesNotUseSail())->toBe(CheckResult::FAIL);
});

it('doesNotUseSail fails when both sail package and docker-compose.yml exist', function (): void {
    bindFakeComposer(['laravel/sail' => true]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'docker-compose.yml' => 'version: "3"',
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->doesNotUseSail())->toBe(CheckResult::FAIL);
});

it('usesLaravelTelescope requires package, post-update script and schedule', function (): void {
    // Missing package -> FAIL
    bindFakeComposer(['laravel/telescope' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelTelescope())->toBe(CheckResult::FAIL);

    // With package but missing script/schedule -> FAIL
    bindFakeComposer(['laravel/telescope' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelTelescope())->toBe(CheckResult::FAIL);

    // With script but missing schedule -> FAIL
    bindFakeComposer(['laravel/telescope' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan telescope:publish']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelTelescope())->toBe(CheckResult::FAIL);

    // With script and schedule but missing phpunit.xml TELESCOPE_ENABLED -> FAIL
    bindFakeComposer(['laravel/telescope' => true]);
    $phpunitXml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <phpunit>
        <php>
            <env name="APP_KEY" value="base64:test"/>
        </php>
    </phpunit>
    XML;
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'phpunit.xml' => $phpunitXml,
    ]);

    Schedule::command('telescope:prune');
    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelTelescope())->toBe(CheckResult::FAIL);

    // With script, schedule and phpunit.xml TELESCOPE_ENABLED = false -> PASS
    bindFakeComposer(['laravel/telescope' => true]);
    $phpunitXml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <phpunit>
        <php>
            <env name="APP_KEY" value="base64:test"/>
            <env name="TELESCOPE_ENABLED" value="false"/>
        </php>
    </phpunit>
    XML;
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'phpunit.xml' => $phpunitXml,
    ]);

    Schedule::command('telescope:prune');
    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelTelescope())->toBe(CheckResult::PASS);
});

it('usesLimenetPintConfig requires package and post-update publish script', function (): void {
    // FAIL when missing package or script
    bindFakeComposer(['limenet/laravel-pint-config' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLimenetPintConfig())->toBe(CheckResult::FAIL);

    // PASS when both present
    bindFakeComposer(['limenet/laravel-pint-config' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan laravel-pint-config:publish']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLimenetPintConfig())->toBe(CheckResult::PASS);
});

it('callsBaseline checks post-update script', function (): void {
    bindFakeComposer([]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan limenet:laravel-baseline:check']]];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $checker = new Checker(makeCommand());
    expect($checker->callsBaseline())->toBe(CheckResult::PASS);
});

it('hasCiJobs parses gitlab-ci.yml for required jobs', function (): void {
    bindFakeComposer([]);
    $yaml = <<<'YML'
build:
  extends: ['.build']
php:
  extends: ['.lint_php']
js:
  extends: ['.lint_js']
test:
  extends: ['.test']
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.gitlab-ci.yml' => $yaml,
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->hasCiJobs())->toBe(CheckResult::PASS);
});

it('hasCiJobs allows additional keys like before_script in job definitions', function (): void {
    bindFakeComposer([]);
    $yaml = <<<'YML'
build:
  extends: ['.build']
  before_script:
    - composer install
php:
  extends: ['.lint_php']
  variables:
    PHP_CS_FIXER_IGNORE_ENV: 1
js:
  extends: ['.lint_js']
  before_script:
    - npm install
test:
  extends: ['.test']
  artifacts:
    reports:
      junit: report.xml
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.gitlab-ci.yml' => $yaml,
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->hasCiJobs())->toBe(CheckResult::PASS);
});

it('callsSentryHook behaves based on package and YAML configuration', function (): void {
    // WARN when sentry not installed
    bindFakeComposer(['sentry/sentry-laravel' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp']), '.gitlab-ci.yml' => '']);

    $checker = new Checker(makeCommand());
    expect($checker->callsSentryHook())->toBe(CheckResult::WARN);

    // FAIL when installed but wrong config
    bindFakeComposer(['sentry/sentry-laravel' => true]);
    $yamlFail = "release:\n  extends: ['.wrong']\n  variables:\n    SENTRY_RELEASE_WEBHOOK: 'https://example.com'\n";
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp']), '.gitlab-ci.yml' => $yamlFail]);

    $checker = new Checker(makeCommand());
    expect($checker->callsSentryHook())->toBe(CheckResult::FAIL);

    // PASS when installed and correct config
    bindFakeComposer(['sentry/sentry-laravel' => true]);
    $yamlOk = "release:\n  extends: ['.release']\n  variables:\n    SENTRY_RELEASE_WEBHOOK: 'https://sentry.io/api/hooks/release/builtin/abc'\n";
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp']), '.gitlab-ci.yml' => $yamlOk]);

    $checker = new Checker(makeCommand());
    expect($checker->callsSentryHook())->toBe(CheckResult::PASS);
});

it('usesPredis fails when not installed and passes when installed', function (): void {
    bindFakeComposer(['predis/predis' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesPredis())->toBe(CheckResult::FAIL);

    bindFakeComposer(['predis/predis' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesPredis())->toBe(CheckResult::PASS);
});

it('usesSpatieHealth requires scheduled health tasks', function (): void {
    // WARN when not installed
    bindFakeComposer(['spatie/laravel-health' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesSpatieHealth())->toBe(CheckResult::WARN);

    // FAIL when installed but not scheduled
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesSpatieHealth())->toBe(CheckResult::FAIL);

    // PASS when scheduled
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    Schedule::command('health:check');
    Schedule::command('health:schedule-check-heartbeat');
    expect((new Checker(makeCommand()))->usesSpatieHealth())->toBe(CheckResult::PASS);
});

it('usesSpatieBackup requires scheduled backup tasks', function (): void {
    // WARN when not installed
    bindFakeComposer(['spatie/laravel-backup' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesSpatieBackup())->toBe(CheckResult::WARN);

    // FAIL when installed but not scheduled
    bindFakeComposer(['spatie/laravel-backup' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesSpatieBackup())->toBe(CheckResult::FAIL);

    // PASS when scheduled
    bindFakeComposer(['spatie/laravel-backup' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    expect((new Checker(makeCommand()))->usesSpatieBackup())->toBe(CheckResult::PASS);
});

it('usesRector fails unless both rector packages installed and ci-lint script configured', function (): void {
    // FAIL when packages not installed
    bindFakeComposer(['rector/rector' => true, 'driftingly/rector-laravel' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesRector())->toBe(CheckResult::FAIL);

    // FAIL when packages installed but ci-lint script missing
    bindFakeComposer(['rector/rector' => true, 'driftingly/rector-laravel' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    expect((new Checker(makeCommand()))->usesRector())->toBe(CheckResult::FAIL);

    // PASS when packages installed and ci-lint script configured
    bindFakeComposer(['rector/rector' => true, 'driftingly/rector-laravel' => true]);
    $composer = ['scripts' => ['ci-lint' => ['rector']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect((new Checker(makeCommand()))->usesRector())->toBe(CheckResult::PASS);
});

it('usesLarastan passes only when larastan is installed', function (): void {
    bindFakeComposer(['larastan/larastan' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesLarastan())->toBe(CheckResult::FAIL);

    bindFakeComposer(['larastan/larastan' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesLarastan())->toBe(CheckResult::PASS);
});

it('usesPhpstanExtensions passes only when both extension packages are installed', function (): void {
    bindFakeComposer(['phpstan/phpstan-deprecation-rules' => true, 'phpstan/phpstan-strict-rules' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesPhpstanExtensions())->toBe(CheckResult::FAIL);

    bindFakeComposer(['phpstan/extension-installer' => true, 'phpstan/phpstan-deprecation-rules' => true, 'phpstan/phpstan-strict-rules' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesPhpstanExtensions())->toBe(CheckResult::PASS);
});

it('usesPhpInsights passes only when phpinsights is installed and ci-lint scripts are configured', function (): void {
    // FAIL when package not installed
    bindFakeComposer(['nunomaduro/phpinsights' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesPhpInsights())->toBe(CheckResult::FAIL);

    // FAIL when package installed but ci-lint scripts missing
    bindFakeComposer(['nunomaduro/phpinsights' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    expect((new Checker(makeCommand()))->usesPhpInsights())->toBe(CheckResult::FAIL);

    // PASS when package installed and ci-lint scripts configured
    bindFakeComposer(['nunomaduro/phpinsights' => true]);
    $composer = [
        'scripts' => [
            'ci-lint' => [
                'insights --summary --no-interaction',
                'insights -n --ansi --format=codeclimate > codeclimate-report.json 2>/dev/null',
            ],
        ],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect((new Checker(makeCommand()))->usesPhpInsights())->toBe(CheckResult::PASS);
});

it('isLaravelVersionMaintained passes for Laravel >= 11', function (): void {
    // The dev setup for this package targets Laravel 11/12.
    bindFakeComposer([]);

    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->isLaravelVersionMaintained())->toBe(CheckResult::PASS);
});

it('hasEncryptedEnvFile detects encrypted env files in base path', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath(['.env.prod.encrypted' => 'dummy']);

    expect((new Checker(makeCommand()))->hasEncryptedEnvFile())->toBe(CheckResult::PASS);
});

it('isCiLintComplete checks ci-lint composer script contents', function (): void {
    bindFakeComposer([]);
    $scriptsOk = [
        'ci-lint' => [
            'pint --parallel',
            'phpstan',
        ],
    ];
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => $scriptsOk])]);

    expect((new Checker(makeCommand()))->isCiLintComplete())->toBe(CheckResult::PASS);

    $scriptsBad = ['ci-lint' => ['pint --parallel']];
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => $scriptsBad])]);

    expect((new Checker(makeCommand()))->isCiLintComplete())->toBe(CheckResult::FAIL);
});

it('checkPhpunit fails when cobertura or junit or APP_KEY is missing', function (): void {
    bindFakeComposer([]);
    $xmlMissing = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <coverage><report></report></coverage>
  <logging></logging>
  <php></php>
</phpunit>
XML;

    $this->withTempBasePath(['phpunit.xml' => $xmlMissing, 'composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->checkPhpunit())->toBe(CheckResult::FAIL);
});

it('checkPhpunit passes when cobertura, junit and APP_KEY are configured', function (): void {
    bindFakeComposer([]);
    $phpunitXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <coverage>
        <report>
            <cobertura outputFile="cobertura.xml"/>
        </report>
    </coverage>
    <logging>
        <junit outputFile="report.xml"/>
    </logging>
    <php>
        <env name="APP_KEY" value="base64:somekey"/>
    </php>
    <source>
        <include>
            <directory suffix=".php">./app</directory>
        </include>
    </source>
</phpunit>
XML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'phpunit.xml' => $phpunitXml,
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->checkPhpunit())->toBe(CheckResult::PASS);
});

it('checkPhpunit fails when phpunit.xml is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $checker = new Checker(makeCommand());
    expect($checker->checkPhpunit())->toBe(CheckResult::FAIL);
});

it('checkPhpunit fails when cobertura is missing', function (): void {
    bindFakeComposer([]);
    $phpunitXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <logging>
        <junit outputFile="report.xml"/>
    </logging>
    <php>
        <env name="APP_KEY" value="base64:somekey"/>
    </php>
    <source>
        <include>
            <directory suffix=".php">./app</directory>
        </include>
    </source>
</phpunit>
XML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'phpunit.xml' => $phpunitXml,
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->checkPhpunit())->toBe(CheckResult::FAIL);
});

it('checkPhpunit fails when junit is missing', function (): void {
    bindFakeComposer([]);
    $phpunitXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <coverage>
        <report>
            <cobertura outputFile="cobertura.xml"/>
        </report>
    </coverage>
    <php>
        <env name="APP_KEY" value="base64:somekey"/>
    </php>
    <source>
        <include>
            <directory suffix=".php">./app</directory>
        </include>
    </source>
</phpunit>
XML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'phpunit.xml' => $phpunitXml,
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->checkPhpunit())->toBe(CheckResult::FAIL);
});

it('checkPhpunit fails when APP_KEY is missing', function (): void {
    bindFakeComposer([]);
    $phpunitXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <coverage>
        <report>
            <cobertura outputFile="cobertura.xml"/>
        </report>
    </coverage>
    <logging>
        <junit outputFile="report.xml"/>
    </logging>
    <php>
    </php>
    <source>
        <include>
            <directory suffix=".php">./app</directory>
        </include>
    </source>
</phpunit>
XML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'phpunit.xml' => $phpunitXml,
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->checkPhpunit())->toBe(CheckResult::FAIL);
});

it('checkPhpunit fails when source configuration is missing', function (): void {
    bindFakeComposer([]);
    $phpunitXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <coverage>
        <report>
            <cobertura outputFile="cobertura.xml"/>
        </report>
    </coverage>
    <logging>
        <junit outputFile="report.xml"/>
    </logging>
    <php>
        <env name="APP_KEY" value="base64:somekey"/>
    </php>
</phpunit>
XML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'phpunit.xml' => $phpunitXml,
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->checkPhpunit())->toBe(CheckResult::FAIL);
});

it('checkPhpunit fails when source directory is incorrect', function (): void {
    bindFakeComposer([]);
    $phpunitXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <coverage>
        <report>
            <cobertura outputFile="cobertura.xml"/>
        </report>
    </coverage>
    <logging>
        <junit outputFile="report.xml"/>
    </logging>
    <php>
        <env name="APP_KEY" value="base64:somekey"/>
    </php>
    <source>
        <include>
            <directory suffix=".php">./src</directory>
        </include>
    </source>
</phpunit>
XML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'phpunit.xml' => $phpunitXml,
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->checkPhpunit())->toBe(CheckResult::FAIL);
});

it('checkPhpunit throws on invalid XML', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath(['phpunit.xml' => '<phpunit>', 'composer.json' => json_encode(['name' => 'tmp'])]);

    $checker = new Checker(makeCommand());
    expect(fn () => $checker->checkPhpunit())->toThrow(Exception::class);
});

it('hasCiJobs fails when required jobs are missing or not extending the correct templates', function (): void {
    bindFakeComposer([]);
    $yaml = "build:\n  extends: ['.wrong']\n";

    $this->withTempBasePath(['.gitlab-ci.yml' => $yaml, 'composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->hasCiJobs())->toBe(CheckResult::FAIL);
});

it('usesLaravelBoost fails when not installed and passes when installed', function (): void {
    bindFakeComposer(['laravel/boost' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesLaravelBoost())->toBe(CheckResult::FAIL);

    bindFakeComposer(['laravel/boost' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan boost:update']]];
    $boostConfig = [
        'agents' => ['claude_code', 'phpstorm'],
        'editors' => ['claude_code', 'phpstorm', 'vscode'],
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelBoost())->toBe(CheckResult::PASS);
});

it('usesLaravelBoost fails when boost.json is missing', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan boost:update']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelBoost())->toBe(CheckResult::FAIL);

    $comments = $checker->getComments();
    expect($comments)->toContain('Laravel Boost configuration missing: Create boost.json in project root');
});

it('usesLaravelBoost fails when boost.json has missing agents', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan boost:update']]];
    $boostConfig = [
        'agents' => ['claude_code'],  // missing phpstorm
        'editors' => ['claude_code', 'phpstorm', 'vscode'],
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelBoost())->toBe(CheckResult::FAIL);

    $comments = $checker->getComments();
    expect($comments)->toContain('Laravel Boost configuration incomplete: boost.json must include agents: claude_code, phpstorm');
});

it('usesLaravelBoost fails when boost.json has missing editors', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan boost:update']]];
    $boostConfig = [
        'agents' => ['claude_code', 'phpstorm'],
        'editors' => ['claude_code', 'phpstorm'],  // missing vscode
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelBoost())->toBe(CheckResult::FAIL);

    $comments = $checker->getComments();
    expect($comments)->toContain('Laravel Boost configuration incomplete: boost.json must include editors: claude_code, phpstorm, vscode');
});

it('usesLaravelBoost fails when boost.json has empty agents array', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan boost:update']]];
    $boostConfig = [
        'agents' => [],
        'editors' => ['claude_code', 'phpstorm', 'vscode'],
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelBoost())->toBe(CheckResult::FAIL);
});

it('usesLaravelBoost fails when boost.json has empty editors array', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan boost:update']]];
    $boostConfig = [
        'agents' => ['claude_code', 'phpstorm'],
        'editors' => [],
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelBoost())->toBe(CheckResult::FAIL);
});

it('usesLaravelBoost passes when boost.json has extra agents and editors', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan boost:update']]];
    $boostConfig = [
        'agents' => ['claude_code', 'phpstorm', 'cursor'],  // extra agent
        'editors' => ['claude_code', 'phpstorm', 'vscode', 'sublime'],  // extra editor
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelBoost())->toBe(CheckResult::PASS);
});

it('hasCompleteRectorConfiguration fails when file missing and passes when configuration is complete', function (): void {
    bindFakeComposer([]);
    // Missing file -> FAIL
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->hasCompleteRectorConfiguration())->toBe(CheckResult::FAIL);

    // Complete config -> PASS
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;
use RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector;
use Rector\CodingStyle\Rector\FunctionLike\FunctionLikeToFirstClassCallableRector;

return static function (RectorConfig $config): void {
    $config
        ->withPaths([
            __DIR__.'/app',
            __DIR__.'/database',
            __DIR__.'/routes',
            __DIR__.'/tests',
        ])
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            codingStyle: true,
            typeDeclarations: true,
            privatization: true,
            instanceOf: true,
            earlyReturn: true,
        )
        ->withPhpSets()
        ->withAttributesSets()
        ->withImportNames(importShortClasses: false)
        ->withRules([
            AddGenericReturnTypeToRelationsRector::class,
        ])
        ->withSkip([
            FunctionLikeToFirstClassCallableRector::class,
        ]);

    $config->withSetProviders(LaravelSetProvider::class);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->hasCompleteRectorConfiguration())->toBe(CheckResult::PASS);
});

it('hasCompleteRectorConfiguration provides specific error message for missing withComposerBased arguments', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;

return static function (RectorConfig $config): void {
    $config->withComposerBased(phpunit: true, symfony: false, laravel: true);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $checker = new Checker(makeCommand());
    $result = $checker->hasCompleteRectorConfiguration();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $checker->getComments();
    expect($comments)->toHaveCount(1);
    expect($comments[0])->toContain('withComposerBased()')
        ->toContain('Expected named arguments: phpunit: true, symfony: true, laravel: true');
});

it('hasCompleteRectorConfiguration provides specific error message for missing withPreparedSets arguments', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;

return static function (RectorConfig $config): void {
    $config
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(deadCode: true, codeQuality: true);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $checker = new Checker(makeCommand());
    $result = $checker->hasCompleteRectorConfiguration();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $checker->getComments();
    expect($comments)->toHaveCount(1);
    expect($comments[0])->toContain('withPreparedSets()')
        ->toContain('Expected named arguments')
        ->toContain('deadCode: true')
        ->toContain('typeDeclarations: true');
});

it('hasCompleteRectorConfiguration provides specific error message for missing withPhpSets call', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;

return static function (RectorConfig $config): void {
    $config
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            codingStyle: true,
            typeDeclarations: true,
            privatization: true,
            instanceOf: true,
            earlyReturn: true,
        )
        ->withImportNames(importShortClasses: false);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $checker = new Checker(makeCommand());
    $result = $checker->hasCompleteRectorConfiguration();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $checker->getComments();
    expect($comments)->toHaveCount(1);
    expect($comments[0])->toContain('Missing call to withPhpSets()');
});

it('hasCompleteRectorConfiguration provides specific error message for missing LaravelSetProvider', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;

return static function (RectorConfig $config): void {
    $config
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            codingStyle: true,
            typeDeclarations: true,
            privatization: true,
            instanceOf: true,
            earlyReturn: true,
        )
        ->withPhpSets()
        ->withAttributesSets()
        ->withImportNames(importShortClasses: false);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $checker = new Checker(makeCommand());
    $result = $checker->hasCompleteRectorConfiguration();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $checker->getComments();
    expect($comments)->toHaveCount(1);
    expect($comments[0])->toContain('withSetProviders()')
        ->toContain('LaravelSetProvider');
});

it('hasCompleteRectorConfiguration provides specific error message for missing withRules argument', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;

return static function (RectorConfig $config): void {
    $config
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            codingStyle: true,
            typeDeclarations: true,
            privatization: true,
            instanceOf: true,
            earlyReturn: true,
        )
        ->withPhpSets()
        ->withAttributesSets()
        ->withImportNames(importShortClasses: false)
        ->withSetProviders(LaravelSetProvider::class)
        ->withRules([]);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $checker = new Checker(makeCommand());
    $result = $checker->hasCompleteRectorConfiguration();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $checker->getComments();
    expect($comments)->toHaveCount(1);
    expect($comments[0])->toContain('withRules()')
        ->toContain('AddGenericReturnTypeToRelationsRector');
});

it('hasCompleteRectorConfiguration provides specific error message for missing withPaths', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;
use RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector;
use Rector\CodingStyle\Rector\FunctionLike\FunctionLikeToFirstClassCallableRector;

return static function (RectorConfig $config): void {
    $config
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            codingStyle: true,
            typeDeclarations: true,
            privatization: true,
            instanceOf: true,
            earlyReturn: true,
        )
        ->withPhpSets()
        ->withAttributesSets()
        ->withImportNames(importShortClasses: false)
        ->withSetProviders(LaravelSetProvider::class)
        ->withRules([
            AddGenericReturnTypeToRelationsRector::class,
        ])
        ->withSkip([
            FunctionLikeToFirstClassCallableRector::class,
        ]);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $checker = new Checker(makeCommand());
    $result = $checker->hasCompleteRectorConfiguration();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $checker->getComments();
    expect($comments)->toHaveCount(1);
    expect($comments[0])->toContain('withPaths()')
        ->toContain('app, database, routes, tests');
});

it('hasCompleteRectorConfiguration provides specific error message for incomplete withPaths', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;
use RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector;
use Rector\CodingStyle\Rector\FunctionLike\FunctionLikeToFirstClassCallableRector;

return static function (RectorConfig $config): void {
    $config
        ->withPaths([
            __DIR__.'/app',
            __DIR__.'/tests',
        ])
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            codingStyle: true,
            typeDeclarations: true,
            privatization: true,
            instanceOf: true,
            earlyReturn: true,
        )
        ->withPhpSets()
        ->withAttributesSets()
        ->withImportNames(importShortClasses: false)
        ->withSetProviders(LaravelSetProvider::class)
        ->withRules([
            AddGenericReturnTypeToRelationsRector::class,
        ])
        ->withSkip([
            FunctionLikeToFirstClassCallableRector::class,
        ]);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $checker = new Checker(makeCommand());
    $result = $checker->hasCompleteRectorConfiguration();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $checker->getComments();
    expect($comments)->toHaveCount(1);
    expect($comments[0])->toContain('withPaths()')
        ->toContain('app, database, routes, tests');
});

it('phpVersionMatchesCi passes when composer PHP constraint matches CI PHP_VERSION', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.2']];
    $gitlabCi = <<<'YML'
variables:
  PHP_VERSION: "8.2"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.gitlab-ci.yml' => $gitlabCi,
    ]);

    expect((new Checker(makeCommand()))->phpVersionMatchesCi())->toBe(CheckResult::PASS);
});

it('phpVersionMatchesCi fails when composer PHP constraint does not match CI PHP_VERSION', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.2']];
    $gitlabCi = <<<'YML'
variables:
  PHP_VERSION: "8.3"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.gitlab-ci.yml' => $gitlabCi,
    ]);

    expect((new Checker(makeCommand()))->phpVersionMatchesCi())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesCi fails when composer.json is missing', function (): void {
    bindFakeComposer([]);
    $gitlabCi = <<<'YML'
variables:
  PHP_VERSION: "8.2"
YML;

    $this->withTempBasePath(['.gitlab-ci.yml' => $gitlabCi]);

    expect((new Checker(makeCommand()))->phpVersionMatchesCi())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesCi fails when PHP constraint is missing from composer.json', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => []];
    $gitlabCi = <<<'YML'
variables:
  PHP_VERSION: "8.2"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.gitlab-ci.yml' => $gitlabCi,
    ]);

    expect((new Checker(makeCommand()))->phpVersionMatchesCi())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesCi fails when .gitlab-ci.yml is missing', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.2']];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect((new Checker(makeCommand()))->phpVersionMatchesCi())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesCi fails when PHP_VERSION is missing from .gitlab-ci.yml', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.2']];
    $gitlabCi = <<<'YML'
variables:
  OTHER_VAR: "value"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.gitlab-ci.yml' => $gitlabCi,
    ]);

    expect((new Checker(makeCommand()))->phpVersionMatchesCi())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesCi handles PHP constraint without caret', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '8.2']];
    $gitlabCi = <<<'YML'
variables:
  PHP_VERSION: "8.2"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.gitlab-ci.yml' => $gitlabCi,
    ]);

    expect((new Checker(makeCommand()))->phpVersionMatchesCi())->toBe(CheckResult::PASS);
});

it('phpVersionMatchesCi works with different PHP versions', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.3']];
    $gitlabCi = <<<'YML'
variables:
  PHP_VERSION: "8.3"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.gitlab-ci.yml' => $gitlabCi,
    ]);

    expect((new Checker(makeCommand()))->phpVersionMatchesCi())->toBe(CheckResult::PASS);
});

it('phpVersionMatchesDdev passes when composer PHP constraint matches DDEV php_version', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.2']];
    $ddevConfig = <<<'YML'
name: test-project
type: php
docroot: public
php_version: "8.2"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.ddev/config.yaml' => $ddevConfig,
    ]);

    expect((new Checker(makeCommand()))->phpVersionMatchesDdev())->toBe(CheckResult::PASS);
});

it('phpVersionMatchesDdev fails when composer PHP constraint does not match DDEV php_version', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.2']];
    $ddevConfig = <<<'YML'
name: test-project
type: php
docroot: public
php_version: "8.3"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.ddev/config.yaml' => $ddevConfig,
    ]);

    expect((new Checker(makeCommand()))->phpVersionMatchesDdev())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesDdev fails when composer.json is missing', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
php_version: "8.2"
YML;

    $this->withTempBasePath(['.ddev/config.yaml' => $ddevConfig]);

    expect((new Checker(makeCommand()))->phpVersionMatchesDdev())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesDdev fails when PHP constraint is missing from composer.json', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => []];
    $ddevConfig = <<<'YML'
name: test-project
php_version: "8.2"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.ddev/config.yaml' => $ddevConfig,
    ]);

    expect((new Checker(makeCommand()))->phpVersionMatchesDdev())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesDdev fails when .ddev/config.yaml is missing', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.2']];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect((new Checker(makeCommand()))->phpVersionMatchesDdev())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesDdev fails when php_version is missing from .ddev/config.yaml', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.2']];
    $ddevConfig = <<<'YML'
name: test-project
type: php
docroot: public
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.ddev/config.yaml' => $ddevConfig,
    ]);

    expect((new Checker(makeCommand()))->phpVersionMatchesDdev())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesDdev handles PHP constraint without caret', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '8.2']];
    $ddevConfig = <<<'YML'
name: test-project
php_version: "8.2"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.ddev/config.yaml' => $ddevConfig,
    ]);

    expect((new Checker(makeCommand()))->phpVersionMatchesDdev())->toBe(CheckResult::PASS);
});

it('phpVersionMatchesDdev works with different PHP versions', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.3']];
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.3"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.ddev/config.yaml' => $ddevConfig,
    ]);

    expect((new Checker(makeCommand()))->phpVersionMatchesDdev())->toBe(CheckResult::PASS);
});

it('ddevHasPcovPackage passes when all requirements are met', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.2"
webimage_extra_packages: ["php${DDEV_PHP_VERSION}-pcov", "php${DDEV_PHP_VERSION}-bcmath"]
YML;

    $customIni = <<<'INI'
[PHP]
opcache.jit=disable
opcache.jit_buffer_size=0
INI;

    $this->withTempBasePath([
        '.ddev/config.yaml' => $ddevConfig,
        '.ddev/php/90-custom.ini' => $customIni,
    ]);

    expect((new Checker(makeCommand()))->ddevHasPcovPackage())->toBe(CheckResult::PASS);
});

it('ddevHasPcovPackage fails when .ddev/config.yaml is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    expect((new Checker(makeCommand()))->ddevHasPcovPackage())->toBe(CheckResult::FAIL);
});

it('ddevHasPcovPackage fails when webimage_extra_packages is missing', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.2"
YML;

    $this->withTempBasePath(['.ddev/config.yaml' => $ddevConfig]);

    expect((new Checker(makeCommand()))->ddevHasPcovPackage())->toBe(CheckResult::FAIL);
});

it('ddevHasPcovPackage fails when webimage_extra_packages is not an array', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.2"
webimage_extra_packages: "php${DDEV_PHP_VERSION}-pcov"
YML;

    $this->withTempBasePath(['.ddev/config.yaml' => $ddevConfig]);

    expect((new Checker(makeCommand()))->ddevHasPcovPackage())->toBe(CheckResult::FAIL);
});

it('ddevHasPcovPackage fails when pcov package is not in the list', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.2"
webimage_extra_packages: ["php${DDEV_PHP_VERSION}-bcmath"]
YML;

    $customIni = <<<'INI'
[PHP]
opcache.jit=disable
INI;

    $this->withTempBasePath([
        '.ddev/config.yaml' => $ddevConfig,
        '.ddev/php/90-custom.ini' => $customIni,
    ]);

    expect((new Checker(makeCommand()))->ddevHasPcovPackage())->toBe(CheckResult::FAIL);
});

it('ddevHasPcovPackage fails when .ddev/php/90-custom.ini is missing', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.2"
webimage_extra_packages: ["php${DDEV_PHP_VERSION}-pcov"]
YML;

    $this->withTempBasePath(['.ddev/config.yaml' => $ddevConfig]);

    expect((new Checker(makeCommand()))->ddevHasPcovPackage())->toBe(CheckResult::FAIL);
});

it('ddevHasPcovPackage fails when 90-custom.ini does not start with [PHP]', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.2"
webimage_extra_packages: ["php${DDEV_PHP_VERSION}-pcov"]
YML;

    $customIni = <<<'INI'
opcache.jit=disable
INI;

    $this->withTempBasePath([
        '.ddev/config.yaml' => $ddevConfig,
        '.ddev/php/90-custom.ini' => $customIni,
    ]);

    expect((new Checker(makeCommand()))->ddevHasPcovPackage())->toBe(CheckResult::FAIL);
});

it('ddevHasPcovPackage fails when 90-custom.ini does not contain opcache.jit=disable', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.2"
webimage_extra_packages: ["php${DDEV_PHP_VERSION}-pcov"]
YML;

    $customIni = <<<'INI'
[PHP]
memory_limit=512M
INI;

    $this->withTempBasePath([
        '.ddev/config.yaml' => $ddevConfig,
        '.ddev/php/90-custom.ini' => $customIni,
    ]);

    expect((new Checker(makeCommand()))->ddevHasPcovPackage())->toBe(CheckResult::FAIL);
});

it('ddevHasPcovPackage passes with [PHP] and whitespace at start', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.2"
webimage_extra_packages: ["php${DDEV_PHP_VERSION}-pcov"]
YML;

    $customIni = <<<'INI'
  [PHP]
opcache.jit=disable
INI;

    $this->withTempBasePath([
        '.ddev/config.yaml' => $ddevConfig,
        '.ddev/php/90-custom.ini' => $customIni,
    ]);

    expect((new Checker(makeCommand()))->ddevHasPcovPackage())->toBe(CheckResult::PASS);
});

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

    expect((new Checker(makeCommand()))->usesReleaseIt())->toBe(CheckResult::PASS);
});

it('usesReleaseIt fails when package.json is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    expect((new Checker(makeCommand()))->usesReleaseIt())->toBe(CheckResult::FAIL);
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

    expect((new Checker(makeCommand()))->usesReleaseIt())->toBe(CheckResult::FAIL);
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

    expect((new Checker(makeCommand()))->usesReleaseIt())->toBe(CheckResult::FAIL);
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

    expect((new Checker(makeCommand()))->usesReleaseIt())->toBe(CheckResult::FAIL);
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

    expect((new Checker(makeCommand()))->usesReleaseIt())->toBe(CheckResult::FAIL);
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

    expect((new Checker(makeCommand()))->usesReleaseIt())->toBe(CheckResult::FAIL);
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

    expect((new Checker(makeCommand()))->usesReleaseIt())->toBe(CheckResult::FAIL);
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

    expect((new Checker(makeCommand()))->usesReleaseIt())->toBe(CheckResult::FAIL);
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

    expect((new Checker(makeCommand()))->usesReleaseIt())->toBe(CheckResult::FAIL);
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

    expect((new Checker(makeCommand()))->usesReleaseIt())->toBe(CheckResult::FAIL);
});

it('hasNpmScripts passes when ci-lint and production scripts exist', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
        'scripts' => [
            'ci-lint' => 'prettier --check .',
            'production' => 'vite build',
        ],
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
    ]);

    expect((new Checker(makeCommand()))->hasNpmScripts())->toBe(CheckResult::PASS);
});

it('hasNpmScripts fails when package.json is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    expect((new Checker(makeCommand()))->hasNpmScripts())->toBe(CheckResult::FAIL);
});

it('hasNpmScripts fails when ci-lint script is missing', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
        'scripts' => [
            'production' => 'vite build',
        ],
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
    ]);

    expect((new Checker(makeCommand()))->hasNpmScripts())->toBe(CheckResult::FAIL);
});

it('hasNpmScripts fails when production script is missing', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
        'scripts' => [
            'ci-lint' => 'prettier --check .',
        ],
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
    ]);

    expect((new Checker(makeCommand()))->hasNpmScripts())->toBe(CheckResult::FAIL);
});

it('hasNpmScripts fails when scripts section is missing', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
    ]);

    expect((new Checker(makeCommand()))->hasNpmScripts())->toBe(CheckResult::FAIL);
});

it('hasGuidelinesUpdateScript passes when guidelines update script is in post-update-cmd', function (): void {
    bindFakeComposer([]);
    $composer = [
        'scripts' => [
            'post-update-cmd' => [
                '@php artisan limenet:laravel-baseline:guidelines',
            ],
        ],
    ];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $checker = new Checker(makeCommand());
    expect($checker->hasGuidelinesUpdateScript())->toBe(CheckResult::PASS);
});

it('hasGuidelinesUpdateScript fails when guidelines update script is missing', function (): void {
    bindFakeComposer([]);
    $composer = [
        'scripts' => [
            'post-update-cmd' => [
                '@php artisan ide-helper:generate',
            ],
        ],
    ];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $checker = new Checker(makeCommand());
    expect($checker->hasGuidelinesUpdateScript())->toBe(CheckResult::FAIL);
});

it('hasGuidelinesUpdateScript fails when composer.json is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    $checker = new Checker(makeCommand());
    expect($checker->hasGuidelinesUpdateScript())->toBe(CheckResult::FAIL);
});

it('hasGuidelinesUpdateScript fails when post-update-cmd section is missing', function (): void {
    bindFakeComposer([]);
    $composer = [
        'scripts' => [],
    ];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $checker = new Checker(makeCommand());
    expect($checker->hasGuidelinesUpdateScript())->toBe(CheckResult::FAIL);
});

it('hasGuidelinesUpdateScript provides helpful comment when script is missing', function (): void {
    bindFakeComposer([]);
    $composer = [
        'scripts' => [
            'post-update-cmd' => [],
        ],
    ];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $checker = new Checker(makeCommand());
    $result = $checker->hasGuidelinesUpdateScript();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $checker->getComments();
    expect($comments)->toContain('Missing guidelines update script in composer.json: Add "@php artisan limenet:laravel-baseline:guidelines" to post-update-cmd section');
});

it('hasGuidelinesUpdateScript passes when guidelines comes before boost:update', function (): void {
    bindFakeComposer([]);
    $composer = [
        'scripts' => [
            'post-update-cmd' => [
                '@php artisan limenet:laravel-baseline:guidelines',
                '@php artisan boost:update',
            ],
        ],
    ];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $checker = new Checker(makeCommand());
    expect($checker->hasGuidelinesUpdateScript())->toBe(CheckResult::PASS);
});

it('hasGuidelinesUpdateScript fails when guidelines comes after boost:update', function (): void {
    bindFakeComposer([]);
    $composer = [
        'scripts' => [
            'post-update-cmd' => [
                '@php artisan boost:update',
                '@php artisan limenet:laravel-baseline:guidelines',
            ],
        ],
    ];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $checker = new Checker(makeCommand());
    $result = $checker->hasGuidelinesUpdateScript();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $checker->getComments();
    expect($comments)->toContain('Guidelines update script must be called before boost:update in composer.json post-update-cmd section');
});

it('hasGuidelinesUpdateScript passes when only guidelines exists without boost', function (): void {
    bindFakeComposer([]);
    $composer = [
        'scripts' => [
            'post-update-cmd' => [
                '@php artisan limenet:laravel-baseline:guidelines',
                '@php artisan ide-helper:generate',
            ],
        ],
    ];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $checker = new Checker(makeCommand());
    expect($checker->hasGuidelinesUpdateScript())->toBe(CheckResult::PASS);
});

it('ddevMutagenIgnoresNodeModules passes when mutagen.yml has /node_modules in ignore paths', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
        - "/.git"
YML;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
    ]);

    expect((new Checker(makeCommand()))->ddevMutagenIgnoresNodeModules())->toBe(CheckResult::PASS);
});

it('ddevMutagenIgnoresNodeModules fails when mutagen.yml is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    expect((new Checker(makeCommand()))->ddevMutagenIgnoresNodeModules())->toBe(CheckResult::FAIL);
});

it('ddevMutagenIgnoresNodeModules fails when /node_modules is not in ignore paths', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/.git"
        - "/vendor"
YML;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
    ]);

    expect((new Checker(makeCommand()))->ddevMutagenIgnoresNodeModules())->toBe(CheckResult::FAIL);
});

it('ddevMutagenIgnoresNodeModules provides helpful comment when file is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    $checker = new Checker(makeCommand());
    $result = $checker->ddevMutagenIgnoresNodeModules();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $checker->getComments();
    expect($comments)->toContain('DDEV Mutagen configuration missing: Create .ddev/mutagen/mutagen.yml');
});

it('ddevMutagenIgnoresNodeModules provides helpful comment when /node_modules is missing', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/.git"
YML;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
    ]);

    $checker = new Checker(makeCommand());
    $result = $checker->ddevMutagenIgnoresNodeModules();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $checker->getComments();
    expect($comments)->toContain('DDEV Mutagen configuration incomplete: Add "/node_modules" to sync.defaults.ignore.paths in .ddev/mutagen/mutagen.yml and run "ddev mutagen reset" to apply changes');
});

it('ddevMutagenIgnoresNodeModules fails when mutagen.yml is ignored in .ddev/.gitignore', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
YML;

    $gitignore = <<<'TXT'
# DDEV-generated settings
/.ddev-docker-compose-*.yaml
/db_snapshots
/sequelpro.spf
/import.yaml
/import-db
/.bgswitch
/.dbimageBuild
/monitoring
/postgres
/traefik
/.gitignore
/.webimageBuild
/.webimageExtra
/.ddevstarttime
/mutagen/mutagen.yml
TXT;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
        '.ddev/.gitignore' => $gitignore,
    ]);

    $checker = new Checker(makeCommand());
    $result = $checker->ddevMutagenIgnoresNodeModules();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $checker->getComments();
    expect($comments)->toContain('DDEV Mutagen configuration is ignored by git: Remove "/mutagen/mutagen.yml" from .ddev/.gitignore to track the configuration');
});

it('ddevMutagenIgnoresNodeModules fails when mutagen.yml is ignored by directory pattern', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
YML;

    $gitignore = <<<'TXT'
/mutagen/
TXT;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
        '.ddev/.gitignore' => $gitignore,
    ]);

    $checker = new Checker(makeCommand());
    $result = $checker->ddevMutagenIgnoresNodeModules();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $checker->getComments();
    expect($comments)->toContain('DDEV Mutagen configuration is ignored by git: Remove "/mutagen/mutagen.yml" from .ddev/.gitignore to track the configuration');
});

it('ddevMutagenIgnoresNodeModules passes when mutagen.yml is not ignored', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
YML;

    $gitignore = <<<'TXT'
# DDEV-generated settings
/.ddev-docker-compose-*.yaml
/db_snapshots
TXT;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
        '.ddev/.gitignore' => $gitignore,
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->ddevMutagenIgnoresNodeModules())->toBe(CheckResult::PASS);
});

it('ddevMutagenIgnoresNodeModules passes when .ddev/.gitignore does not exist', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
YML;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
    ]);

    $checker = new Checker(makeCommand());
    expect($checker->ddevMutagenIgnoresNodeModules())->toBe(CheckResult::PASS);
});

it('ddevMutagenIgnoresNodeModules fails when mutagen.yml contains #ddev-generated comment', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
#ddev-generated
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
YML;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
    ]);

    $checker = new Checker(makeCommand());
    $result = $checker->ddevMutagenIgnoresNodeModules();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $checker->getComments();
    expect($comments)->toContain('DDEV Mutagen configuration is auto-generated: Remove "#ddev-generated" comment from .ddev/mutagen/mutagen.yml to prevent DDEV from overwriting your changes');
});

it('ddevMutagenIgnoresNodeModules fails when mutagen.yml contains #ddev-generated in middle of file', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    #ddev-generated
    ignore:
      paths:
        - "/node_modules"
YML;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
    ]);

    $checker = new Checker(makeCommand());
    $result = $checker->ddevMutagenIgnoresNodeModules();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $checker->getComments();
    expect($comments)->toContain('DDEV Mutagen configuration is auto-generated: Remove "#ddev-generated" comment from .ddev/mutagen/mutagen.yml to prevent DDEV from overwriting your changes');
});

it('ddevMutagenIgnoresNodeModules fails when .ddev/.gitignore contains #ddev-generated comment', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
YML;

    $gitignore = <<<'TXT'
#ddev-generated
/.ddev-docker-compose-*.yaml
/db_snapshots
TXT;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
        '.ddev/.gitignore' => $gitignore,
    ]);

    $checker = new Checker(makeCommand());
    $result = $checker->ddevMutagenIgnoresNodeModules();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $checker->getComments();
    expect($comments)->toContain('DDEV .gitignore is auto-generated: Remove "#ddev-generated" comment from .ddev/.gitignore to prevent DDEV from regenerating it');
});

it('ddevMutagenIgnoresNodeModules fails when .ddev/.gitignore contains #ddev-generated in middle', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
YML;

    $gitignore = <<<'TXT'
# DDEV-generated settings
#ddev-generated
/.ddev-docker-compose-*.yaml
/db_snapshots
TXT;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
        '.ddev/.gitignore' => $gitignore,
    ]);

    $checker = new Checker(makeCommand());
    $result = $checker->ddevMutagenIgnoresNodeModules();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $checker->getComments();
    expect($comments)->toContain('DDEV .gitignore is auto-generated: Remove "#ddev-generated" comment from .ddev/.gitignore to prevent DDEV from regenerating it');
});
