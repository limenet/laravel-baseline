<?php

namespace Limenet\LaravelBaseline\Health;

use PhpParser\Node;

class HealthScheduleCheckHeartbeatVisitor extends AbstractHealthChecksVisitor
{
    private bool $found = false;

    public function __construct(
        private readonly string $checkClassName = 'ScheduleCheck',
        private readonly int $maxAgeInMinutes = 2,
    ) {}

    public function wasFound(): bool
    {
        return $this->found;
    }

    protected function processChecksArray(Node\Expr\Array_ $array): void
    {
        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }

            if ($this->isTargetCheckWithHeartbeat($item->value)) {
                $this->found = true;

                return;
            }
        }
    }

    private function isTargetCheckWithHeartbeat(Node\Expr $expr): bool
    {
        return $this->extractRootClassName($expr) === $this->checkClassName
            && $this->hasHeartbeatCall($expr);
    }

    private function hasHeartbeatCall(Node\Expr $expr): bool
    {
        if (!$expr instanceof Node\Expr\MethodCall) {
            return false;
        }

        if (
            $expr->name instanceof Node\Identifier
            && $expr->name->toString() === 'heartbeatMaxAgeInMinutes'
        ) {
            $arg = $expr->args[0] ?? null;

            if (
                $arg instanceof Node\Arg
                && $arg->value instanceof Node\Scalar\Int_
                && $arg->value->value === $this->maxAgeInMinutes
            ) {
                return true;
            }
        }

        return $this->hasHeartbeatCall($expr->var);
    }
}
