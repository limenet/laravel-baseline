<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotUsePhpInsightsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('doesNotUsePhpInsights fails when package is still installed', function (): void {
    bindFakeComposer(['nunomaduro/phpinsights' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    expect(makeCheck(DoesNotUsePhpInsightsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('doesNotUsePhpInsights passes when package absent and no leftovers remain', function (): void {
    bindFakeComposer(['nunomaduro/phpinsights' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    expect(makeCheck(DoesNotUsePhpInsightsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotUsePhpInsights fails when ci-lint still references insights', function (): void {
    bindFakeComposer(['nunomaduro/phpinsights' => false]);
    $composer = ['scripts' => ['ci-lint' => ['pint --parallel', 'insights --summary --no-interaction']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    [$check, $collector] = makeCheckWithCollector(DoesNotUsePhpInsightsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Remove leftover 'insights' entries from the ci-lint script in composer.json");
});

it('doesNotUsePhpInsights fails when config/insights.php still exists', function (): void {
    bindFakeComposer(['nunomaduro/phpinsights' => false]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'config/insights.php' => "<?php\nreturn [];\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(DoesNotUsePhpInsightsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Remove config/insights.php — PHP Insights is no longer used');
});

it('doesNotUsePhpInsights fix removes the composer entry, leftover ci-lint entries and config file', function (): void {
    // bindFakeComposer's hasPackage() is a static map, so it can't reflect the fix() writing composer.json
    // mid-run (unlike the real Illuminate\Support\Composer, which re-reads the file on every call) — assert
    // directly on the resulting composer.json instead of the final CheckResult for the package dimension.
    bindFakeComposer(['nunomaduro/phpinsights' => true]);
    $composer = [
        'require-dev' => ['nunomaduro/phpinsights' => '^2.0'],
        'scripts' => ['ci-lint' => ['pint --parallel', 'insights --summary --no-interaction']],
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'config/insights.php' => "<?php\nreturn [];\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(DoesNotUsePhpInsightsCheck::class);
    $check->fix();

    expect($collector->all())->toContain('Remove nunomaduro/phpinsights from composer.json (run `composer update` afterward to sync composer.lock)');

    $updatedComposer = json_decode(file_get_contents(base_path('composer.json')), true);
    expect($updatedComposer['require-dev'])->not->toHaveKey('nunomaduro/phpinsights');
    expect($updatedComposer['scripts']['ci-lint'])->toBe(['pint --parallel']);
    expect(file_exists(base_path('config/insights.php')))->toBeFalse();
});

it('doesNotUsePhpInsights fix returns PASS once the package is already gone', function (): void {
    bindFakeComposer(['nunomaduro/phpinsights' => false]);
    $composer = ['scripts' => ['ci-lint' => ['pint --parallel', 'insights --summary --no-interaction']]];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'config/insights.php' => "<?php\nreturn [];\n",
    ]);

    $check = makeCheck(DoesNotUsePhpInsightsCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $updatedComposer = json_decode(file_get_contents(base_path('composer.json')), true);
    expect($updatedComposer['scripts']['ci-lint'])->toBe(['pint --parallel']);
    expect(file_exists(base_path('config/insights.php')))->toBeFalse();
});
