<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

class ModelShouldBeStrictCheck extends AbstractServiceProviderStaticCallCheck
{
    protected function staticClassName(): string
    {
        return 'Model';
    }

    protected function staticMethodName(): string
    {
        return 'shouldBeStrict';
    }

    protected function missingCallComment(): string
    {
        return 'Missing Model::shouldBeStrict() call in AppServiceProvider';
    }

    protected function falseLiteralComment(): string
    {
        return 'Do not pass false to Model::shouldBeStrict(); use true, no argument, or a dynamic expression';
    }

    protected function fixStatement(): string
    {
        return 'Model::shouldBeStrict(! app()->isProduction());';
    }

    protected function fixImports(): array
    {
        return ['Illuminate\\Database\\Eloquent\\Model'];
    }
}
