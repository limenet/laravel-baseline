<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

class DoesNotDuplicateRectorSetRulesCheck extends AbstractRemovesFromRectorConfigCheck
{
    protected function methodName(): string
    {
        return 'withRules';
    }

    protected function classShortNames(): array
    {
        return ['AddGenericReturnTypeToRelationsRector'];
    }

    protected function removalComment(): string
    {
        return 'Remove AddGenericReturnTypeToRelationsRector from withRules() in rector.php: it already ships in LaravelSetList::LARAVEL_TYPE_DECLARATIONS, which hasRectorConfigWithSets() mandates, and Rector 2.6 warns about rules registered in both a set and withRules()';
    }
}
