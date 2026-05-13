<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Health\HealthChecksStaticCallVisitor;
use Limenet\LaravelBaseline\PhpFile\PhpFileWriter;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;

abstract class AbstractUsesSpatieHealthChecksCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        if (!$this->checkComposerPackages($this->requiredComposerPackages())) {
            return CheckResult::WARN;
        }

        $file = base_path('app/Providers/AppServiceProvider.php');

        if (!file_exists($file)) {
            $this->addComment($this->missingChecksComment());

            return CheckResult::FAIL;
        }

        $writer = PhpFileWriter::open($file);

        $visitor = new HealthChecksStaticCallVisitor($this->requiredHealthCheckClasses());
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($writer->stmts);

        if ($visitor->wasFound()) {
            return CheckResult::PASS;
        }

        $this->addComment($this->missingChecksComment());

        if ($dry) {
            return CheckResult::FAIL;
        }

        $finder = new NodeFinder();

        // Find existing Health::checks([...]) call to extend
        $healthCall = $finder->findFirst(
            $writer->stmts,
            fn ($n): bool => $n instanceof Node\Expr\StaticCall
                && $n->class instanceof Node\Name
                && $n->class->getLast() === 'Health'
                && $n->name instanceof Node\Identifier
                && $n->name->toString() === 'checks',
        );

        if ($healthCall instanceof Node\Expr\StaticCall) {
            $firstArg = $healthCall->args[0] ?? null;

            if ($firstArg instanceof Node\Arg && $firstArg->value instanceof Node\Expr\Array_) {
                foreach ($visitor->getMissingClasses() as $shortName) {
                    $firstArg->value->items[] = new Node\ArrayItem(
                        new Node\Expr\StaticCall(
                            new Node\Name($shortName),
                            new Node\Identifier('new'),
                        ),
                    );
                }
            }
        } else {
            $bootMethod = $finder->findFirst(
                $writer->stmts,
                fn ($n): bool => $n instanceof Node\Stmt\ClassMethod
                    && $n->name->toString() === 'boot',
            );

            if (!$bootMethod instanceof Node\Stmt\ClassMethod) {
                return CheckResult::FAIL;
            }

            $items = array_map(
                fn (string $shortName): Node\ArrayItem => new Node\ArrayItem(
                    new Node\Expr\StaticCall(
                        new Node\Name($shortName),
                        new Node\Identifier('new'),
                    ),
                ),
                $this->requiredHealthCheckClasses(),
            );

            $bootMethod->stmts[] = new Node\Stmt\Expression(
                new Node\Expr\StaticCall(
                    new Node\Name('Health'),
                    new Node\Identifier('checks'),
                    [new Node\Arg(new Node\Expr\Array_($items))],
                ),
            );
        }

        $writer->addMissingUseStatements($this->healthCheckFqns());
        $writer->save();

        return $this->fix(dry: true);
    }

    /** @return list<string> Short class names required in Health::checks([...]) */
    abstract protected function requiredHealthCheckClasses(): array;

    protected function missingChecksComment(): string
    {
        $classes = implode(', ', $this->requiredHealthCheckClasses());

        return "Health checks not registered: Add Health::checks([{$classes}]) in AppServiceProvider";
    }

    /** @return list<string> */
    protected function requiredComposerPackages(): array
    {
        return ['spatie/laravel-health'];
    }

    /**
     * @return list<string>
     */
    protected function healthCheckFqns(): array
    {
        return [];
    }
}
