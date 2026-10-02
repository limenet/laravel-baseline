<?php

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Checks\CheckRegistry;
use Limenet\LaravelBaseline\Project\FilesystemProject;
use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Runner\CheckRunner;
use Limenet\LaravelBaseline\Runner\PeriodicRunner;
use Limenet\LaravelBaseline\State\JsonStateStore;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * The standalone runner executes in projects that have neither laravel/framework
 * nor illuminate/support installed, so anything it can reach must not touch
 * them. The test suite itself always has Laravel loaded, which is exactly why a
 * leak would otherwise go unnoticed until a consumer's run fatals.
 *
 * This walks the classes reachable from the non-Laravel checks and the
 * standalone entry points, and fails on any Laravel helper function or
 * Illuminate/Laravel class reference. Laravel\Prompts is framework-free and allowed.
 */
const STANDALONE_FORBIDDEN_FUNCTIONS = [
    'app', 'app_path', 'base_path', 'class_basename', 'collect', 'config', 'config_path',
    'data_get', 'data_set', 'database_path', 'env', 'now', 'public_path', 'resource_path',
    'storage_path', 'str', 'tap', 'today', 'value',
];

/**
 * Classes the standalone runner reaches without going through a check.
 *
 * @return list<class-string>
 */
function standaloneEntryPoints(): array
{
    $roots = [
        FilesystemProject::class,
        JsonStateStore::class,
        CheckRunner::class,
        PeriodicRunner::class,
    ];

    foreach (glob(dirname(__DIR__).'/src/Cli/{,*/}*.php', GLOB_BRACE) ?: [] as $file) {
        $roots[] = 'Limenet\\LaravelBaseline\\'.str_replace(['/', '.php'], ['\\', ''], substr($file, strlen(dirname(__DIR__).'/src/')));
    }

    return array_values(array_filter($roots, fn (string $class): bool => class_exists($class)));
}

/**
 * @return array{list<string>, list<string>} [own-namespace classes referenced, violations]
 */
function standaloneScanFile(string $file): array
{
    $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse((string) file_get_contents($file)) ?? [];
    $traverser = new NodeTraverser;
    $traverser->addVisitor(new NameResolver);
    $ast = $traverser->traverse($ast);

    $references = [];
    $violations = [];

    foreach ((new NodeFinder)->find($ast, fn (Node $node): bool => $node instanceof Node\Name) as $name) {
        /** @var Node\Name $name */
        $resolved = $name->toString();

        if (preg_match('/^(Illuminate|Laravel(?!\\\\Prompts)|Spatie\\\\LaravelPackageTools|Orchestra)\\\\/', $resolved) === 1) {
            $violations[] = $resolved;
        }

        if (str_starts_with($resolved, 'Limenet\\LaravelBaseline\\')) {
            $references[] = $resolved;
        }
    }

    foreach ((new NodeFinder)->findInstanceOf($ast, Node\Expr\FuncCall::class) as $call) {
        if ($call->name instanceof Node\Name && in_array(strtolower($call->name->toString()), STANDALONE_FORBIDDEN_FUNCTIONS, true)) {
            $violations[] = $call->name->toString().'()';
        }
    }

    return [$references, $violations];
}

it('keeps everything the standalone runner can reach free of Laravel', function (): void {
    $queue = [
        ...standaloneEntryPoints(),
        ...CheckRegistry::for(Profile::Php),
        ...CheckRegistry::for(Profile::WordPress),
    ];
    $seen = [];
    $violations = [];

    while ($queue !== []) {
        $class = array_shift($queue);

        if (isset($seen[$class]) || (!class_exists($class) && !interface_exists($class) && !trait_exists($class) && !enum_exists($class))) {
            continue;
        }

        $seen[$class] = true;
        $reflection = new ReflectionClass($class);
        $file = $reflection->getFileName();

        if ($file === false) {
            continue;
        }

        [$references, $fileViolations] = standaloneScanFile($file);

        foreach ($fileViolations as $violation) {
            $violations[] = "{$class} uses {$violation}";
        }

        // The registry names every check; only the ones a non-Laravel profile
        // creates are reachable, and those are already queued above.
        if ($class !== CheckRegistry::class) {
            array_push($queue, ...$references);
        }
    }

    expect($seen)->toHaveKey(AbstractCheck::class)
        ->and(array_values(array_unique($violations)))->toBe([]);
});
