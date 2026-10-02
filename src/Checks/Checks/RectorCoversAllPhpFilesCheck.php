<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Concerns\AppendsToRectorChain;
use Limenet\LaravelBaseline\Enums\CheckResult;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Every PHP file the project owns sits under one of rector.php's paths, or
 * at the root with ->withRootFiles(), or is skipped by path in ->withSkip().
 *
 * The fix only adds ->withRootFiles(), and only when the root files are all
 * that is missing: which directories to process is the developer's call.
 */
class RectorCoversAllPhpFilesCheck extends AbstractCoversAllPhpFilesCheck implements FixableInterface
{
    use AppendsToRectorChain;

    public function check(): CheckResult
    {
        return $this->fix(dry: true);
    }

    public function fix(bool $dry = false): CheckResult
    {
        $rectorFile = $this->path('rector.php');

        if (!file_exists($rectorFile)) {
            $this->addComment('Rector configuration missing: create rector.php in the project root');

            return CheckResult::FAIL;
        }

        $config = $this->readConfig($rectorFile);

        if ($config === null) {
            $this->addComment('rector.php could not be parsed');

            return CheckResult::FAIL;
        }

        $paths = $config['paths'];

        if ($config['rootFiles']) {
            $paths = [...$paths, ...$this->rootFiles()];
        }

        $uncovered = $this->uncoveredFiles($paths, $config['skipped']);

        if ($uncovered === []) {
            return CheckResult::PASS;
        }

        // withRootFiles() only exists on the RectorConfig::configure() chain;
        // the legacy closure config has no equivalent to append.
        $onlyRootFiles = $config['fluent']
            && !$config['rootFiles']
            && array_filter($uncovered, fn (string $file): bool => str_contains($file, '/')) === [];

        $this->reportUncovered(
            'Rector',
            $uncovered,
            match (true) {
                $onlyRootFiles => 'add ->withRootFiles() to rector.php',
                $config['fluent'] => 'add them (or their directory) to ->withPaths() in rector.php, or to ->withSkip() if leaving them unprocessed is deliberate',
                default => 'add them (or their directory) to $rectorConfig->paths() in rector.php, or to $rectorConfig->skip() if leaving them unprocessed is deliberate',
            },
        );

        if ($dry || !$onlyRootFiles) {
            return CheckResult::FAIL;
        }

        $this->appendToRectorChain($rectorFile, '->withRootFiles()');

        return $this->fix(dry: true);
    }

    /**
     * The root-level PHP files ->withRootFiles() adds.
     *
     * @return list<string>
     */
    private function rootFiles(): array
    {
        return array_map(basename(...), glob($this->path().'/*.php') ?: []);
    }

    /**
     * @return array{paths: list<string>, rootFiles: bool, skipped: list<string>, fluent: bool}|null
     */
    private function readConfig(string $rectorFile): ?array
    {
        try {
            $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse((string) file_get_contents($rectorFile));
        } catch (\Throwable) {
            return null;
        }

        if ($ast === null) {
            return null;
        }

        $config = ['paths' => [], 'rootFiles' => false, 'skipped' => [], 'fluent' => false];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Expr\StaticCall::class) as $call) {
            if ($call->name instanceof Node\Identifier && $call->name->toString() === 'configure') {
                $config['fluent'] = true;
            }
        }

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Expr\MethodCall::class) as $call) {
            if (!$call->name instanceof Node\Identifier) {
                continue;
            }

            $array = ($call->args[0] ?? null) instanceof Node\Arg && $call->args[0]->value instanceof Node\Expr\Array_
                ? $call->args[0]->value
                : null;

            match ($call->name->toString()) {
                // paths() is the pre-RectorConfig::configure() spelling.
                'withPaths', 'paths' => array_push($config['paths'], ...$this->pathItems($array)),
                'withRootFiles' => $config['rootFiles'] = true,
                // Unkeyed entries skip paths; keyed ones skip a rule for some paths.
                'withSkip', 'skip' => array_push($config['skipped'], ...$this->pathItems($array)),
                default => null,
            };
        }

        return $config;
    }

    /**
     * Project-relative paths from the unkeyed `'x'`, `__DIR__`, or `__DIR__.'/x'` items of an array.
     *
     * @return list<string>
     */
    private function pathItems(?Node\Expr\Array_ $array): array
    {
        if ($array === null) {
            return [];
        }

        $paths = [];

        foreach ($array->items as $item) {
            if ($item->key !== null) {
                continue;
            }

            $value = $item->value;

            if ($value instanceof Node\Scalar\MagicConst\Dir) {
                $paths[] = '';
            } elseif ($value instanceof Node\Expr\BinaryOp\Concat
                && $value->left instanceof Node\Scalar\MagicConst\Dir
                && $value->right instanceof Node\Scalar\String_
            ) {
                $paths[] = ltrim($value->right->value, '/');
            } elseif ($value instanceof Node\Scalar\String_) {
                $paths[] = $value->value;
            }
        }

        return $paths;
    }
}
