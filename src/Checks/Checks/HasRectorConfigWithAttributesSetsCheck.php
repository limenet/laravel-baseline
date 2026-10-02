<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorHasCall;

class HasRectorConfigWithAttributesSetsCheck extends AbstractHasRectorConfigCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorHasCall($this->commentCollector, 'withAttributesSets');
    }

    protected function fixCodeSnippet(): string
    {
        return '->withAttributesSets()';
    }
}
