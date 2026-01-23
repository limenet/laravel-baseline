<?php

namespace Limenet\LaravelBaseline\Adminer;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * AST visitor for extracting $middlewareGroups from HTTP Kernel.
 */
class KernelMiddlewareVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<int|string, mixed>|null
     */
    private ?array $middlewareGroups = null;

    /**
     * Get the extracted middleware groups.
     *
     * @return array<int|string, mixed>|null
     */
    public function getMiddlewareGroups(): ?array
    {
        return $this->middlewareGroups;
    }

    public function enterNode(Node $node): ?int
    {
        // Look for the $middlewareGroups property
        if ($node instanceof Node\Stmt\Property) {
            foreach ($node->props as $prop) {
                if ($prop->name->toString() === 'middlewareGroups' && $prop->default instanceof Node\Expr\Array_) {
                    $this->middlewareGroups = $this->parseArray($prop->default);
                }
            }
        }

        return null;
    }

    /**
     * Parse an array node into a PHP array.
     *
     * @return array<string|int, mixed>
     */
    private function parseArray(Node\Expr\Array_ $array): array
    {
        $result = [];

        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }

            $key = $this->parseKey($item->key);
            $value = $this->parseValue($item->value);

            if ($key !== null) {
                $result[$key] = $value;
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * Parse an array key node.
     */
    private function parseKey(?Node\Expr $key): string|int|null
    {
        if ($key === null) {
            return null;
        }

        if ($key instanceof Node\Scalar\String_) {
            return $key->value;
        }

        if ($key instanceof Node\Scalar\LNumber) {
            return $key->value;
        }

        return null;
    }

    /**
     * Parse a value node into a PHP value.
     */
    private function parseValue(Node\Expr $value): mixed
    {
        // String literal
        if ($value instanceof Node\Scalar\String_) {
            return $value->value;
        }

        // Class constant fetch (e.g., SomeMiddleware::class)
        if ($value instanceof Node\Expr\ClassConstFetch) {
            if ($value->class instanceof Node\Name && $value->name instanceof Node\Identifier && $value->name->toString() === 'class') {
                return $value->class->toString();
            }

            return null;
        }

        // Nested array
        if ($value instanceof Node\Expr\Array_) {
            return $this->parseArray($value);
        }

        return null;
    }
}
