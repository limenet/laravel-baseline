<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorArrayArgument;

class HasRectorConfigWithSetsCheck extends AbstractHasRectorConfigCheck
{
    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorArrayArgument($this->commentCollector, 'withSets', ['LaravelBaselineSetList']);
    }
}
