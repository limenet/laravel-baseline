<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Project\Profile;

class DoesNotUseDeprecatedRectorPreparedSetsCheck extends AbstractRemovesFromRectorConfigCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    protected function methodName(): string
    {
        return 'withPreparedSets';
    }

    protected function classShortNames(): array
    {
        return [];
    }

    protected function argumentNames(): array
    {
        return ['instanceOf', 'earlyReturn', 'if', 'naming'];
    }

    protected function removalComment(): string
    {
        return 'Remove the instanceOf, earlyReturn, if and naming arguments from withPreparedSets() in rector.php: Rector 2.9 no longer applies them and prints a deprecation warning on every run. Their rules moved into the codeQuality and codingStyle sets, which hasRectorConfigWithPreparedSets() already requires';
    }
}
