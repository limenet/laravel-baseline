<?php

namespace Limenet\LaravelBaseline\Health;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class HealthConfigVisitor extends NodeVisitorAbstract
{
    private bool $hasValidResultStore = false;

    private bool $notificationsDisabled = false;

    public function enterNode(Node $node): null
    {
        if (!$node instanceof Node\Stmt\Return_ || !$node->expr instanceof Node\Expr\Array_) {
            return null;
        }

        foreach ($node->expr->items as $item) {
            if ($item === null || !$item->key instanceof Node\Scalar\String_) {
                continue;
            }

            if ($item->key->value === 'result_stores' && $item->value instanceof Node\Expr\Array_) {
                $this->hasValidResultStore = $this->checkResultStores($item->value);
            }

            if ($item->key->value === 'notifications' && $item->value instanceof Node\Expr\Array_) {
                $this->notificationsDisabled = $this->checkNotificationsDisabled($item->value);
            }
        }

        return null;
    }

    public function hasValidResultStore(): bool
    {
        return $this->hasValidResultStore;
    }

    public function notificationsDisabled(): bool
    {
        return $this->notificationsDisabled;
    }

    private function checkResultStores(Node\Expr\Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }

            if (!$item->key instanceof Node\Expr\ClassConstFetch) {
                continue;
            }

            if (!$item->key->name instanceof Node\Identifier || $item->key->name->toString() !== 'class') {
                continue;
            }

            if (!$item->key->class instanceof Node\Name) {
                continue;
            }

            if ($item->key->class->getLast() !== 'JsonFileHealthResultStore') {
                continue;
            }

            if (!$item->value instanceof Node\Expr\Array_) {
                continue;
            }

            $hasDisk = false;
            $hasPath = false;

            foreach ($item->value->items as $configItem) {
                if ($configItem === null || !$configItem->key instanceof Node\Scalar\String_ || !$configItem->value instanceof Node\Scalar\String_) {
                    continue;
                }

                if ($configItem->key->value === 'disk' && $configItem->value->value === 's3_health') {
                    $hasDisk = true;
                }

                if ($configItem->key->value === 'path' && $configItem->value->value === 'health.json') {
                    $hasPath = true;
                }
            }

            if ($hasDisk && $hasPath) {
                return true;
            }
        }

        return false;
    }

    private function checkNotificationsDisabled(Node\Expr\Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if ($item === null || !$item->key instanceof Node\Scalar\String_) {
                continue;
            }

            if ($item->key->value !== 'enabled') {
                continue;
            }

            return $item->value instanceof Node\Expr\ConstFetch
                && strtolower($item->value->name->toString()) === 'false';
        }

        return false;
    }
}
