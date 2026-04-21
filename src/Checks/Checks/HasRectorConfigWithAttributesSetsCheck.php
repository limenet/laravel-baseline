<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorHasCall;

class HasRectorConfigWithAttributesSetsCheck extends AbstractHasRectorConfigCheck
{
    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorHasCall($this->commentCollector, 'withAttributesSets');
    }
}
