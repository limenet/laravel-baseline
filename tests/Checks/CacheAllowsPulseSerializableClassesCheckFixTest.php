<?php

use Limenet\LaravelBaseline\Checks\Checks\CacheAllowsPulseSerializableClassesCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('cacheAllowsPulseSerializableClasses is fixable', function (): void {
    expect(makeCheck(CacheAllowsPulseSerializableClassesCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('cacheAllowsPulseSerializableClasses fix does nothing in dry mode', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    $config = pulseCacheConfig('false');
    pulseCacheBasePath($this, ['config/cache.php' => $config]);

    expect(makeCheck(CacheAllowsPulseSerializableClassesCheck::class)->fix(dry: true))->toBe(CheckResult::FAIL);
    expect(file_get_contents(base_path('config/cache.php')))->toBe($config);
});

it('cacheAllowsPulseSerializableClasses fix replaces false with the required classes', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    pulseCacheBasePath($this, ['config/cache.php' => pulseCacheConfig('false')]);

    expect(makeCheck(CacheAllowsPulseSerializableClassesCheck::class)->fix())->toBe(CheckResult::PASS);

    $contents = file_get_contents(base_path('config/cache.php'));

    expect($contents)
        ->toContain('\stdClass::class')
        ->toContain('\Illuminate\Support\Collection::class')
        ->toContain('\Carbon\CarbonImmutable::class')
        ->not->toContain("'serializable_classes' => false");

    // The rest of the file, including Laravel's doc-comment banners, survives.
    expect($contents)
        ->toContain('| Default Cache Store')
        ->toContain("'default' => env('CACHE_STORE', 'database')")
        ->toContain("'driver' => 'array'");

    // The replaced array is laid out one entry per line, not collapsed.
    expect($contents)->toContain("\n        \\stdClass::class,");
});

it('cacheAllowsPulseSerializableClasses fix appends only the missing classes', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    pulseCacheBasePath($this, [
        'config/cache.php' => pulseCacheConfig("[\n        \\App\\Dto\\Foo::class,\n    ]"),
    ]);

    expect(makeCheck(CacheAllowsPulseSerializableClassesCheck::class)->fix())->toBe(CheckResult::PASS);

    $contents = file_get_contents(base_path('config/cache.php'));

    expect($contents)
        ->toContain('\App\Dto\Foo::class')
        ->toContain('\stdClass::class')
        ->toContain('\Illuminate\Support\Collection::class')
        ->toContain('\Carbon\CarbonImmutable::class');
});

it('cacheAllowsPulseSerializableClasses fix is idempotent', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    pulseCacheBasePath($this, ['config/cache.php' => pulseCacheConfig('false')]);

    $check = makeCheck(CacheAllowsPulseSerializableClassesCheck::class);

    expect($check->fix())->toBe(CheckResult::PASS);
    $first = file_get_contents(base_path('config/cache.php'));

    expect($check->fix())->toBe(CheckResult::PASS);
    expect(file_get_contents(base_path('config/cache.php')))->toBe($first);
    expect(substr_count((string) $first, 'stdClass'))->toBe(1);
});

it('cacheAllowsPulseSerializableClasses fix leaves an unrestricted config alone', function (string $value): void {
    bindFakeComposer(['laravel/pulse' => true]);
    $config = pulseCacheConfig($value);
    pulseCacheBasePath($this, ['config/cache.php' => $config]);

    expect(makeCheck(CacheAllowsPulseSerializableClassesCheck::class)->fix())->toBe(CheckResult::PASS);
    expect(file_get_contents(base_path('config/cache.php')))->toBe($config);
})->with(['true', 'null']);

it('cacheAllowsPulseSerializableClasses fix leaves a non-literal value alone', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    $config = pulseCacheConfig("env('CACHE_SERIALIZABLE_CLASSES')");
    pulseCacheBasePath($this, ['config/cache.php' => $config]);

    expect(makeCheck(CacheAllowsPulseSerializableClassesCheck::class)->fix())->toBe(CheckResult::FAIL);
    expect(file_get_contents(base_path('config/cache.php')))->toBe($config);
});
