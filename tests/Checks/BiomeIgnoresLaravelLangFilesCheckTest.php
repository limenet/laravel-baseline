<?php

use Limenet\LaravelBaseline\Checks\Checks\BiomeIgnoresLaravelLangFilesCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

function biomeLangIncludes(array $includes): string
{
    return json_encode(['files' => ['includes' => $includes]], JSON_UNESCAPED_SLASHES);
}

it('biomeIgnoresLaravelLangFiles follows Biome\'s last-match-wins evaluation', function (array $includes, CheckResult $expected): void {
    $this->withTempBasePath(['biome.json' => biomeLangIncludes($includes)]);

    expect(makeCheck(BiomeIgnoresLaravelLangFilesCheck::class)->check())->toBe($expected);
})->with([
    'everything' => [['**'], CheckResult::FAIL],
    'every json file' => [['**/*.json'], CheckResult::FAIL],
    'only one location excluded' => [['**', '!lang/*.json'], CheckResult::FAIL],
    'both excluded' => [['**', '!lang/*.json', '!resources/lang/*.json'], CheckResult::PASS],
    'excluded anywhere' => [['**', '!**/lang/*.json'], CheckResult::PASS],
    'directories excluded' => [['**', '!lang', '!resources/lang'], CheckResult::PASS],
    'directory contents excluded' => [['**', '!lang/**', '!resources/lang/**'], CheckResult::PASS],
    'taken back by a later pattern' => [['**', '!lang/*.json', '!resources/lang/*.json', '**/*.json'], CheckResult::FAIL],
    'allowlist' => [['app/**', 'resources/js/**'], CheckResult::PASS],
    'empty' => [[], CheckResult::PASS],
]);

it('biomeIgnoresLaravelLangFiles fails when an existing locale is not excluded', function (): void {
    $this->withTempBasePath([
        'biome.json' => biomeLangIncludes(['**', '!lang/en.json', '!resources/lang/*.json']),
        'lang/en.json' => '{}',
        'lang/de.json' => '{}',
    ]);

    [$check, $collector] = makeCheckWithCollector(BiomeIgnoresLaravelLangFilesCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $collector->all()))->toContain('add "!lang/*.json" at the end of files.includes');
});

it('biomeIgnoresLaravelLangFiles appends only the missing exclusions', function (): void {
    $this->withTempBasePath(['biome.json' => biomeLangIncludes(['**', '!resources/lang/*.json'])]);

    expect(makeCheck(BiomeIgnoresLaravelLangFilesCheck::class)->fix())->toBe(CheckResult::PASS);
    expect(json_decode((string) file_get_contents(base_path('biome.json')), true)['files']['includes'])
        ->toBe(['**', '!resources/lang/*.json', '!lang/*.json']);
});

it('biomeIgnoresLaravelLangFiles reports a config without files.includes', function (): void {
    $this->withTempBasePath(['biome.json' => '{}']);

    [$check, $collector] = makeCheckWithCollector(BiomeIgnoresLaravelLangFilesCheck::class);

    expect($check->fix())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Biome checks Laravel lang files (lang/*.json, resources/lang/*.json) because biome.json has no files.includes: add "files": { "includes": ["**", "!lang/*.json", "!resources/lang/*.json"] }');
    expect(file_get_contents(base_path('biome.json')))->toBe('{}');
});

it('biomeIgnoresLaravelLangFiles passes without biome.json', function (): void {
    $this->withTempBasePath([]);

    expect(makeCheck(BiomeIgnoresLaravelLangFilesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('biomeIgnoresLaravelLangFiles only applies to Laravel projects', function (): void {
    expect(BiomeIgnoresLaravelLangFilesCheck::profiles())->toBe([Profile::Laravel]);
});
