<?php

use Limenet\LaravelBaseline\Checks\Checks\BiomeIgnoresClaudeMdCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

it('biomeIgnoresClaudeMd follows Biome\'s last-match-wins evaluation', function (array $includes, CheckResult $expected): void {
    $this->withTempBasePath(['biome.json' => biomeWithIncludes($includes)]);

    expect(makeCheck(BiomeIgnoresClaudeMdCheck::class)->check())->toBe($expected);
})->with([
    'everything' => [['**'], CheckResult::FAIL],
    'every markdown file' => [['**/*.md'], CheckResult::FAIL],
    'excluded' => [['**', '!CLAUDE.md'], CheckResult::PASS],
    'excluded anywhere' => [['**', '!**/CLAUDE.md'], CheckResult::PASS],
    'markdown excluded everywhere' => [['**', '!**/*.md'], CheckResult::PASS],
    'taken back by a later pattern' => [['**', '!**/*.md', '*.md'], CheckResult::FAIL],
    'allowlist' => [['resources/**', 'src/*.ts'], CheckResult::PASS],
    'empty' => [[], CheckResult::PASS],
]);

it('biomeIgnoresClaudeMd tells which entry to add and why', function (): void {
    $this->withTempBasePath(['biome.json' => biomeWithIncludes(['**'])]);

    [$check, $collector] = makeCheckWithCollector(BiomeIgnoresClaudeMdCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $collector->all()))
        ->toContain('Biome checks CLAUDE.md (CLAUDE.md)')
        ->toContain('Biome 2.6+ reformats')
        ->toContain('add "!CLAUDE.md" at the end of files.includes');
});

it('biomeIgnoresClaudeMd runs in standalone projects, and its fix is idempotent', function (Profile $profile): void {
    $project = makeProject($profile, ['biome.json' => biomeWithIncludes(['**'])]);

    expect(makeCheck(BiomeIgnoresClaudeMdCheck::class, $project)->fix())->toBe(CheckResult::PASS);
    expect(makeCheck(BiomeIgnoresClaudeMdCheck::class, $project)->fix())->toBe(CheckResult::PASS);
    expect(json_decode((string) file_get_contents($project->path('biome.json')), true)['files']['includes'])
        ->toBe(['**', '!CLAUDE.md']);
})->with([Profile::Php, Profile::WordPress]);
