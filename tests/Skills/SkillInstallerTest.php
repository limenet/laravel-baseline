<?php

use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Skills\SkillInstaller;

it('ships the standalone skills', function (): void {
    expect(array_keys(SkillInstaller::packaged()))
        ->toBe(['creating-a-release', 'ignoring-trivy-findings', 'updating-dependencies']);
});

it('ships no Laravel or artisan instructions in the standalone skills', function (): void {
    // These are installed into projects without Laravel, where artisan and
    // config/baseline.php do not exist.
    foreach (SkillInstaller::packaged() as $name => $skill) {
        expect($skill['contents'])
            ->not->toContain('artisan', "{$name} mentions artisan")
            ->not->toContain('config/baseline.php', "{$name} mentions config/baseline.php")
            ->not->toContain('Laravel project', "{$name} calls the project a Laravel project");
    }
});

it('ends updating-dependencies by recording the run in .baseline.json', function (): void {
    $contents = SkillInstaller::packaged()['updating-dependencies']['contents'];

    expect($contents)->toContain('### 7. Record the run (last)')
        ->toContain('.baseline.json')
        ->toContain('"updatesDependencies"');
});

it('syncs missing and outdated skills, and nothing else', function (): void {
    $project = makeProject(Profile::WordPress, ['.claude/skills/project-specific/SKILL.md' => "# mine\n"]);
    $targets = array_column(SkillInstaller::packaged(), 'target');

    expect(SkillInstaller::sync($project))->toBe($targets)
        ->and(SkillInstaller::sync($project))->toBe([]);

    $target = '.claude/skills/creating-a-release/SKILL.md';
    file_put_contents($project->path($target), "# outdated\n");

    expect(SkillInstaller::sync($project))->toBe([$target])
        ->and(file_get_contents($project->path($target)))->toBe(SkillInstaller::packaged()['creating-a-release']['contents'])
        ->and(file_get_contents($project->path('.claude/skills/project-specific/SKILL.md')))->toBe("# mine\n");
});

it('installs without overwriting unless forced', function (): void {
    $project = makeProject(Profile::Php, ['.claude/skills/creating-a-release/SKILL.md' => "# local\n"]);

    expect(SkillInstaller::install($project))->toBe([
        'installed' => ['ignoring-trivy-findings', 'updating-dependencies'],
        'skipped' => ['creating-a-release'],
    ])
        ->and(file_get_contents($project->path('.claude/skills/creating-a-release/SKILL.md')))->toBe("# local\n")
        ->and(SkillInstaller::install($project, force: true)['installed'])->toHaveCount(3);
});
