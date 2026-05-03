<?php

namespace Limenet\LaravelBaseline\Rector;

use PhpParser\Node;

class RectorVisitorArrayClassConstant extends AbstractRectorVisitor
{
    public function getErrorMessage(): string
    {
        return sprintf(
            'Rector configuration incomplete: Missing or incorrect call to %s() in rector.php - Expected array containing: %s',
            $this->methodName,
            implode(', ', $this->payload),
        );
    }

    protected function checkMethod(Node\Expr\MethodCall $node): bool
    {
        $args = [];

        $firstArg = $node->args[0] ?? null;
        if (!$firstArg instanceof Node\Arg) {
            return false;
        }

        $arg0 = $firstArg->value;

        if ($arg0 instanceof Node\Expr\Array_) {
            foreach ($arg0->items as $arg) {
                if ($arg !== null
                    && $arg->value instanceof Node\Expr\ClassConstFetch
                    && $arg->value->class instanceof Node\Name
                    && $arg->value->name instanceof Node\Identifier) {
                    $args[] = $arg->value->class->toString().'::'.$arg->value->name->toString();
                }
            }
        }

        foreach ($this->payload as $required) {
            if (!in_array($required, $args, true)) {
                return false;
            }
        }

        return true;
    }
}
