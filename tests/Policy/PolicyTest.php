<?php

use Limenet\LaravelBaseline\Policy\Policy;
use Limenet\LaravelBaseline\Policy\PolicyException;

it('resolves the shipped policy directory relative to the package', function (): void {
    expect(Policy::defaultDirectory())->toBe(dirname(__DIR__, 2).'/policy');
    expect(file_exists(Policy::defaultDirectory().'/policy.json'))->toBeTrue();
});

it('loads every key the checks depend on with the declared type', function (): void {
    $policy = Policy::fromDirectory();

    expect($policy->string('node.minMajor'))->toBeString();
    expect($policy->string('npm.minMajor'))->toBeString();
    expect($policy->string('npm.constraint'))->toBeString();
    expect($policy->int('npm.minReleaseAgeDays'))->toBeInt();
    expect($policy->bool('npm.engineStrict'))->toBeBool();
    expect($policy->string('editorconfig.template'))->toBeString();
    expect($policy->strings('editorconfig.requiredProperties'))->not->toBeEmpty();
    expect($policy->stringMap('agentFiles.forbidden'))->not->toBeEmpty();
    expect($policy->strings('claude.allow.shared'))->not->toBeEmpty();
    expect($policy->strings('claude.allow.php'))->not->toBeEmpty();
    expect($policy->strings('claude.deny.shared'))->not->toBeEmpty();
    expect($policy->string('claude.ciLintHookCommand.php'))->toBeString();
    expect($policy->string('claude.ciLintHookCommand.js'))->toBeString();
    expect($policy->stringListMap('ci.requiredJobs.php'))->not->toBeEmpty();
    expect($policy->stringListMap('ci.requiredJobs.js'))->not->toBeEmpty();
    expect($policy->int('periodic.defaultIntervalDays'))->toBeInt();
});

it('normalises a single accepted CI template into a list', function (): void {
    $map = Policy::fromArray(['ci' => ['requiredJobs' => ['php' => [
        'build' => '.build',
        'test' => ['.test', '.test_db'],
    ]]]])->stringListMap('ci.requiredJobs.php');

    expect($map)->toBe(['build' => ['.build'], 'test' => ['.test', '.test_db']]);
});

it('throws when an accepted-template list holds a non-string', function (): void {
    Policy::fromArray(['ci' => ['requiredJobs' => ['php' => ['test' => [1]]]]])->stringListMap('ci.requiredJobs.php');
})->throws(PolicyException::class, 'is not a map of strings or lists of strings');

it('resolves templates relative to the policy directory', function (): void {
    $policy = Policy::fromDirectory();

    $editorconfig = $policy->template($policy->string('editorconfig.template'));

    expect($editorconfig)->toStartWith('root = true');
    expect($editorconfig)->toEndWith("\n");

    // Every property the check requires must actually appear in the file it writes.
    foreach ($policy->strings('editorconfig.requiredProperties') as $property) {
        expect($editorconfig)->toContain($property);
    }
});

it('keeps the shared claude entries free of DDEV-specific rules', function (): void {
    // The npm runner requires the shared half verbatim, so anything DDEV-shaped
    // leaking into it would be unsatisfiable in a JS-only project.
    foreach (Policy::fromDirectory()->strings('claude.allow.shared') as $entry) {
        expect($entry)->not->toContain('ddev');
        expect($entry)->not->toContain('artisan');
        expect($entry)->not->toContain('composer');
    }
});

it('throws a helpful error for a missing key', function (): void {
    Policy::fromArray(['node' => ['minMajor' => '24']])->string('node.nope');
})->throws(PolicyException::class, 'Policy key "node.nope" is not defined');

it('throws when a key holds the wrong type', function (): void {
    Policy::fromArray(['npm' => ['minReleaseAgeDays' => '7']])->int('npm.minReleaseAgeDays');
})->throws(PolicyException::class, 'is not a int');

it('throws when the policy file is absent', function (): void {
    Policy::fromDirectory('/nonexistent/policy');
})->throws(PolicyException::class, 'is missing or unreadable');

it('is overridable in the container so checks can be tested against fixed values', function (): void {
    app()->instance(Policy::class, Policy::fromArray(['node' => ['minMajor' => '99']]));

    expect(app(Policy::class)->string('node.minMajor'))->toBe('99');
});
