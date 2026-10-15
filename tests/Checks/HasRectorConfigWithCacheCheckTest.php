<?php

use Limenet\LaravelBaseline\Checks\Checks\HasRectorConfigWithCacheCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

function rectorWith(string $chain): string
{
    return "<?php\n\nuse Rector\\Config\\RectorConfig;\n\nreturn RectorConfig::configure()\n    ->withPhpSets(){$chain};\n";
}

it('hasRectorConfigWithCache fails when rector.php is missing', function (): void {
    $this->withTempBasePath(['.gitignore' => "/.cache/rector/\n"]);

    expect(makeCheck(HasRectorConfigWithCacheCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('hasRectorConfigWithCache fails when withCache is absent', function (): void {
    $this->withTempBasePath(['rector.php' => rectorWith(''), '.gitignore' => "/.cache/rector/\n"]);

    [$check, $collector] = makeCheckWithCollector(HasRectorConfigWithCacheCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $collector->all()))->toContain("cacheDirectory: __DIR__.'/.cache/rector'");
});

it('hasRectorConfigWithCache passes with the cache directory', function (string $argument): void {
    $this->withTempBasePath(['rector.php' => rectorWith("\n    ->withCache({$argument})"), '.gitignore' => "/.cache/rector/\n"]);

    expect(makeCheck(HasRectorConfigWithCacheCheck::class)->check())->toBe(CheckResult::PASS);
})->with([
    'named, __DIR__' => ["cacheDirectory: __DIR__.'/.cache/rector'"],
    'positional, __DIR__' => ["__DIR__ . '/.cache/rector/'"],
    'relative string' => ["cacheDirectory: './.cache/rector'"],
    'with cacheClass' => ["cacheDirectory: __DIR__.'/.cache/rector', cacheClass: FileCacheStorage::class"],
]);

it('hasRectorConfigWithCache fails when the cache lives elsewhere', function (): void {
    $this->withTempBasePath(['rector.php' => rectorWith("\n    ->withCache(cacheDirectory: '/tmp/rector')"), '.gitignore' => "/.cache/rector/\n"]);

    expect(makeCheck(HasRectorConfigWithCacheCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('hasRectorConfigWithCache fails when .gitignore does not ignore the cache', function (): void {
    $this->withTempBasePath(['rector.php' => rectorWith("\n    ->withCache(cacheDirectory: __DIR__.'/.cache/rector')"), '.gitignore' => "/vendor\n"]);

    [$check, $collector] = makeCheckWithCollector(HasRectorConfigWithCacheCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $collector->all()))->toContain("Missing entry in .gitignore: add '/.cache/rector/'");
});

it('hasRectorConfigWithCache fix appends the call and the .gitignore entry', function (): void {
    $this->withTempBasePath(['rector.php' => rectorWith(''), '.gitignore' => "/vendor\n"]);

    expect(makeCheck(HasRectorConfigWithCacheCheck::class)->fix())->toBe(CheckResult::PASS);

    expect((string) file_get_contents(base_path('rector.php')))->toContain("->withCache(cacheDirectory: __DIR__ . '/.cache/rector')");
    expect((string) file_get_contents(base_path('.gitignore')))->toBe("/vendor\n/.cache/rector/\n");
});

it('hasRectorConfigWithCache fix repoints an existing call, keeping its other arguments', function (string $before, string $after): void {
    $this->withTempBasePath(['rector.php' => rectorWith("\n    ->withCache({$before})"), '.gitignore' => "/.cache/rector/\n"]);

    expect(makeCheck(HasRectorConfigWithCacheCheck::class)->fix())->toBe(CheckResult::PASS);

    expect((string) file_get_contents(base_path('rector.php')))->toContain("->withCache({$after})");
})->with([
    'named' => ["cacheDirectory: '/tmp/rector', cacheClass: FileCacheStorage::class", "cacheDirectory: __DIR__ . '/.cache/rector', cacheClass: FileCacheStorage::class"],
    'positional' => ["'/tmp/rector'", "__DIR__ . '/.cache/rector'"],
    'no directory' => ['cacheClass: FileCacheStorage::class', "cacheDirectory: __DIR__ . '/.cache/rector', cacheClass: FileCacheStorage::class"],
]);

it('hasRectorConfigWithCache fix creates rector.php when there is none', function (): void {
    $this->withTempBasePath(['.gitignore' => "/.cache/rector/\n"]);

    expect(makeCheck(HasRectorConfigWithCacheCheck::class)->fix())->toBe(CheckResult::PASS);
    expect((string) file_get_contents(base_path('rector.php')))->toContain('withCache(');
});

it('hasRectorConfigWithCache runs in standalone projects', function (Profile $profile): void {
    $project = makeProject($profile, ['rector.php' => rectorWith(''), '.gitignore' => "/vendor\n"]);

    expect(makeCheck(HasRectorConfigWithCacheCheck::class, $project)->check())->toBe(CheckResult::FAIL);
    expect(makeCheck(HasRectorConfigWithCacheCheck::class, $project)->fix())->toBe(CheckResult::PASS);
})->with([Profile::Php, Profile::WordPress]);
