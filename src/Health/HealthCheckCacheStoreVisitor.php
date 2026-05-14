<?php

namespace Limenet\LaravelBaseline\Health;

use PhpParser\Node;

class HealthCheckCacheStoreVisitor extends AbstractHealthChecksVisitor
{
    private bool $found = false;

    public function __construct(
        private readonly string $checkClassName,
        private readonly string $cacheStoreName,
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

            if ($this->isTargetCheckWithCacheStore($item->value)) {
                $this->found = true;

                return;
            }
        }
    }

    private function isTargetCheckWithCacheStore(Node\Expr $expr): bool
    {
        return $this->extractRootClassName($expr) === $this->checkClassName
            && $this->hasCacheStoreCall($expr);
    }

    private function hasCacheStoreCall(Node\Expr $expr): bool
    {
        if (!$expr instanceof Node\Expr\MethodCall) {
            return false;
        }

        if (
            $expr->name instanceof Node\Identifier
            && $expr->name->toString() === 'useCacheStore'
        ) {
            $arg = $expr->args[0] ?? null;

            if (
                $arg instanceof Node\Arg
                && $arg->value instanceof Node\Scalar\String_
                && $arg->value->value === $this->cacheStoreName
            ) {
                return true;
            }
        }

        return $this->hasCacheStoreCall($expr->var);
    }
}
