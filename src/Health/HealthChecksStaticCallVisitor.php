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

            // CacheCheck::new() is a StaticCall with name 'new'
            if (
                $item->value instanceof Node\Expr\StaticCall
                && $item->value->class instanceof Node\Name
                && $item->value->name instanceof Node\Identifier
                && $item->value->name->toString() === 'new'
            ) {
                $classNames[] = $item->value->class->getLast();
            }
        }

        return $classNames;
    }
}
