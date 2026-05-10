<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorArrayArgument;

class HasRectorConfigWithSkipCheck extends AbstractHasRectorConfigCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $rectorFile = base_path('rector.php');

        if (!file_exists($rectorFile)) {
            if ($dry) {
                return CheckResult::FAIL;
            }

            file_put_contents($rectorFile, "<?php\n\nuse Rector\\Config\\RectorConfig;\n\nreturn RectorConfig::configure();\n");
        }

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
            if ($dry) {
                return $always;
            }

            if (!str_contains((file_get_contents($rectorFile) ?: ''), 'withSkip(')) {
                $skipClasses = [
                    'CarbonToDateFacadeRector::class',
                    'AppToResolveRector::class',
                    'RedirectBackToBackHelperRector::class',
                    'RedirectRouteToToRouteHelperRector::class',
                    'NowFuncWithStartOfDayMethodCallToTodayFuncRector::class',
                    'EloquentOrderByToLatestOrOldestRector::class',
                ];

                if ($this->composerPackageSatisfies('laravel/framework', '^13')) {
                    $skipClasses[] = 'TablePropertyToTableAttributeRector::class';
                }

                if (file_exists(base_path('server.php'))) {
                    $skipClasses[] = 'ServerVariableToRequestFacadeRector::class';
                }

                $this->appendToRectorChain($rectorFile, '->withSkip(['.implode(', ', $skipClasses).'])');
            }

            return $this->fix(dry: true);
        }

        if ($this->composerPackageSatisfies('laravel/framework', '^13')) {
            $l13 = $this->runVisitorOnRector(
                new RectorVisitorArrayArgument($this->commentCollector, 'withSkip', [
                    'TablePropertyToTableAttributeRector',
                ]),
            );

            if ($l13 !== null) {
                // withSkip exists but missing L13 class — can't merge into existing call
                return $dry ? $l13 : CheckResult::FAIL;
            }
        }

        if (file_exists(base_path('server.php'))) {
            $server = $this->runVisitorOnRector(
                new RectorVisitorArrayArgument($this->commentCollector, 'withSkip', [
                    'ServerVariableToRequestFacadeRector',
                ]),
            );

            if ($server !== null) {
                return $dry ? $server : CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }

    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorArrayArgument($this->commentCollector, 'withSkip', []);
    }

    protected function fixCodeSnippet(): string
    {
        return '->withSkip([CarbonToDateFacadeRector::class, AppToResolveRector::class, RedirectBackToBackHelperRector::class, RedirectRouteToToRouteHelperRector::class, NowFuncWithStartOfDayMethodCallToTodayFuncRector::class, EloquentOrderByToLatestOrOldestRector::class])';
    }
}
