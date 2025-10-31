<?php

namespace Limenet\LaravelBaseline\Rector;

use PhpParser\Node;

class RectorVisitorNamedArgument extends AbstractRectorVisitor
{
    public function getErrorMessage(): string
    {
        $expectedArgs = [];
        foreach ($this->payload as $name) {
            if (str_starts_with($name, '!')) {
                $expectedArgs[] = substr($name, 1).': false';
            } else {
                $expectedArgs[] = $name.': true';
            }
        }

        return sprintf(
            'Rector configuration incomplete: Missing or incorrect call to %s() in rector.php - Expected named arguments: %s',
            $this->methodName,
            implode(', ', $expectedArgs),
        );
    }

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
