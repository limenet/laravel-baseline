<?php

namespace Limenet\LaravelBaseline\Rector;

use PhpParser\Node;

class RectorVisitorClassFetch extends AbstractRectorVisitor
{
    public function getErrorMessage(): string
    {
        return sprintf(
            'Rector configuration incomplete: Missing or incorrect call to %s() in rector.php - Expected class constant for one of: %s',
            $this->methodName,
            implode(', ', $this->payload),
        );
    }

    protected function checkMethod(Node\Expr\MethodCall $node): bool
    {
        foreach ($node->args as $arg) {
            if ($arg->value instanceof Node\Expr\ClassConstFetch
                && $arg->value->class instanceof Node\Name
                && in_array($arg->value->class->toString(), $this->payload, true)) {
                return true;
            }
        }

        return false;
    }
}
