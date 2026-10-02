<?php

use Limenet\LaravelBaseline\Checks\Checks\BiomeIgnoresCiArtifactsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

function biomeWithIncludes(array $includes): string
{
    return json_encode(['files' => ['includes' => $includes]], JSON_UNESCAPED_SLASHES);
}

it('biomeIgnoresCiArtifacts follows Biome\'s last-match-wins evaluation', function (array $includes, CheckResult $expected): void {
    $this->withTempBasePath(['biome.json' => biomeWithIncludes($includes)]);

    expect(makeCheck(BiomeIgnoresCiArtifactsCheck::class)->check())->toBe($expected);
})->with([
    'everything' => [['**'], CheckResult::FAIL],
    'every json file' => [['**/*.json'], CheckResult::FAIL],
    'root json files' => [['*.json'], CheckResult::FAIL],
    'excluded' => [['**', '!metadata.json'], CheckResult::PASS],
    'excluded anywhere' => [['**', '!**/metadata.json'], CheckResult::PASS],
    'excluded with ./' => [['**', '!./metadata.json'], CheckResult::PASS],
    'force-ignored' => [['**', '!!metadata.json'], CheckResult::PASS],
    'taken back by a later pattern' => [['**', '!metadata.json', '*.json'], CheckResult::FAIL],
    'allowlist' => [['resources/**', 'src/*.ts'], CheckResult::PASS],
    'nested json only' => [['config/*.json'], CheckResult::PASS],
    'empty' => [[], CheckResult::PASS],
]);

it('biomeIgnoresCiArtifacts tells which entry to add', function (): void {
    $this->withTempBasePath(['biome.json' => biomeWithIncludes(['**'])]);

    [$check, $collector] = makeCheckWithCollector(BiomeIgnoresCiArtifactsCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $collector->all()))->toContain('add "!metadata.json" at the end of files.includes');
});

it('biomeIgnoresCiArtifacts fails on an unparsable biome.json without writing to it', function (): void {
    $this->withTempBasePath(['biome.json' => '{ "files": ']);

    [$check, $collector] = makeCheckWithCollector(BiomeIgnoresCiArtifactsCheck::class);

    expect($check->fix())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('biome.json is not valid JSON');
    expect(file_get_contents(base_path('biome.json')))->toBe('{ "files": ');
});

it('biomeIgnoresCiArtifacts fails when files.includes is not a list', function (): void {
    $this->withTempBasePath(['biome.json' => '{ "files": { "includes": "**" } }']);

    [$check, $collector] = makeCheckWithCollector(BiomeIgnoresCiArtifactsCheck::class);

    expect($check->fix())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Invalid files.includes in biome.json: must be a list of glob patterns');
});

it('biomeIgnoresCiArtifacts appends to files.includes, not to an override\'s includes', function (): void {
    $before = <<<'JSON'
    {
      "overrides": [{ "includes": ["**/*.css"] }],
      "files": {
        "includes": [
          "**",
          "!composer.json" // keep the comment
        ]
      }
    }

    JSON;

    $this->withTempBasePath(['biome.json' => $before]);

    expect(makeCheck(BiomeIgnoresCiArtifactsCheck::class)->fix())->toBe(CheckResult::PASS);
    expect(file_get_contents(base_path('biome.json')))->toBe(str_replace(
        '"!composer.json" // keep the comment',
        "\"!composer.json\", // keep the comment\n      \"!metadata.json\"",
        $before,
    ));
});

it('biomeIgnoresCiArtifacts runs in standalone projects', function (Profile $profile): void {
    $project = makeProject($profile, ['biome.json' => biomeWithIncludes(['**'])]);

    expect(makeCheck(BiomeIgnoresCiArtifactsCheck::class, $project)->fix())->toBe(CheckResult::PASS);
    expect(json_decode((string) file_get_contents($project->path('biome.json')), true)['files']['includes'])
        ->toBe(['**', '!metadata.json']);
})->with([Profile::Php, Profile::WordPress]);
