<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\PhpFile\PhpFileWriter;
use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorCacheDirectory;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Rector caches under the system temp directory by default, which a CI job
 * cannot carry over to the next pipeline. Inside the project the CI templates
 * can cache it, so an unchanged file is not processed again.
 */
class HasRectorConfigWithCacheCheck extends AbstractHasRectorConfigCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    public function fix(bool $dry = false): CheckResult
    {
        $rectorFile = $this->path('rector.php');
        $result = $this->runVisitorOnRector($this->makeVisitor());

        if ($result !== null) {
            if ($dry) {
                return $result;
            }

            // The parent appends the call, but gives up on one that is already
            // there with another directory; that one gets its argument replaced.
            if (!file_exists($rectorFile) || !$this->replaceCacheDirectory($rectorFile)) {
                parent::fix();
            }
        }

        $gitignoreResult = $this->ensureGitignoreEntry('/'.$this->directory().'/', 'ignore the Rector cache directory', $dry);

        if ($gitignoreResult !== null && $dry) {
            return $gitignoreResult;
        }

        return $dry ? CheckResult::PASS : $this->fix(dry: true);
    }

    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorCacheDirectory($this->commentCollector, 'withCache', [$this->directory()]);
    }

    protected function fixCodeSnippet(): string
    {
        return "->withCache(cacheDirectory: __DIR__.'/{$this->directory()}')";
    }

    private function directory(): string
    {
        return trim($this->policy()->string('toolCache.rector'), '/');
    }

    /**
     * Points an existing `withCache()` at the project directory, keeping its
     * other arguments. False when rector.php has no such call.
     */
    private function replaceCacheDirectory(string $rectorFile): bool
    {
        $writer = PhpFileWriter::open($rectorFile);

        $call = (new NodeFinder)->findFirst($writer->stmts, static fn (Node $node): bool => $node instanceof Node\Expr\MethodCall
            && $node->name instanceof Node\Identifier
            && $node->name->toString() === 'withCache');

        if (!$call instanceof Node\Expr\MethodCall) {
            return false;
        }

        $value = new Node\Expr\BinaryOp\Concat(new Node\Scalar\MagicConst\Dir, new Node\Scalar\String_('/'.$this->directory()));
        $arg = RectorVisitorCacheDirectory::cacheDirectoryArg($call);

        if ($arg instanceof Node\Arg) {
            $arg->value = $value;
        } else {
            array_unshift($call->args, new Node\Arg($value, name: new Node\Identifier('cacheDirectory')));
        }

        $writer->save();

        return true;
    }
}
