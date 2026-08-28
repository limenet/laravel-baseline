<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotExcludeUnknownChecksCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

/**
 * Pest shares one global function namespace across the suite, hence the prefix.
 */
function excludesConfig(string $body): string
{
    return "<?php\n\nreturn {$body};\n";
}

it('doesNotExcludeUnknownChecks passes when the project has no baseline config', function (): void {
    $this->withTempBasePath([]);

    expect(makeCheck(DoesNotExcludeUnknownChecksCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotExcludeUnknownChecks passes when every exclude names a registered check', function (): void {
    $this->withTempBasePath([
        'config/baseline.php' => excludesConfig("['excludes' => ['hasEditorconfig', 'usesPest']]"),
    ]);

    expect(makeCheck(DoesNotExcludeUnknownChecksCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotExcludeUnknownChecks passes when the excludes key is absent', function (): void {
    $this->withTempBasePath([
        'config/baseline.php' => excludesConfig("['periodic' => ['updatesDependencies' => '2026-01-01T00:00:00+00:00']]"),
    ]);

    expect(makeCheck(DoesNotExcludeUnknownChecksCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotExcludeUnknownChecks fails and names the dead entry', function (): void {
    $this->withTempBasePath([
        'config/baseline.php' => excludesConfig("['excludes' => ['hasRectorConfigWithSetProviders']]"),
    ]);

    [$check, $collector] = makeCheckWithCollector(DoesNotExcludeUnknownChecksCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain(
        'Remove "hasRectorConfigWithSetProviders" from the excludes in config/baseline.php: no check by that name is registered, so the entry excludes nothing',
    );
});

it('doesNotExcludeUnknownChecks does not rewrite the config on a dry run', function (): void {
    $original = excludesConfig("['excludes' => ['hasRectorConfigWithSetProviders']]");
    $this->withTempBasePath(['config/baseline.php' => $original]);

    expect(makeCheck(DoesNotExcludeUnknownChecksCheck::class)->check())->toBe(CheckResult::FAIL);
    expect(file_get_contents(base_path('config/baseline.php')))->toBe($original);
});

it('doesNotExcludeUnknownChecks removes only the dead entries', function (): void {
    $this->withTempBasePath([
        'config/baseline.php' => excludesConfig("['excludes' => ['hasEditorconfig', 'hasRectorConfigWithSetProviders', 'usesPest']]"),
    ]);

    expect(makeCheck(DoesNotExcludeUnknownChecksCheck::class)->fix())->toBe(CheckResult::PASS);

    /** @var array<string,mixed> $config */
    $config = require base_path('config/baseline.php');

    expect($config['excludes'])->toBe(['hasEditorconfig', 'usesPest']);
});

it('doesNotExcludeUnknownChecks keeps periodic state when it rewrites the config', function (): void {
    $this->withTempBasePath([
        'config/baseline.php' => excludesConfig(
            "['excludes' => ['aCheckThatNeverExisted'], 'periodic' => ['updatesDependencies' => '2026-01-01T00:00:00+00:00']]",
        ),
    ]);

    expect(makeCheck(DoesNotExcludeUnknownChecksCheck::class)->fix())->toBe(CheckResult::PASS);

    /** @var array<string,mixed> $config */
    $config = require base_path('config/baseline.php');

    expect($config['excludes'])->toBe([]);
    expect($config['periodic'])->toBe(['updatesDependencies' => '2026-01-01T00:00:00+00:00']);
});
