<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorArrayArgument;

class HasRectorConfigWithRulesCheck extends AbstractHasRectorConfigCheck
{
    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorArrayArgument($this->commentCollector, 'withRules', ['AddGenericReturnTypeToRelationsRector']);
    }
}
