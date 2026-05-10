<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorArrayArgument;

class HasRectorConfigWithRulesCheck extends AbstractHasRectorConfigCheck
{
    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorArrayArgument($this->commentCollector, 'withRules', [
            'AddGenericReturnTypeToRelationsRector',
            'MinutesToSecondsInCacheRector',
            'UseForwardsCallsTraitRector',
        ]);
    }

    protected function fixCodeSnippet(): string
    {
        return '->withRules([AddGenericReturnTypeToRelationsRector::class, MinutesToSecondsInCacheRector::class, UseForwardsCallsTraitRector::class])';
    }

    protected function fixImports(): array
    {
        return [
            'RectorLaravel\\Rector\\ClassMethod\\AddGenericReturnTypeToRelationsRector',
            'RectorLaravel\\Rector\\MethodCall\\MinutesToSecondsInCacheRector',
            'RectorLaravel\\Rector\\Class_\\UseForwardsCallsTraitRector',
        ];
    }
}
