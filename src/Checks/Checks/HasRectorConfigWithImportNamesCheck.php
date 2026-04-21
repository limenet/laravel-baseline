<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorNamedArgument;

class HasRectorConfigWithImportNamesCheck extends AbstractHasRectorConfigCheck
{
    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorNamedArgument($this->commentCollector, 'withImportNames', ['!importShortClasses']);
    }
}
