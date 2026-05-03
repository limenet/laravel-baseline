<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorConfiguredRules;

class HasRectorConfigWithConfiguredRulesCheck extends AbstractHasRectorConfigCheck
{
    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorConfiguredRules($this->commentCollector, 'withConfiguredRule', [
            'RouteActionCallableRector',
            'WhereToWhereLikeRector',
        ]);
    }
}
