<?php

use Limenet\LaravelBaseline\Checks\CheckInterface;
use Limenet\LaravelBaseline\Checks\CheckRegistry;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Policy\Policy;
use Symfony\Component\Finder\Finder;

/**
 * Executes the shared fixtures in `fixtures/` — the same tree the npm runner's
 * vitest suite executes. The contract is deliberately narrow: the verdict, and
 * the file state a fix leaves behind. Comment wording is NOT compared, because
 * byte-identical prose across two languages costs more than it is worth; each
 * runner's own per-check tests cover its messages.
 *
 * These tests are additive — every existing per-check test still stands.
 */
function conformanceFixtureRoot(): string
{
    return dirname(__DIR__, 2).'/fixtures';
}

/**
 * @return array<string, array{0: string}>
 */
function conformanceCases(string $engine, bool $onlyFixable = false): array
{
    $cases = [];

    foreach (glob(conformanceFixtureRoot().'/*/*/case.json') ?: [] as $file) {
        /** @var array<string,mixed> $case */
        $case = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        if (!in_array($engine, $case['engines'], true)) {
            continue;
        }

        if ($onlyFixable && !isset($case['fix'])) {
            continue;
        }

        $cases[$case['check'].' — '.$case['description']] = [dirname($file)];
    }

    return $cases;
}

/**
 * @return array<string,string> relative path => content
 */
function conformanceProjectFiles(string $projectDir): array
{
    $files = [];

    $finder = (new Finder)
        ->in($projectDir)
        ->files()
        ->ignoreDotFiles(false)
        ->ignoreVCS(false);

    foreach ($finder as $file) {
        $files[$file->getRelativePathname()] = $file->getContents();
    }

    return $files;
}

/**
 * @return class-string<CheckInterface>
 */
function conformanceCheckClass(string $name): string
{
    foreach (CheckRegistry::all() as $class) {
        if ($class::name() === $name) {
            return $class;
        }
    }

    throw new RuntimeException("Fixture references unregistered check \"{$name}\"");
}

/**
 * @return array<string,mixed>
 */
function conformanceCase(string $dir): array
{
    return json_decode((string) file_get_contents($dir.'/case.json'), true, flags: JSON_THROW_ON_ERROR);
}

it('reaches the shared fixture verdict', function (string $dir): void {
    $case = conformanceCase($dir);

    $this->withTempBasePath(conformanceProjectFiles($dir.'/project'));

    $check = makeCheck(conformanceCheckClass($case['check']));

    expect($check->check())->toBe(CheckResult::from($case['expect']));
})->with(fn (): array => conformanceCases('php'));

it('leaves the shared fixture file state behind after a fix', function (string $dir): void {
    $case = conformanceCase($dir);

    $this->withTempBasePath(conformanceProjectFiles($dir.'/project'));

    $check = makeCheck(conformanceCheckClass($case['check']));

    expect($check)->toBeInstanceOf(FixableInterface::class);
    expect($check->fix())->toBe(CheckResult::from($case['fix']['expect']));

    foreach ($case['fix']['files'] ?? [] as $path => $expected) {
        // @template:<name> resolves to policy/templates/<name>, so canonical
        // file bodies are never duplicated into a fixture.
        if (str_starts_with((string) $expected, '@template:')) {
            $expected = Policy::fromDirectory()->template(substr((string) $expected, strlen('@template:')));
        }

        expect(file_get_contents(base_path($path)))->toBe($expected);
    }

    foreach ($case['fix']['json'] ?? [] as $path => $assertions) {
        $data = json_decode((string) file_get_contents(base_path($path)), true, flags: JSON_THROW_ON_ERROR);

        foreach ($assertions as $key => $expected) {
            expect(data_get($data, $key))->toBe($expected);
        }
    }

    foreach ($case['fix']['absent'] ?? [] as $path) {
        expect(file_exists(base_path($path)))->toBeFalse();
    }
})->with(fn (): array => conformanceCases('php', onlyFixable: true));

it('names a registered check and a real project directory in every fixture', function (): void {
    $files = glob(conformanceFixtureRoot().'/*/*/case.json') ?: [];

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $case = conformanceCase(dirname($file));

        expect($case)->toHaveKeys(['check', 'description', 'engines', 'expect']);
        expect(CheckResult::tryFrom($case['expect']))->not->toBeNull();
        expect($case['engines'])->not->toBeEmpty();
        expect(is_dir(dirname($file).'/project'))->toBeTrue();

        // Only checks this runner claims must resolve here — a fixture may target
        // the npm runner alone (e.g. ciSetsNodeVersion, which has no PHP
        // counterpart). The vitest suite makes the mirror-image assertion.
        if (in_array('php', $case['engines'], true)) {
            conformanceCheckClass($case['check']);
        }

        // The directory name must be the kebab-case of the check it exercises,
        // so a rename cannot leave the tree half-migrated.
        $expectedDir = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $case['check']));
        expect(basename(dirname($file, 2)))->toBe($expectedDir);
    }
});

it('keeps policy/ inside the composer distribution archive', function (): void {
    // The PHP checks read policy/policy.json from vendor/ at runtime, so an
    // export-ignore slip breaks every consumer on install — silently, until
    // someone upgrades. .gitattributes here is an allowlist-by-omission.
    $root = dirname(__DIR__, 2);

    if (!is_dir($root.'/.git')) {
        $this->markTestSkipped('not a git checkout');
    }

    $output = shell_exec('git -C '.escapeshellarg($root).' check-attr export-ignore policy/policy.json 2>&1');

    expect($output)->toContain('export-ignore: unspecified');
});
