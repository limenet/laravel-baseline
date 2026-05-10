<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorClassFetch;

class HasRectorConfigWithSetProvidersCheck extends AbstractHasRectorConfigCheck
{
    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorClassFetch($this->commentCollector, 'withSetProviders', ['LaravelSetProvider']);
    }

    protected function fixCodeSnippet(): string
    {
        return '->withSetProviders([LaravelSetProvider::class])';
    }

    protected function fixImports(): array
    {
        return ['RectorLaravel\\Set\\LaravelSetProvider'];
    }
}
