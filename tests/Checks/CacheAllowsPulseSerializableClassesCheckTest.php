<?php

use Limenet\LaravelBaseline\Checks\Checks\CacheAllowsPulseSerializableClassesCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('cacheAllowsPulseSerializableClasses warns when laravel/pulse is not installed', function (): void {
    bindFakeComposer(['laravel/pulse' => false]);
    pulseCacheBasePath($this, ['config/cache.php' => pulseCacheConfig('false')]);

    expect(makeCheck(CacheAllowsPulseSerializableClassesCheck::class)->check())->toBe(CheckResult::WARN);
});

it('cacheAllowsPulseSerializableClasses warns below Laravel 13', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    pulseCacheBasePath($this, ['config/cache.php' => pulseCacheConfig('false')], laravel: '^12.0');

    expect(makeCheck(CacheAllowsPulseSerializableClassesCheck::class)->check())->toBe(CheckResult::WARN);
});

it('cacheAllowsPulseSerializableClasses passes when config/cache.php is not published', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    pulseCacheBasePath($this, []);

    expect(makeCheck(CacheAllowsPulseSerializableClassesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('cacheAllowsPulseSerializableClasses passes when the key is absent', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    pulseCacheBasePath($this, [
        'config/cache.php' => "<?php\n\nreturn ['default' => 'redis'];\n",
    ]);

    expect(makeCheck(CacheAllowsPulseSerializableClassesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('cacheAllowsPulseSerializableClasses passes when the value is null or true', function (string $value): void {
    bindFakeComposer(['laravel/pulse' => true]);
    pulseCacheBasePath($this, ['config/cache.php' => pulseCacheConfig($value)]);

    expect(makeCheck(CacheAllowsPulseSerializableClassesCheck::class)->check())->toBe(CheckResult::PASS);
})->with(['null', 'true']);

it('cacheAllowsPulseSerializableClasses fails on the Laravel 13 default of false', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    pulseCacheBasePath($this, ['config/cache.php' => pulseCacheConfig('false')]);

    [$check, $collector] = makeCheckWithCollector(CacheAllowsPulseSerializableClassesCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $collector->all()))
        ->toContain('\stdClass::class')
        ->toContain('\Illuminate\Support\Collection::class')
        ->toContain('\Carbon\CarbonImmutable::class')
        ->toContain('config/cache.php');
});

it('cacheAllowsPulseSerializableClasses reports only the missing classes', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    pulseCacheBasePath($this, [
        'config/cache.php' => pulseCacheConfig('[\stdClass::class]'),
    ]);

    [$check, $collector] = makeCheckWithCollector(CacheAllowsPulseSerializableClassesCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $collector->all()))
        ->toContain('\Illuminate\Support\Collection::class')
        ->toContain('\Carbon\CarbonImmutable::class')
        ->not->toContain('\stdClass::class');
});

it('cacheAllowsPulseSerializableClasses passes for fully-qualified class constants', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    pulseCacheBasePath($this, [
        'config/cache.php' => pulseCacheConfig(
            '[\stdClass::class, \Illuminate\Support\Collection::class, \Carbon\CarbonImmutable::class]',
        ),
    ]);

    expect(makeCheck(CacheAllowsPulseSerializableClassesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('cacheAllowsPulseSerializableClasses resolves imported short names', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    pulseCacheBasePath($this, [
        'config/cache.php' => pulseCacheConfig(
            '[stdClass::class, Collection::class, CarbonImmutable::class]',
            uses: "use Carbon\\CarbonImmutable;\nuse Illuminate\\Support\\Collection;\n",
        ),
    ]);

    expect(makeCheck(CacheAllowsPulseSerializableClassesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('cacheAllowsPulseSerializableClasses passes for plain string entries', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    pulseCacheBasePath($this, [
        'config/cache.php' => pulseCacheConfig(
            "['stdClass', 'Illuminate\\Support\\Collection', '\\Carbon\\CarbonImmutable']",
        ),
    ]);

    expect(makeCheck(CacheAllowsPulseSerializableClassesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('cacheAllowsPulseSerializableClasses passes for a superset', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    pulseCacheBasePath($this, [
        'config/cache.php' => pulseCacheConfig(
            '[\App\Dto\Foo::class, \stdClass::class, \Illuminate\Support\Collection::class, \Carbon\CarbonImmutable::class]',
        ),
    ]);

    expect(makeCheck(CacheAllowsPulseSerializableClassesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('cacheAllowsPulseSerializableClasses fails on a non-literal value', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    pulseCacheBasePath($this, [
        'config/cache.php' => pulseCacheConfig("env('CACHE_SERIALIZABLE_CLASSES')"),
    ]);

    [$check, $collector] = makeCheckWithCollector(CacheAllowsPulseSerializableClassesCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $collector->all()))->toContain('is not a literal array');
});

it('cacheAllowsPulseSerializableClasses fails when config/cache.php cannot be parsed', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    pulseCacheBasePath($this, ['config/cache.php' => "<?php\n\nreturn [\n"]);

    [$check, $collector] = makeCheckWithCollector(CacheAllowsPulseSerializableClassesCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $collector->all()))->toContain('could not be parsed');
});

it('cacheAllowsPulseSerializableClasses fails when the config is not an array literal', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    pulseCacheBasePath($this, [
        'config/cache.php' => "<?php\n\nreturn array_merge([], []);\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(CacheAllowsPulseSerializableClassesCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $collector->all()))->toContain('does not return an array literal');
});

it('cacheAllowsPulseSerializableClasses uses the last of duplicate keys', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    $config = <<<'PHP'
    <?php

    return [
        'serializable_classes' => false,
        'serializable_classes' => [\stdClass::class, \Illuminate\Support\Collection::class, \Carbon\CarbonImmutable::class],
    ];
    PHP;
    pulseCacheBasePath($this, ['config/cache.php' => $config]);

    expect(makeCheck(CacheAllowsPulseSerializableClassesCheck::class)->check())->toBe(CheckResult::PASS);
});
