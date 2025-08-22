<?php

namespace Limenet\LaravelBaseline\Rector;

use PhpParser\Node;

class RectorVisitorNamedArgument extends AbstractRectorVisitor
{
    protected function checkMethod(Node\Expr\MethodCall $node): bool
    {
        $args = [];
        foreach ($node->args as $arg) {
            if ($arg->name) {
                $args[$arg->name->toString()] = $arg->value instanceof Node\Expr\ConstFetch
                    ? $arg->value->name->toString()
                    : null;
            }
        }

        $errors = 0;
        foreach ($this->payload as $name) {
            if (($args[$name] ?? null) !== 'true') {
                $errors++;
            }

        }

        return $errors === 0;
    }
}
