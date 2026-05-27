<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Health\HealthScheduleCheckHeartbeatVisitor;
use Limenet\LaravelBaseline\PhpFile\PhpFileWriter;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;

class UsesSpatieHealthScheduleCheckHeartbeatCheck extends AbstractFixableCheck
{
    private const CLASS_NAME = 'ScheduleCheck';

    private const MAX_AGE_IN_MINUTES = 2;

    public function fix(bool $dry = false): CheckResult
    {
        if (!$this->checkComposerPackages('spatie/laravel-health')) {
            return CheckResult::WARN;
        }

        $file = base_path('app/Providers/AppServiceProvider.php');

        if (!file_exists($file)) {
            $this->addComment($this->failComment());

            return CheckResult::FAIL;
        }

        $writer = PhpFileWriter::open($file);

        $visitor = new HealthScheduleCheckHeartbeatVisitor(self::CLASS_NAME, self::MAX_AGE_IN_MINUTES);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($writer->stmts);

        if ($visitor->wasFound()) {
            return CheckResult::PASS;
        }

        $this->addComment($this->failComment());

        if ($dry) {
            return CheckResult::FAIL;
        }

        // Locate the ScheduleCheck::new()... expression inside Health::checks([...])
        $arrayItem = $this->findScheduleCheckItem($writer->stmts);

        if (!$arrayItem instanceof Node\ArrayItem) {
            // No ScheduleCheck to extend — the missing-class fix lives in the core checks check.
            return CheckResult::FAIL;
        }

        // A wrong-value heartbeatMaxAgeInMinutes() call exists; appending another would
        // create a conflicting duplicate, so leave it for manual correction.
        if ($this->hasHeartbeatCall($arrayItem->value)) {
            return CheckResult::FAIL;
        }

        $arrayItem->value = new Node\Expr\MethodCall(
            $arrayItem->value,
            new Node\Identifier('heartbeatMaxAgeInMinutes'),
            [new Node\Arg(new Node\Scalar\Int_(self::MAX_AGE_IN_MINUTES))],
        );

        $writer->save();

        return $this->fix(dry: true);
    }

    /**
     * @param  Node\Stmt[]  $stmts
     */
    private function findScheduleCheckItem(array $stmts): ?Node\ArrayItem
    {
        $finder = new NodeFinder();

        $healthCall = $finder->findFirst(
            $stmts,
            fn ($n): bool => $n instanceof Node\Expr\StaticCall
                && $n->class instanceof Node\Name
                && $n->class->getLast() === 'Health'
                && $n->name instanceof Node\Identifier
                && $n->name->toString() === 'checks',
        );

        if (!$healthCall instanceof Node\Expr\StaticCall) {
            return null;
        }

        $firstArg = $healthCall->args[0] ?? null;

        if (!$firstArg instanceof Node\Arg || !$firstArg->value instanceof Node\Expr\Array_) {
            return null;
        }

        foreach ($firstArg->value->items as $item) {
            if ($item instanceof Node\ArrayItem && $this->extractRootClassName($item->value) === self::CLASS_NAME) {
                return $item;
            }
        }

        return null;
    }

    private function extractRootClassName(Node\Expr $expr): ?string
    {
        if (
            $expr instanceof Node\Expr\StaticCall
            && $expr->class instanceof Node\Name
            && $expr->name instanceof Node\Identifier
            && $expr->name->toString() === 'new'
        ) {
            return $expr->class->getLast();
        }

        if ($expr instanceof Node\Expr\MethodCall) {
            return $this->extractRootClassName($expr->var);
        }

        return null;
    }

    private function hasHeartbeatCall(Node\Expr $expr): bool
    {
        if (!$expr instanceof Node\Expr\MethodCall) {
            return false;
        }

        if ($expr->name instanceof Node\Identifier && $expr->name->toString() === 'heartbeatMaxAgeInMinutes') {
            return true;
        }

        return $this->hasHeartbeatCall($expr->var);
    }

    private function failComment(): string
    {
        return 'ScheduleCheck not configured correctly: Use ScheduleCheck::new()->heartbeatMaxAgeInMinutes(2) in Health::checks() in AppServiceProvider to prevent false positives';
    }
}
