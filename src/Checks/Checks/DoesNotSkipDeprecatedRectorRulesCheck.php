<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Project\Profile;

class DoesNotSkipDeprecatedRectorRulesCheck extends AbstractRemovesFromRectorConfigCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    protected function methodName(): string
    {
        return 'withSkip';
    }

    protected function classShortNames(): array
    {
        return ['StringToClassConstantRector', 'FlipTypeControlToUseExclusiveTypeRector'];
    }

    protected function removalComment(): string
    {
        return 'Remove StringToClassConstantRector and FlipTypeControlToUseExclusiveTypeRector from withSkip() in rector.php: Rector 2.9 deleted both classes, so naming them in withSkip() breaks the config. StringToClassConstantRector was only skipped because rector-laravel\'s legacy event sets rewrote any matching string, and driftingly/rector-laravel 2.7 replaced it there with EventStringToClassConstantRector, which only rewrites event dispatcher calls';
    }
}
