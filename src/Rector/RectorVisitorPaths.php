<?php

namespace Limenet\LaravelBaseline\Rector;

use PhpParser\Node;

class RectorVisitorPaths extends AbstractRectorVisitor
{
    public function getErrorMessage(): string
    {
        return sprintf(
            'Rector configuration incomplete: Missing or incorrect call to %s() in rector.php. Expected paths: %s',
            $this->methodName,
            implode(', ', $this->payload),
        );
    }

    protected function checkMethod(Node\Expr\MethodCall $node): bool
    {
        $paths = [];

        if (!($node->args[0] ?? null) instanceof Node\Arg) {
            return false;
        }

        $arg0 = $node->args[0]->value;

        if ($arg0 instanceof Node\Expr\Array_) {
            foreach ($arg0->items as $item) {
                if ($item->value instanceof Node\Expr\BinaryOp\Concat) {
                    // Handle __DIR__.'/path' pattern
                    $concat = $item->value;
                    if ($concat->left instanceof Node\Scalar\MagicConst\Dir
                        && $concat->right instanceof Node\Scalar\String_
                    ) {
                        // Extract path without leading slash (e.g., '/app' -> 'app')
                        $paths[] = ltrim($concat->right->value, '/');
                    }
                }
            }
        }

        $errors = 0;

        foreach ($this->payload as $requiredPath) {
            if (!in_array($requiredPath, $paths, true)) {
                $errors++;
            }
        }

        return $errors === 0;
    }
}
