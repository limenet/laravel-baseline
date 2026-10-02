<?php

use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Runner\CheckRunner;
use Symfony\Component\Console\Output\BufferedOutput;

it('reports failures with the exclude hint for .baseline.json', function (): void {
    $project = makeProject(Profile::Php, ['composer.json' => json_encode(['name' => 'tmp'])]);
    $output = new BufferedOutput;

    $errors = (new CheckRunner($project, $output))->run();

    expect($errors)->toBeGreaterThan(0)
        ->and($output->fetch())->toContain('❌ bumps Composer')
        ->toContain('To exclude, add bumpsComposer to the excludes in .baseline.json');
});

it('skips checks excluded in .baseline.json', function (): void {
    $project = makeProject(Profile::Php, [
        'composer.json' => json_encode(['name' => 'tmp']),
        '.baseline.json' => json_encode(['excludes' => ['bumpsComposer']]),
    ]);
    $output = new BufferedOutput;

    (new CheckRunner($project, $output))->run();

    expect($output->fetch())->toContain('⚪ bumps Composer (excluded)')
        ->not->toContain('❌ bumps Composer');
});

it('fixes what it can when asked to', function (): void {
    $project = makeProject(Profile::Php, ['composer.json' => json_encode(['name' => 'tmp'])]);
    $output = new BufferedOutput;

    (new CheckRunner($project, $output))->run(fix: true);

    expect($output->fetch())->toContain('🔧 bumps Composer (fixed)')
        ->and(json_decode((string) file_get_contents($project->path('composer.json')), true)['scripts']['post-update-cmd'] ?? [])
        ->toContain('composer bump');
});
