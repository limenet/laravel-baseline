<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorArrayClassConstant;

class HasRectorConfigWithPestSetCheck extends AbstractHasRectorConfigCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        if (!$this->composerPackageSatisfies('pestphp/pest', '>=5.0') || !$this->checkComposerPackages('rector/rector')) {
            return CheckResult::WARN;
        }

        return parent::fix($dry);
    }

    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorArrayClassConstant($this->commentCollector, 'withSets', ['PestSetList::CODING_STYLE']);
    }

    protected function fixCodeSnippet(): string
    {
        return '->withSets([PestSetList::CODING_STYLE])';
    }

    protected function fixImports(): array
    {
        return ['Pest\\Rector\\Set\\PestSetList'];
    }
}
