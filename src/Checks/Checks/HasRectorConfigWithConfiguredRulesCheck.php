<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorConfiguredRules;

class HasRectorConfigWithConfiguredRulesCheck extends AbstractHasRectorConfigCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $rectorFile = base_path('rector.php');

        if (!file_exists($rectorFile)) {
            if ($dry) {
                return CheckResult::FAIL;
            }

            file_put_contents($rectorFile, "<?php\n\nuse Rector\\Config\\RectorConfig;\n\nreturn RectorConfig::configure();\n");
        }

        $result = $this->runVisitorOnRector($this->makeVisitor());

        if ($result === null) {
            return CheckResult::PASS;
        }

        if ($dry) {
            return $result;
        }

        $content = file_get_contents($rectorFile) ?: '';

        if (!str_contains($content, 'RouteActionCallableRector(')) {
            $this->appendToRectorChain(
                $rectorFile,
                "->withConfiguredRule(RouteActionCallableRector::class, [RouteActionCallableRector::NAMESPACE => 'App\\\\Http\\\\Controllers'])",
                $this->fixImports(),
            );
        }

        $content = file_get_contents($rectorFile) ?: '';

        if (!str_contains($content, 'WhereToWhereLikeRector(')) {
            $this->appendToRectorChain($rectorFile, '->withConfiguredRule(WhereToWhereLikeRector::class, [])');
        }

        return $this->fix(dry: true);
    }

    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorConfiguredRules($this->commentCollector, 'withConfiguredRule', [
            'RouteActionCallableRector',
            'WhereToWhereLikeRector',
        ]);
    }

    protected function fixCodeSnippet(): string
    {
        return "->withConfiguredRule(RouteActionCallableRector::class, [RouteActionCallableRector::NAMESPACE => 'App\\\\Http\\\\Controllers'])";
    }

    protected function fixImports(): array
    {
        return [
            'RectorLaravel\\Rector\\Route\\RouteActionCallableRector',
            'RectorLaravel\\Rector\\MethodCall\\WhereToWhereLikeRector',
        ];
    }
}
