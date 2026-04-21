<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorArrayArgument;

class HasRectorConfigWithSkipCheck extends AbstractHasRectorConfigCheck
{
    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorArrayArgument($this->commentCollector, 'withSkip', ['TablePropertyToTableAttributeRector']);
    }

    public function check(): CheckResult
    {
        if (!$this->composerPackageSatisfies('laravel/framework', '^13')) {
            return CheckResult::WARN;
        }

        return parent::check();
    }
}
