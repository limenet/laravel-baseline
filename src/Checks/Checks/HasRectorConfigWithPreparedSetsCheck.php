<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorNamedArgument;

class HasRectorConfigWithPreparedSetsCheck extends AbstractHasRectorConfigCheck
{
    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorNamedArgument($this->commentCollector, 'withPreparedSets', ['deadCode', 'codeQuality', 'codingStyle', 'typeDeclarations', 'privatization', 'instanceOf', 'earlyReturn']);
    }
}
