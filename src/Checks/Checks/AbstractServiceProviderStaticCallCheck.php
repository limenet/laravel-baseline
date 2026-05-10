<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\ServiceProvider\StaticCallVisitor;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

abstract class AbstractServiceProviderStaticCallCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $file = base_path('app/Providers/AppServiceProvider.php');

        if (!file_exists($file)) {
            $this->addComment($this->missingCallComment());

            return CheckResult::FAIL;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $code = file_get_contents($file) ?: '';
            $ast = $parser->parse($code) ?? [];
        } catch (\Throwable) {
            return CheckResult::FAIL;
        }

        $visitor = new StaticCallVisitor($this->staticClassName(), $this->staticMethodName());
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

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
        $stmtAst = $parser->parse($stmtCode) ?? [];

        if ($stmtAst === []) {
            return CheckResult::FAIL;
        }

        $finder = new NodeFinder();
        $bootMethod = $finder->findFirst(
            $ast,
            fn ($n): bool => $n instanceof Node\Stmt\ClassMethod
                && $n->name->toString() === 'boot',
        );

        if (!$bootMethod instanceof Node\Stmt\ClassMethod) {
            return CheckResult::FAIL;
        }

        $bootMethod->stmts = array_merge($stmtAst, $bootMethod->stmts ?? []);

        $this->addMissingUseStatements($ast, $this->fixImports());

        file_put_contents($file, (new PrettyPrinter\Standard())->prettyPrintFile($ast));

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

    /**
     * @param  array<Node>  $ast
     * @param  list<string>  $imports
     */
    private function addMissingUseStatements(array &$ast, array $imports): void
    {
        if ($imports === []) {
            return;
        }

        $existingFqns = [];
        $lastUseIdx = -1;

        foreach ($ast as $i => $stmt) {
            if ($stmt instanceof Node\Stmt\Use_) {
                $lastUseIdx = $i;

                foreach ($stmt->uses as $use) {
                    $existingFqns[] = $use->name->toString();
                }
            }
        }

        $newUses = [];

        foreach ($imports as $fqn) {
            if (!in_array($fqn, $existingFqns, true)) {
                $newUses[] = new Node\Stmt\Use_([
                    new Node\UseItem(new Node\Name($fqn)),
                ]);
            }
        }

        if ($newUses !== []) {
            $insertIdx = $lastUseIdx >= 0 ? $lastUseIdx + 1 : 1;
            array_splice($ast, $insertIdx, 0, $newUses);
        }
    }
}
