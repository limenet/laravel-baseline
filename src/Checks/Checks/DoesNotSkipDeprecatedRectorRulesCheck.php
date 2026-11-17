<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

class DoesNotSkipDeprecatedRectorRulesCheck extends AbstractRemovesFromRectorConfigCheck
{
    protected function methodName(): string
    {
        return 'withSkip';
    }

    protected function classShortNames(): array
    {
        return ['StringToClassConstantRector'];
    }

    protected function removalComment(): string
    {
        return 'Remove StringToClassConstantRector from withSkip() in rector.php: Rector 2.8 deprecated it, and driftingly/rector-laravel 2.7 replaced it in the legacy event sets with EventStringToClassConstantRector, which only rewrites event dispatcher calls. The skip guards against nothing any more, makes Rector print a deprecation warning on every run, and breaks the config once Rector deletes the class';
    }
}
