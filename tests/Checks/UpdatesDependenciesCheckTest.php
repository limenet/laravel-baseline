<?php

use Limenet\LaravelBaseline\Checks\Checks\UpdatesDependenciesCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

it('updatesDependencies is always applicable', function (): void {
    expect(makeCheck(UpdatesDependenciesCheck::class)->isApplicable())->toBeTrue();
});

it('updatesDependencies fails when no state exists', function (): void {
    $this->withTempBasePath([]);

    expect(makeCheck(UpdatesDependenciesCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('updatesDependencies fails when state is older than 30 days', function (): void {
    $old = now()->subDays(31)->toAtomString();
    $this->withTempBasePath([
        'config/baseline.php' => "<?php\n\nreturn ['excludes' => [], 'periodic' => ['updatesDependencies' => '{$old}']];\n",
    ]);

    expect(makeCheck(UpdatesDependenciesCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('updatesDependencies passes when state is within 30 days', function (): void {
    $recent = now()->subDays(1)->toAtomString();
    $this->withTempBasePath([
        'config/baseline.php' => "<?php\n\nreturn ['excludes' => [], 'periodic' => ['updatesDependencies' => '{$recent}']];\n",
    ]);

    expect(makeCheck(UpdatesDependenciesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('updatesDependencies provides helpful comment when expired', function (): void {
    $this->withTempBasePath([]);

    [$check, $collector] = makeCheckWithCollector(UpdatesDependenciesCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Run `ddev artisan limenet:laravel-baseline:periodic` to complete this periodic check');
});

it('updatesDependencies points a standalone project at vendor/bin/baseline periodic', function (): void {
    [$check, $collector] = makeCheckWithCollector(UpdatesDependenciesCheck::class, makeProject(Profile::WordPress));

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toContain('Run `ddev exec vendor/bin/baseline periodic` to complete this periodic check');
});

it('updatesDependencies reads the last run from .baseline.json', function (): void {
    $project = makeProject(Profile::Php, [
        '.baseline.json' => json_encode(['periodic' => ['updatesDependencies' => (new DateTimeImmutable)->format(DATE_ATOM)]]),
    ]);

    expect(makeCheck(UpdatesDependenciesCheck::class, $project)->check())->toBe(CheckResult::PASS);
});
