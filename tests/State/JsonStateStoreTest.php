<?php

use Limenet\LaravelBaseline\Project\Profile;

it('is empty when .baseline.json does not exist', function (): void {
    $state = makeProject(Profile::Php)->state();

    expect($state->excludes())->toBe([])
        ->and($state->storedExcludes())->toBe([])
        ->and($state->lastRun('updatesDependencies'))->toBeNull();
});

it('reads excludes, honouring only strings', function (): void {
    $state = makeProject(Profile::Php, [
        '.baseline.json' => json_encode(['excludes' => ['usesPest', 42, 'bumpsComposer']]),
    ])->state();

    expect($state->excludes())->toBe(['usesPest', 'bumpsComposer'])
        ->and($state->storedExcludes())->toBe(['usesPest', 42, 'bumpsComposer']);
});

it('reads a last run written by the npm runner', function (): void {
    $state = makeProject(Profile::Php, [
        '.baseline.json' => json_encode(['periodic' => ['updatesDependencies' => '2026-01-15T10:00:00.000Z']]),
    ])->state();

    expect($state->lastRun('updatesDependencies')?->format('Y-m-d'))->toBe('2026-01-15');
});

it('records a run and keeps keys it does not own', function (): void {
    $project = makeProject(Profile::Php, [
        '.baseline.json' => json_encode(['profile' => 'wordpress', 'excludes' => ['usesPest']]),
    ]);

    $project->state()->recordRun('updatesDependencies', new DateTimeImmutable('2026-03-01T12:00:00+00:00'));

    $written = (string) file_get_contents($project->path('.baseline.json'));

    expect(json_decode($written, true))->toBe([
        'profile' => 'wordpress',
        'excludes' => ['usesPest'],
        'periodic' => ['updatesDependencies' => '2026-03-01T12:00:00+00:00'],
    ])
        ->and($written)->toEndWith("}\n")
        ->and($written)->toContain("\n    \"profile\"");
});

it('replaces the excludes', function (): void {
    $project = makeProject(Profile::Php, [
        '.baseline.json' => json_encode(['excludes' => ['usesPest', 'gone'], 'periodic' => ['x' => 'y']]),
    ]);

    $project->state()->setExcludes(['usesPest']);

    expect(json_decode((string) file_get_contents($project->path('.baseline.json')), true))
        ->toBe(['excludes' => ['usesPest'], 'periodic' => ['x' => 'y']]);
});

it('refuses to overwrite a .baseline.json it cannot parse', function (): void {
    $project = makeProject(Profile::Php, ['.baseline.json' => '{broken']);

    expect(fn () => $project->state()->setExcludes([]))->toThrow(JsonException::class);
});
