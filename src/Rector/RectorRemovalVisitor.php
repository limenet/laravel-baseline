<?php

namespace Limenet\LaravelBaseline\Rector;

use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * Strips class references out of a fluent RectorConfig call — the
 * `LaravelSetProvider::class` argument of `->withSetProviders(...)`, one entry
 * of the `->withRules([...])` array — and drops the call itself once nothing is
 * left to pass. A call that was its own statement (`$config->withX(…);`) leaves
 * a bare `$config;` behind, which goes too.
 *
 * Both argument shapes are handled: a first argument that is an array is
 * filtered item by item, anything else is filtered argument by argument.
 */
class RectorRemovalVisitor extends NodeVisitorAbstract
{
    private bool $removed = false;

    /** @var list<string> */
    private array $dissolvedVariables = [];

    /**
     * @param  list<string>  $classShortNames
     */
    public function __construct(
        private readonly string $methodName,
        private readonly array $classShortNames,
    ) {}

    public function hasRemoved(): bool
    {
        return $this->removed;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Expression
            && $node->expr instanceof Node\Expr\Variable
            && is_string($node->expr->name)
            && in_array($node->expr->name, $this->dissolvedVariables, true)) {
            return NodeVisitor::REMOVE_NODE;
        }

        if (!$node instanceof Node\Expr\MethodCall) {
            return null;
        }

        if (!$node->name instanceof Node\Identifier || $node->name->toString() !== $this->methodName) {
            return null;
        }

        $firstArg = $node->args[0] ?? null;

        if ($firstArg instanceof Node\Arg && $firstArg->value instanceof Node\Expr\Array_) {
            return $this->filterArray($node, $firstArg->value);
        }

        return $this->filterArguments($node);
    }

    private function filterArray(Node\Expr\MethodCall $node, Node\Expr\Array_ $array): ?Node
    {
        $kept = array_values(array_filter(
            $array->items,
            fn (Node\ArrayItem $item): bool => !$this->matches($item->value) && !$this->matches($item->key),
        ));

        if (count($kept) === count($array->items)) {
            return null;
        }

        $this->removed = true;

        if ($kept === []) {
            return $this->dissolve($node);
        }

        $array->items = $kept;

        return null;
    }

    private function filterArguments(Node\Expr\MethodCall $node): ?Node
    {
        $kept = array_values(array_filter(
            $node->args,
            fn (Node\Arg|Node\VariadicPlaceholder $arg): bool => !$arg instanceof Node\Arg || !$this->matches($arg->value),
        ));

        if (count($kept) === count($node->args)) {
            return null;
        }

        $this->removed = true;

        if ($kept === []) {
            return $this->dissolve($node);
        }

        $node->args = $kept;

        return null;
    }

    /**
     * Replace the call with whatever it was called on, remembering the variable
     * so a statement that is now nothing but that variable can be dropped.
     */
    private function dissolve(Node\Expr\MethodCall $node): Node\Expr
    {
        if ($node->var instanceof Node\Expr\Variable && is_string($node->var->name)) {
            $this->dissolvedVariables[] = $node->var->name;
        }

        return $node->var;
    }

    private function matches(?Node $node): bool
    {
        return $node instanceof Node\Expr\ClassConstFetch
            && $node->class instanceof Node\Name
            && in_array($node->class->getLast(), $this->classShortNames, true);
    }
}
