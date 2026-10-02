<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesPestCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

it('usesPest passes when pest packages are present and no disallowed packages', function (): void {
    bindFakeComposer([
        'pestphp/pest' => true,
        'pestphp/pest-plugin-laravel' => true,
        'pestphp/pest-plugin-drift' => false,
        'spatie/phpunit-watcher' => false,
    ]);

    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesPestCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('usesPest fails when both drift plugin and phpunit-watcher are present', function (): void {
    bindFakeComposer([
        'pestphp/pest' => true,
        'pestphp/pest-plugin-laravel' => true,
        'pestphp/pest-plugin-drift' => true, // disallowed
        'spatie/phpunit-watcher' => true,    // disallowed
    ]);

    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesPestCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesPest fails when either drift or phpunit-watcher is present', function (string $forbidden): void {
    bindFakeComposer([
        'pestphp/pest' => true,
        'pestphp/pest-plugin-laravel' => true,
        $forbidden => true,
    ]);

    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesPestCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toContain("Remove {$forbidden} from composer.json: it is not used alongside Pest");
})->with(['pestphp/pest-plugin-drift', 'spatie/phpunit-watcher']);

it('usesPest warns outside Laravel when there is no test suite', function (Profile $profile): void {
    $project = makeProject($profile, ['composer.json' => json_encode(['require-dev' => []])]);

    expect(makeCheck(UsesPestCheck::class, $project)->check())->toBe(CheckResult::WARN);
})->with([Profile::Php, Profile::WordPress]);

it('usesPest passes outside Laravel on plain Pest without the Laravel plugin', function (): void {
    $project = makeProject(Profile::WordPress, ['composer.json' => json_encode(['require-dev' => ['pestphp/pest' => '^4.0']])]);

    expect(makeCheck(UsesPestCheck::class, $project)->check())->toBe(CheckResult::PASS);
});

it('usesPest still rejects the drift plugin outside Laravel', function (): void {
    $project = makeProject(Profile::Php, ['composer.json' => json_encode(['require-dev' => [
        'pestphp/pest' => '^4.0',
        'pestphp/pest-plugin-drift' => '^4.0',
    ]])]);

    expect(makeCheck(UsesPestCheck::class, $project)->check())->toBe(CheckResult::FAIL);
});
