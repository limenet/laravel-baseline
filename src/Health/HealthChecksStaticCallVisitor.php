<?php

namespace Limenet\LaravelBaseline\Health;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class HealthChecksStaticCallVisitor extends NodeVisitorAbstract
{
    private bool $found = false;

    /** @var list<string> */
    private array $missingClasses = [];

    /**
     * @param  list<string>  $requiredClassNames  Short class names, e.g. 'CacheCheck'
     */
    public function __construct(private readonly array $requiredClassNames) {}

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
        $foundClasses = $this->extractClassNamesFromArg($firstArg instanceof Node\Arg ? $firstArg : null);
        $this->missingClasses = array_values(array_diff($this->requiredClassNames, $foundClasses));
        $this->found = $this->missingClasses === [];

        return null;
    }

    public function wasFound(): bool
    {
        return $this->found;
    }

    /** @return list<string> */
    public function getMissingClasses(): array
    {
        return $this->missingClasses;
    }

    /** @return list<string> */
    private function extractClassNamesFromArg(?Node\Arg $arg): array
    {
        if ($arg === null || !$arg->value instanceof Node\Expr\Array_) {
            return [];
        }

        $classNames = [];

        foreach ($arg->value->items as $item) {
            if ($item === null) {
                continue;
            }

            $className = $this->extractClassNameFromExpr($item->value);
            if ($className !== null) {
                $classNames[] = $className;
            }
        }

        return $classNames;
    }

    private function extractClassNameFromExpr(Node\Expr $expr): ?string
    {
        // CacheCheck::new() is a StaticCall with name 'new'
        if (
            $expr instanceof Node\Expr\StaticCall
            && $expr->class instanceof Node\Name
            && $expr->name instanceof Node\Identifier
            && $expr->name->toString() === 'new'
        ) {
            return $expr->class->getLast();
        }

        // CpuLoadCheck::new()->failWhen...() — walk up the method call chain
        if ($expr instanceof Node\Expr\MethodCall) {
            return $this->extractClassNameFromExpr($expr->var);
        }

        // $cond ? EnvironmentCheck::new()->... : EnvironmentCheck::new() — use either branch
        if ($expr instanceof Node\Expr\Ternary) {
            return $this->extractClassNameFromExpr($expr->if ?? $expr->else) ?? $this->extractClassNameFromExpr($expr->else);
        }

        return null;
    }
}
