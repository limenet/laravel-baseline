<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorArrayArgument;

class HasRectorConfigWithRulesCheck extends AbstractHasRectorConfigCheck
{
    protected function makeVisitor(): AbstractRectorVisitor
    {
        // AddGenericReturnTypeToRelationsRector deliberately absent: it comes in
        // through LaravelSetList::LARAVEL_TYPE_DECLARATIONS, and listing it here
        // too makes Rector warn about the duplicate. See
        // DoesNotDuplicateRectorSetRulesCheck, which takes it back out.
        return new RectorVisitorArrayArgument($this->commentCollector, 'withRules', [
            'MinutesToSecondsInCacheRector',
            'UseForwardsCallsTraitRector',
        ]);
    }

    protected function fixCodeSnippet(): string
    {
        return '->withRules([MinutesToSecondsInCacheRector::class, UseForwardsCallsTraitRector::class])';
    }

    protected function fixImports(): array
    {
        return [
            'RectorLaravel\\Rector\\StaticCall\\MinutesToSecondsInCacheRector',
            'RectorLaravel\\Rector\\Class_\\UseForwardsCallsTraitRector',
        ];
    }
}
