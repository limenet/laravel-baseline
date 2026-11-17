<?php

use Limenet\LaravelBaseline\Checks\Checks\BiomeIgnoresDdevCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

it('biomeIgnoresDdev follows Biome\'s last-match-wins evaluation', function (array $includes, CheckResult $expected): void {
    $this->withTempBasePath(['biome.json' => biomeWithIncludes($includes)]);

    expect(makeCheck(BiomeIgnoresDdevCheck::class)->check())->toBe($expected);
})->with([
    'everything' => [['**'], CheckResult::FAIL],
    'every yaml file' => [['**/*.yaml'], CheckResult::FAIL],
    'excluded' => [['**', '!.ddev'], CheckResult::PASS],
    'excluded anywhere' => [['**', '!**/.ddev'], CheckResult::PASS],
    'excluded with its contents' => [['**', '!.ddev/**'], CheckResult::PASS],
    'taken back by a later pattern' => [['**', '!.ddev', '.ddev/**/*.yaml'], CheckResult::FAIL],
    'allowlist' => [['resources/**', 'src/*.ts'], CheckResult::PASS],
    'empty' => [[], CheckResult::PASS],
]);

it('biomeIgnoresDdev tells which entry to add and why', function (): void {
    $this->withTempBasePath(['biome.json' => biomeWithIncludes(['**'])]);

    [$check, $collector] = makeCheckWithCollector(BiomeIgnoresDdevCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $collector->all()))
        ->toContain('Biome checks DDEV-managed files (.ddev)')
        ->toContain('Biome 2.6+ formats YAML')
        ->toContain('add "!.ddev" at the end of files.includes');
});

it('biomeIgnoresDdev runs in standalone projects, and its fix is idempotent', function (Profile $profile): void {
    $project = makeProject($profile, ['biome.json' => biomeWithIncludes(['**'])]);

    expect(makeCheck(BiomeIgnoresDdevCheck::class, $project)->fix())->toBe(CheckResult::PASS);
    expect(makeCheck(BiomeIgnoresDdevCheck::class, $project)->fix())->toBe(CheckResult::PASS);
    expect(json_decode((string) file_get_contents($project->path('biome.json')), true)['files']['includes'])
        ->toBe(['**', '!.ddev']);
})->with([Profile::Php, Profile::WordPress]);
