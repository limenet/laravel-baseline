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
            $expected = 'true';

            if (str_starts_with($name, '!')) {
                $name = substr($name, 1);
                $expected = 'false';
            }

            if (($args[$name] ?? null) !== $expected) {
                $errors++;
            }
        }

        return $errors === 0;
    }
}
