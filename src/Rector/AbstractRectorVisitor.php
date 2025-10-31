<?php

namespace Limenet\LaravelBaseline\Rector;

use Limenet\LaravelBaseline\Checks\Checker;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

abstract class AbstractRectorVisitor extends NodeVisitorAbstract
{
    private bool $found = false;

    /**
     * @param  string[]  $payload
     */
    public function __construct(
        protected readonly Checker $checker,
        public readonly string $methodName,
        public readonly array $payload = [],
    ) {}

    abstract public function getErrorMessage(): string;

    public function wasFound(): bool
    {
        return $this->found;
    }

    public function enterNode(Node $node)
    {
        if (!$node instanceof Node\Expr\MethodCall) {
            return null;
        }

        if (!$node->name instanceof Node\Identifier || $node->name->toString() !== $this->methodName) {
            return null;
        }

        $this->found = $this->checkMethod($node);

        return null;
    }

    abstract protected function checkMethod(Node\Expr\MethodCall $node): bool;
}
