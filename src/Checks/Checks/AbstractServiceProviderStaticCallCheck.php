<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\PhpFile\PhpFileWriter;
use Limenet\LaravelBaseline\ServiceProvider\StaticCallVisitor;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

abstract class AbstractServiceProviderStaticCallCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $file = base_path('app/Providers/AppServiceProvider.php');

        if (!file_exists($file)) {
            $this->addComment($this->missingCallComment());

            return CheckResult::FAIL;
        }

        $writer = PhpFileWriter::open($file);

        $visitor = new StaticCallVisitor($this->staticClassName(), $this->staticMethodName());
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($writer->stmts);

        if ($visitor->wasFound() && !$visitor->isValid()) {
            $this->addComment($this->falseLiteralComment());

            return CheckResult::FAIL;
        }

        if ($visitor->wasFound()) {
            return CheckResult::PASS;
        }

        // Not found
        $this->addComment($this->missingCallComment());

        if ($dry) {
            return CheckResult::FAIL;
        }

        // Apply fix: parse the statement and prepend to boot()
        $stmtCode = '<?php '.$this->fixStatement();
        $stmtAst = (new ParserFactory())->createForNewestSupportedVersion()->parse($stmtCode) ?? [];

        if ($stmtAst === []) {
            return CheckResult::FAIL;
        }

        $finder = new NodeFinder();
        $bootMethod = $finder->findFirst(
            $writer->stmts,
            fn ($n): bool => $n instanceof Node\Stmt\ClassMethod
                && $n->name->toString() === 'boot',
        );

        if (!$bootMethod instanceof Node\Stmt\ClassMethod) {
            return CheckResult::FAIL;
        }

        $bootMethod->stmts = array_merge($stmtAst, $bootMethod->stmts ?? []);

        $writer->addMissingUseStatements($this->fixImports());
        $writer->save();

        return $this->fix(dry: true);
    }

    abstract protected function staticClassName(): string;

    abstract protected function staticMethodName(): string;

    abstract protected function missingCallComment(): string;

    abstract protected function falseLiteralComment(): string;

    abstract protected function fixStatement(): string;

    /**
     * @return list<string>
     */
    protected function fixImports(): array
    {
        return [];
    }
}
