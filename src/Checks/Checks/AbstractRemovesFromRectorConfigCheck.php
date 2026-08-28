<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\PhpFile\PhpFileWriter;
use Limenet\LaravelBaseline\Rector\RectorRemovalVisitor;
use PhpParser\Node;
use PhpParser\NodeTraverser;

/**
 * Base for the checks that take something *out* of rector.php.
 *
 * The mirror image of {@see AbstractHasRectorConfigCheck}: a mandated call that
 * later turns out to be wrong cannot simply stop being mandated, because every
 * project that already ran the old fix still carries it. These checks fail
 * while the offending reference is present and remove it on --fix.
 *
 * The use-statement the removal orphans is left alone: every project the
 * baseline governs runs Pint and Rector over its own tree, and both strip an
 * unused import on the next pass.
 */
abstract class AbstractRemovesFromRectorConfigCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $rectorFile = base_path('rector.php');

        if (!file_exists($rectorFile)) {
            return CheckResult::PASS;
        }

        try {
            $writer = PhpFileWriter::open($rectorFile);
        } catch (\Throwable) {
            // Unparseable rector.php — the hasRectorConfigWith* checks report that
            return CheckResult::PASS;
        }

        $visitor = new RectorRemovalVisitor($this->methodName(), $this->classShortNames());

        /** @var Node\Stmt[] $stmts */
        $stmts = (new NodeTraverser($visitor))->traverse($writer->stmts);
        $writer->stmts = $stmts;

        if (!$visitor->hasRemoved()) {
            return CheckResult::PASS;
        }

        $this->addComment($this->removalComment());

        if ($dry) {
            return CheckResult::FAIL;
        }

        $writer->save();

        return CheckResult::PASS;
    }

    /**
     * The RectorConfig method to take the references out of.
     */
    abstract protected function methodName(): string;

    /**
     * Short class names to remove from that call's arguments.
     *
     * @return list<string>
     */
    abstract protected function classShortNames(): array;

    abstract protected function removalComment(): string;
}
