<?php

namespace Limenet\LaravelBaseline\ServiceProvider;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class StaticCallVisitor extends NodeVisitorAbstract
{
    private bool $found = false;

    private bool $valid = false;

    public function __construct(
        private readonly string $className,
        private readonly string $methodName,
    ) {}

    public function enterNode(Node $node): null
    {
        if (!$node instanceof Node\Expr\StaticCall) {
            return null;
        }

        if (!$node->class instanceof Node\Name || $node->class->getLast() !== $this->className) {
            return null;
        }

        if (!$node->name instanceof Node\Identifier || $node->name->toString() !== $this->methodName) {
            return null;
        }

        $this->found = true;
        $firstArg = $node->args[0] ?? null;

        $this->valid = !(
            $firstArg instanceof Node\Arg
            && $firstArg->value instanceof Node\Expr\ConstFetch
            && strtolower($firstArg->value->name->toString()) === 'false'
        );

        return null;
    }

    public function wasFound(): bool
    {
        return $this->found;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }
}
