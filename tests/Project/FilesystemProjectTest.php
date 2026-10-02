<?php

use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\State\JsonStateStore;

it('resolves paths with base_path semantics', function (): void {
    $project = makeProject(Profile::Php);

    expect($project->path())->toBe($project->path(''))
        ->and($project->path('composer.json'))->toBe($project->path().DIRECTORY_SEPARATOR.'composer.json')
        ->and($project->path('/composer.json'))->toBe($project->path('composer.json'));
});

it('reports the profile it was created with', function (Profile $profile): void {
    expect(makeProject($profile)->profile())->toBe($profile);
})->with([Profile::Php, Profile::WordPress]);

it('finds packages in require and require-dev', function (): void {
    $project = makeProject(Profile::Php, ['composer.json' => json_encode([
        'require' => ['php' => '^8.4', 'vendor/runtime' => '^1.0'],
        'require-dev' => ['vendor/dev' => '^2.0'],
    ])]);

    expect($project->hasComposerPackage('vendor/runtime'))->toBeTrue()
        ->and($project->hasComposerPackage('vendor/dev'))->toBeTrue()
        ->and($project->hasComposerPackage('vendor/absent'))->toBeFalse();
});

it('reports no packages when composer.json is missing or invalid', function (array $files): void {
    expect(makeProject(Profile::Php, $files)->hasComposerPackage('vendor/runtime'))->toBeFalse();
})->with([
    'missing' => [[]],
    'invalid' => [['composer.json' => '{not json']],
]);

it('reads the shipped policy', function (): void {
    expect(makeProject(Profile::Php)->policy()->int('periodic.defaultIntervalDays'))->toBeGreaterThan(0);
});

it('keeps its state in .baseline.json', function (): void {
    $project = makeProject(Profile::Php);

    expect($project->state())->toBeInstanceOf(JsonStateStore::class)
        ->and($project->state()->location())->toBe('.baseline.json');
});
