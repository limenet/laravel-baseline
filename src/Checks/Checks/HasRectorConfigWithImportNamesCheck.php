<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorNamedArgument;

class HasRectorConfigWithImportNamesCheck extends AbstractHasRectorConfigCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorNamedArgument($this->commentCollector, 'withImportNames', ['!importShortClasses']);
    }

    protected function fixCodeSnippet(): string
    {
        return '->withImportNames(importShortClasses: false)';
    }
}
