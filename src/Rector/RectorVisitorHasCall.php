<?php

namespace Limenet\LaravelBaseline\Rector;

use PhpParser\Node;

class RectorVisitorHasCall extends AbstractRectorVisitor
{
    protected function checkMethod(Node\Expr\MethodCall $node): bool
    {
        return true;
    }
}
