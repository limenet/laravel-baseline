<?php

use Illuminate\Console\OutputStyle;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer as IlluminateComposer;
use Illuminate\Support\Facades\Schedule;
use Limenet\LaravelBaseline\Checks\Checker;
use Limenet\LaravelBaseline\Commands\LaravelBaselineCommand;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Helper: create a LaravelBaselineCommand with initialized Output and container.
 */
function makeCommand(): LaravelBaselineCommand
{
    /** @var \Illuminate\Contracts\Foundation\Application $app */
    $app = app();

    $command = new LaravelBaselineCommand();
    $command->setLaravel($app);
    $output = new OutputStyle(new ArrayInput([]), new BufferedOutput());
    $command->setOutput($output);

    return $command;
}

/**
 * Helper: bind a fake Composer instance with predefined package availability map.
 *
 * @param  array<string,bool>  $map
 */
function bindFakeComposer(array $map): void
{
    $app = app();

    $fake = new class(new Filesystem(), $map) extends IlluminateComposer
    {
        /** @var array<string,bool> */
        private array $map;

        public function __construct(Filesystem $files, array $map)
        {
            parent::__construct($files);
            $this->map = $map;
        }

        public function setWorkingPath($path)
        {
            return $this;
        }

        public function hasPackage($package)
        {
            return $this->map[$package] ?? false;
        }
    };

    $app->bind(IlluminateComposer::class, fn () => $fake);
}

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

it('usesLaravelHorizon returns WARN if package missing, FAIL if missing post-deploy, PASS when all ok', function (): void {
    // WARN
    bindFakeComposer(['laravel/horizon' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelHorizon())->toBe(CheckResult::WARN);

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

it('usesLaravelPennant checks for pennant:purge in post-deploy scripts', function (): void {
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
    // WARN when not installed
    bindFakeComposer(['laravel/pulse' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelPulse())->toBe(CheckResult::WARN);

    // FAIL when installed but not scheduled
    bindFakeComposer(['laravel/pulse' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    // no schedule
    $checker = new Checker(makeCommand());
    expect($checker->usesLaravelPulse())->toBe(CheckResult::FAIL);

    // PASS when scheduled
    bindFakeComposer(['laravel/pulse' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

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

    // With script and schedule -> PASS
    bindFakeComposer(['laravel/telescope' => true]);
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

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
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan limenet:laravel-baseline']]];

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

it('usesPredis warns when not installed and passes when installed', function (): void {
    bindFakeComposer(['predis/predis' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesPredis())->toBe(CheckResult::WARN);

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

it('usesRector warns unless both rector packages installed', function (): void {
    bindFakeComposer(['rector/rector' => true, 'driftingly/rector-laravel' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesRector())->toBe(CheckResult::WARN);

    bindFakeComposer(['rector/rector' => true, 'driftingly/rector-laravel' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

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

    bindFakeComposer(['phpstan/phpstan-deprecation-rules' => true, 'phpstan/phpstan-strict-rules' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesPhpstanExtensions())->toBe(CheckResult::PASS);
});

it('usesPhpInsights passes only when phpinsights is installed', function (): void {
    bindFakeComposer(['nunomaduro/phpinsights' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesPhpInsights())->toBe(CheckResult::FAIL);

    bindFakeComposer(['nunomaduro/phpinsights' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

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
            'rector',
            'insights --summary --no-interaction',
            'insights -n --ansi --format=codeclimate > codeclimate-report.json 2>/dev/null',
        ],
    ];
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => $scriptsOk])]);

    expect((new Checker(makeCommand()))->isCiLintComplete())->toBe(CheckResult::PASS);

    $scriptsBad = ['ci-lint' => ['pint --parallel', 'phpstan']];
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => $scriptsBad])]);

    expect((new Checker(makeCommand()))->isCiLintComplete())->toBe(CheckResult::FAIL);
});

it('checkPhpunit passes when cobertura, junit and APP_KEY are configured', function (): void {
    bindFakeComposer([]);
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <coverage>
    <report>
      <cobertura outputFile="cobertura.xml" />
    </report>
  </coverage>
  <logging>
    <junit outputFile="report.xml" />
  </logging>
  <php>
    <env name="APP_KEY" value="base64:xxx" />
  </php>
</phpunit>
XML;

    $this->withTempBasePath(['phpunit.xml' => $xml, 'composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->checkPhpunit())->toBe(CheckResult::PASS);
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

it('usesLaravelBoost warns when not installed and passes when installed', function (): void {
    bindFakeComposer(['laravel/boost' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesLaravelBoost())->toBe(CheckResult::WARN);

    bindFakeComposer(['laravel/boost' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->usesLaravelBoost())->toBe(CheckResult::PASS);
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
            strictBooleans: true
        )
        ->withPhpSets();

    $config->withSetProviders(LaravelSetProvider::class);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    expect((new Checker(makeCommand()))->hasCompleteRectorConfiguration())->toBe(CheckResult::PASS);
});
