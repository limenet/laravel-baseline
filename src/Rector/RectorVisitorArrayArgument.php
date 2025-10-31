<?php

namespace Limenet\LaravelBaseline\Rector;

use PhpParser\Node;

class RectorVisitorArrayArgument extends AbstractRectorVisitor
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

        $arg0 = $node->args[0]->value;

        if ($arg0 instanceof Node\Expr\Array_) {
            foreach ($arg0->items as $arg) {
                if ($arg->value instanceof Node\Expr\ClassConstFetch) {
                    $args[] = $arg->value->class->toString();
                }
            }
        }

        $errors = 0;

        foreach ($this->payload as $name) {
            if (!in_array($name, $args, true)) {
                $errors++;
            }
        }

        return $errors === 0;
    }
}
