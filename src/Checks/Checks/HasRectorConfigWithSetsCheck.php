<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorArrayClassConstant;

class HasRectorConfigWithSetsCheck extends AbstractHasRectorConfigCheck
{
    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorArrayClassConstant($this->commentCollector, 'withSets', [
            'LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS',
            'LaravelSetList::LARAVEL_ARRAYACCESS_TO_METHOD_CALL',
            'LaravelSetList::LARAVEL_ARRAY_STR_FUNCTION_TO_STATIC_CALL',
            'LaravelSetList::LARAVEL_CODE_QUALITY',
            'LaravelSetList::LARAVEL_COLLECTION',
            'LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME',
            'LaravelSetList::LARAVEL_ELOQUENT_MAGIC_METHOD_TO_QUERY_BUILDER',
            'LaravelSetList::LARAVEL_FACADE_ALIASES_TO_FULL_NAMES',
            'LaravelSetList::LARAVEL_LEGACY_FACTORIES_TO_CLASSES',
            'LaravelSetList::LARAVEL_TESTING',
            'LaravelSetList::LARAVEL_TYPE_DECLARATIONS',
        ]);
    }
}
