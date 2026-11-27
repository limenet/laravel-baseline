<?php

use Limenet\LaravelBaseline\Checks\Checks\ViteWatchIgnoresGeneratedDirectoriesCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Policy\Policy;
use Limenet\LaravelBaseline\Project\Profile;

/**
 * @return list<string>
 */
function viteGlobs(string ...$groups): array
{
    return array_merge(...array_map(
        static fn (string $group): array => Policy::fromDirectory()->strings("vite.watchIgnored.{$group}"),
        $groups,
    ));
}

/**
 * @param  list<string>  $globs
 */
function viteConfig(array $globs): string
{
    $entries = implode('', array_map(static fn (string $glob): string => "                '{$glob}',\n", $globs));

    return "export default {\n    server: {\n        watch: {\n            ignored: [\n{$entries}            ],\n        },\n    },\n};\n";
}

it('viteWatchIgnoresGeneratedDirectories passes when the project has no Vite config', function (): void {
    $this->withTempBasePath(['package.json' => '{}']);

    expect(makeCheck(ViteWatchIgnoresGeneratedDirectoriesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('viteWatchIgnoresGeneratedDirectories requires the Laravel directories in a Laravel project only', function (): void {
    $withoutLaravel = viteConfig(viteGlobs('shared', 'composer'));

    $this->withTempBasePath(['vite.config.js' => $withoutLaravel]);

    [$check, $collector] = makeCheckWithCollector(ViteWatchIgnoresGeneratedDirectoriesCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toBe(["Missing in vite.config.js: server.watch.ignored must list '**/storage/**', '**/bootstrap/cache/**', so the dev server does not watch generated directories"]);

    foreach ([Profile::Php, Profile::WordPress] as $profile) {
        $project = makeProject($profile, ['vite.config.js' => $withoutLaravel]);

        expect(makeCheck(ViteWatchIgnoresGeneratedDirectoriesCheck::class, $project)->check())->toBe(CheckResult::PASS);
    }
});

it('viteWatchIgnoresGeneratedDirectories passes once everything is listed, in any order and quoting', function (): void {
    $globs = array_reverse(viteGlobs('shared', 'composer', 'laravel'));
    $entries = implode(', ', array_map(static fn (string $glob): string => "\"{$glob}\"", $globs));

    $this->withTempBasePath(['vite.config.ts' => "export default defineConfig({ server: { watch: { ignored: [{$entries}, /\\.tmp$/, ...more] } } })"]);

    expect(makeCheck(ViteWatchIgnoresGeneratedDirectoriesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('viteWatchIgnoresGeneratedDirectories does not count a glob in a comment or in another array', function (): void {
    $all = implode(', ', array_map(static fn (string $glob): string => "'{$glob}'", viteGlobs('shared', 'composer')));
    $project = makeProject(Profile::Php, [
        'vite.config.js' => "// ignored: [{$all}]\nconst generated = [{$all}];\n\nexport default {\n    build: { watch: { ignored: [{$all}] } },\n    server: { watch: { ignored: generated.slice() } },\n};\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(ViteWatchIgnoresGeneratedDirectoriesCheck::class, $project);

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all()[0])->toStartWith('Cannot verify server.watch.ignored in vite.config.js (server.watch.ignored is not an array literal)');
});

it('viteWatchIgnoresGeneratedDirectories reads the config Vite would load', function (): void {
    // Vite resolves vite.config.js before vite.config.ts.
    $project = makeProject(Profile::Php, [
        'vite.config.ts' => viteConfig(viteGlobs('shared', 'composer')),
        'vite.config.js' => 'export default {}',
    ]);

    [$check, $collector] = makeCheckWithCollector(ViteWatchIgnoresGeneratedDirectoriesCheck::class, $project);

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all()[0])->toStartWith('Missing in vite.config.js');
});

it('viteWatchIgnoresGeneratedDirectories leaves a config it cannot follow untouched', function (string $source, string $reason): void {
    $project = makeProject(Profile::WordPress, ['vite.config.js' => $source]);

    [$check, $collector] = makeCheckWithCollector(ViteWatchIgnoresGeneratedDirectoriesCheck::class, $project);

    expect($check->fix())->toBe(CheckResult::FAIL)
        ->and($collector->all()[0])->toContain("({$reason})")
        ->and(file_get_contents($project->path('vite.config.js')))->toBe($source);
})->with([
    'a variable' => ['export default config', 'it does not export a plain object literal'],
    'watch disabled' => ['export default { server: { watch: null } }', 'server.watch is not an object literal'],
    'shorthand server' => ['export default { server }', 'server is not an object literal'],
    'server twice' => ['export default { server: {}, server: {} }', 'server is declared more than once'],
    'spread at the root' => ['export default { ...base }', 'server may come from a spread or a computed key'],
    'a single string' => ["export default { server: { watch: { ignored: '**/vendor/**' } } }", 'server.watch.ignored is not an array literal'],
]);

it('viteWatchIgnoresGeneratedDirectories fills an empty config', function (): void {
    $project = makeProject(Profile::Php, ['vite.config.js' => "export default {}\n"]);

    expect(makeCheck(ViteWatchIgnoresGeneratedDirectoriesCheck::class, $project)->fix())->toBe(CheckResult::PASS)
        ->and(file_get_contents($project->path('vite.config.js')))->toBe(viteConfigUnterminated(viteGlobs('shared', 'composer')));
});

/**
 * @param  list<string>  $globs
 */
function viteConfigUnterminated(array $globs): string
{
    return str_replace("};\n", "}\n", viteConfig($globs));
}

it('viteWatchIgnoresGeneratedDirectories writes an inline block into a single-line config', function (): void {
    $project = makeProject(Profile::Php, ['vite.config.js' => "export default defineConfig({ plugins: [vue()] })\n"]);
    $entries = implode(', ', array_map(static fn (string $glob): string => "'{$glob}'", viteGlobs('shared', 'composer')));

    expect(makeCheck(ViteWatchIgnoresGeneratedDirectoriesCheck::class, $project)->fix())->toBe(CheckResult::PASS)
        ->and(file_get_contents($project->path('vite.config.js')))->toBe("export default defineConfig({ plugins: [vue()], server: { watch: { ignored: [{$entries}] } } })\n");
});

it('viteWatchIgnoresGeneratedDirectories indents with tabs where the file does', function (): void {
    $project = makeProject(Profile::Php, ['vite.config.js' => "export default {\n\tserver: {\n\t\twatch: {\n\t\t\tusePolling: true\n\t\t}\n\t}\n}\n"]);
    $entries = implode(",\n", array_map(static fn (string $glob): string => "\t\t\t\t'{$glob}'", viteGlobs('shared', 'composer')));

    expect(makeCheck(ViteWatchIgnoresGeneratedDirectoriesCheck::class, $project)->fix())->toBe(CheckResult::PASS)
        ->and(file_get_contents($project->path('vite.config.js')))->toBe("export default {\n\tserver: {\n\t\twatch: {\n\t\t\tusePolling: true,\n\t\t\tignored: [\n{$entries}\n\t\t\t]\n\t\t}\n\t}\n}\n");
});

it('viteWatchIgnoresGeneratedDirectories is fixed after one run', function (): void {
    $this->withTempBasePath(['vite.config.js' => "export default {\n    server: {\n        watch: {\n            ignored: ['**/vendor/**'],\n        },\n    },\n};\n"]);

    expect(makeCheck(ViteWatchIgnoresGeneratedDirectoriesCheck::class)->fix())->toBe(CheckResult::PASS)
        ->and(makeCheck(ViteWatchIgnoresGeneratedDirectoriesCheck::class)->check())->toBe(CheckResult::PASS);
});
