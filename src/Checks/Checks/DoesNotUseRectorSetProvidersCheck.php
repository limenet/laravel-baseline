<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

class DoesNotUseRectorSetProvidersCheck extends AbstractRemovesFromRectorConfigCheck
{
    protected function methodName(): string
    {
        return 'withSetProviders';
    }

    protected function classShortNames(): array
    {
        return ['LaravelSetProvider'];
    }

    protected function removalComment(): string
    {
        return 'Remove LaravelSetProvider from withSetProviders() in rector.php: driftingly/rector-laravel 2.6.0 deleted RectorLaravel\Set\LaravelSetProvider, so Rector aborts with "Set provider ... must implement SetProviderInterface" before doing any work. The rules it provided are version-bonded into LaravelSetList::COMPOSER_BASED, which withComposerBased(laravel: true) already loads';
    }
}
