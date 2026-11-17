<?php

use Limenet\LaravelBaseline\Checks\Checks\BiomeIgnoresClaudeSkillsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

it('biomeIgnoresClaudeSkills follows Biome\'s last-match-wins evaluation', function (array $includes, CheckResult $expected): void {
    $this->withTempBasePath(['biome.json' => biomeWithIncludes($includes)]);

    expect(makeCheck(BiomeIgnoresClaudeSkillsCheck::class)->check())->toBe($expected);
})->with([
    'everything' => [['**'], CheckResult::FAIL],
    'every markdown file' => [['**/*.md'], CheckResult::FAIL],
    'excluded' => [['**', '!.claude/skills'], CheckResult::PASS],
    'excluded with ./' => [['**', '!./.claude/skills'], CheckResult::PASS],
    'excluded with its contents' => [['**', '!.claude/skills/**'], CheckResult::PASS],
    'markdown excluded everywhere' => [['**', '!**/*.md'], CheckResult::PASS],
    'parent excluded' => [['**', '!.claude'], CheckResult::PASS],
    'force-ignored' => [['**', '!!.claude/skills'], CheckResult::PASS],
    'taken back by a later pattern' => [['**', '!.claude', '.claude/**'], CheckResult::FAIL],
    'allowlist' => [['resources/**', 'src/*.ts'], CheckResult::PASS],
    'empty' => [[], CheckResult::PASS],
]);

it('biomeIgnoresClaudeSkills tells which entry to add and why', function (): void {
    $this->withTempBasePath(['biome.json' => biomeWithIncludes(['**'])]);

    [$check, $collector] = makeCheckWithCollector(BiomeIgnoresClaudeSkillsCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $collector->all()))
        ->toContain('Biome checks installed Claude skills (.claude/skills)')
        ->toContain('Biome 2.6+ formats Markdown')
        ->toContain('add "!.claude/skills" at the end of files.includes');
});

it('biomeIgnoresClaudeSkills runs in standalone projects, and its fix is idempotent', function (Profile $profile): void {
    $project = makeProject($profile, ['biome.json' => biomeWithIncludes(['**'])]);

    expect(makeCheck(BiomeIgnoresClaudeSkillsCheck::class, $project)->fix())->toBe(CheckResult::PASS);
    expect(makeCheck(BiomeIgnoresClaudeSkillsCheck::class, $project)->fix())->toBe(CheckResult::PASS);
    expect(json_decode((string) file_get_contents($project->path('biome.json')), true)['files']['includes'])
        ->toBe(['**', '!.claude/skills']);
})->with([Profile::Php, Profile::WordPress]);
