<?php

namespace Limenet\LaravelBaseline\Health;

use PhpParser\Node;

class QueueCheckHorizonQueuesVisitor extends AbstractHealthChecksVisitor
{
    /** @var list<string>|null null = QueueCheck not found or no onQueue call */
    private ?array $queues = null;

    protected function processChecksArray(Node\Expr\Array_ $array): void
    {
        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }

            if ($this->extractRootClassName($item->value) !== 'QueueCheck') {
                continue;
            }

            $onQueueQueues = $this->extractOnQueueQueues($item->value);

            if ($onQueueQueues !== null) {
                $this->queues = $onQueueQueues;
            }

            return;
        }
    }

    /** @return list<string>|null null means QueueCheck not found or onQueue not called */
    public function getQueues(): ?array
    {
        return $this->queues;
    }

    /** @return list<string>|null */
    private function extractOnQueueQueues(Node\Expr $expr): ?array
    {
        if (!$expr instanceof Node\Expr\MethodCall) {
            return null;
        }

        if (
            $expr->name instanceof Node\Identifier
            && $expr->name->toString() === 'onQueue'
        ) {
            $arg = $expr->args[0] ?? null;

            if (!$arg instanceof Node\Arg) {
                return null;
            }

            // ->onQueue('default')
            if ($arg->value instanceof Node\Scalar\String_) {
                return [$arg->value->value];
            }

            // ->onQueue(['default', 'notifications'])
            if ($arg->value instanceof Node\Expr\Array_) {
                $queues = [];

                foreach ($arg->value->items as $item) {
                    if ($item !== null && $item->value instanceof Node\Scalar\String_) {
                        $queues[] = $item->value->value;
                    }
                }

                return $queues;
            }

            return null;
        }

        return $this->extractOnQueueQueues($expr->var);
    }
}
