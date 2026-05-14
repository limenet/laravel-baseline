<?php

namespace Limenet\LaravelBaseline\Health;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

abstract class AbstractHealthChecksVisitor extends NodeVisitorAbstract
{
    final public function enterNode(Node $node): null
    {
        if (!$node instanceof Node\Expr\StaticCall) {
            return null;
        }

        if (!$node->class instanceof Node\Name || $node->class->toString() !== 'Health') {
            return null;
        }

        if (!$node->name instanceof Node\Identifier || $node->name->toString() !== 'checks') {
            return null;
        }

        $firstArg = $node->args[0] ?? null;

        if (!$firstArg instanceof Node\Arg || !$firstArg->value instanceof Node\Expr\Array_) {
            return null;
        }

        $this->processChecksArray($firstArg->value);

        return null;
    }

    abstract protected function processChecksArray(Node\Expr\Array_ $array): void;

    protected function extractRootClassName(Node\Expr $expr): ?string
    {
        // ClassName::new()
        if (
            $expr instanceof Node\Expr\StaticCall
            && $expr->class instanceof Node\Name
            && $expr->name instanceof Node\Identifier
            && $expr->name->toString() === 'new'
        ) {
            return $expr->class->getLast();
        }

        // ClassName::new()->chainedMethod() — walk up the chain
        if ($expr instanceof Node\Expr\MethodCall) {
            return $this->extractRootClassName($expr->var);
        }

        // $cond ? ClassName::new()->... : ClassName::new() — use either branch
        if ($expr instanceof Node\Expr\Ternary) {
            return $this->extractRootClassName($expr->if ?? $expr->else)
                ?? $this->extractRootClassName($expr->else);
        }

        return null;
    }
}
