<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorArrayArgument;

class HasRectorConfigWithSkipCheck extends AbstractHasRectorConfigCheck
{
    public function check(): CheckResult
    {
        $always = $this->runVisitorOnRector(
            new RectorVisitorArrayArgument($this->commentCollector, 'withSkip', [
                'CarbonToDateFacadeRector',
                'AppToResolveRector',
                'RedirectBackToBackHelperRector',
                'RedirectRouteToToRouteHelperRector',
                'NowFuncWithStartOfDayMethodCallToTodayFuncRector',
                'EloquentOrderByToLatestOrOldestRector',
            ]),
        );
        if ($always !== null) {
            return $always;
        }

        if ($this->composerPackageSatisfies('laravel/framework', '^13')) {
            $l13 = $this->runVisitorOnRector(
                new RectorVisitorArrayArgument($this->commentCollector, 'withSkip', [
                    'TablePropertyToTableAttributeRector',
                ]),
            );
            if ($l13 !== null) {
                return $l13;
            }
        }

        if (file_exists(base_path('server.php'))) {
            $server = $this->runVisitorOnRector(
                new RectorVisitorArrayArgument($this->commentCollector, 'withSkip', [
                    'ServerVariableToRequestFacadeRector',
                ]),
            );
            if ($server !== null) {
                return $server;
            }
        }

        return CheckResult::PASS;
    }

    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorArrayArgument($this->commentCollector, 'withSkip', []);
    }
}
