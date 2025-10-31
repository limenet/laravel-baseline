<?php

namespace Limenet\LaravelBaseline\Rector;

use PhpParser\Node;

class RectorVisitorHasCall extends AbstractRectorVisitor
{
    public function getErrorMessage(): string
    {
        return sprintf(
            'Rector configuration incomplete: Missing call to %s() in rector.php',
            $this->methodName,
        );
    }

    protected function checkMethod(Node\Expr\MethodCall $node): bool
    {
        return true;
    }
}
