<?php

namespace Limenet\LaravelBaseline\Health;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class HealthScheduleCheckConfigVisitor extends NodeVisitorAbstract
{
    private bool $valid = false;

    public function enterNode(Node $node): null
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

        foreach ($firstArg->value->items as $item) {
            if ($item === null) {
                continue;
            }

            if ($this->extractClassName($item->value) === 'ScheduleCheck'
                && $this->hasHeartbeatMaxAgeInMinutes($item->value)) {
                $this->valid = true;

                return null;
            }
        }

        return null;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    private function extractClassName(Node\Expr $expr): ?string
    {
        if ($expr instanceof Node\Expr\StaticCall
            && $expr->class instanceof Node\Name
            && $expr->name instanceof Node\Identifier
            && $expr->name->toString() === 'new') {
            return $expr->class->getLast();
        }

        if ($expr instanceof Node\Expr\MethodCall) {
            return $this->extractClassName($expr->var);
        }

        return null;
    }

    private function hasHeartbeatMaxAgeInMinutes(Node\Expr $expr): bool
    {
        if (!$expr instanceof Node\Expr\MethodCall) {
            return false;
        }

        if ($expr->name instanceof Node\Identifier
            && $expr->name->toString() === 'heartbeatMaxAgeInMinutes') {
            $firstArg = $expr->args[0] ?? null;
            if ($firstArg instanceof Node\Arg
                && $firstArg->value instanceof Node\Scalar\Int_
                && $firstArg->value->value === 2) {
                return true;
            }
        }

        return $this->hasHeartbeatMaxAgeInMinutes($expr->var);
    }
}
