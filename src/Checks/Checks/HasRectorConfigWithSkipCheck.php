<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use Limenet\LaravelBaseline\Rector\RectorVisitorArrayArgument;

class HasRectorConfigWithSkipCheck extends AbstractHasRectorConfigCheck
{
    /**
     * Rules every project skips, whatever it has installed.
     *
     * @var list<string>
     */
    private const ALWAYS = [
        'CarbonToDateFacadeRector',
        'AppToResolveRector',
        'RedirectBackToBackHelperRector',
        'RedirectRouteToToRouteHelperRector',
        'NowFuncWithStartOfDayMethodCallToTodayFuncRector',
        'EloquentOrderByToLatestOrOldestRector',
        // Maps the Laravel 5.2-era string events context-free, so any matching
        // string literal is rewritten — 'auth.login' as a view name becomes
        // Illuminate\Auth\Events\Login::class and the login page stops working.
        'StringToClassConstantRector',
        // Shipped in LARAVEL_TYPE_DECLARATIONS from rector-laravel 2.6, and a
        // downgrade wherever it lands: it rewrites `Builder<$this>` — which
        // Larastan reads as the more precise generic — to `Builder<static>`.
        'AddGenericBuilderToScopesRector',
        // Rewrites the classic getFooAttribute()/setFooAttribute() accessors
        // into a single Attribute-returning method. A mechanical style change
        // on code that works, and it loses the docblocks and the separate
        // get/set seams the surrounding code may rely on.
        'MigrateToSimplifiedAttributeRector',
    ];

    /** @var array<string, string> */
    private const IMPORTS = [
        'AddGenericBuilderToScopesRector' => 'RectorLaravel\\Rector\\ClassMethod\\AddGenericBuilderToScopesRector',
        'AppToResolveRector' => 'RectorLaravel\\Rector\\FuncCall\\AppToResolveRector',
        'CarbonToDateFacadeRector' => 'RectorLaravel\\Rector\\StaticCall\\CarbonToDateFacadeRector',
        'EloquentOrderByToLatestOrOldestRector' => 'RectorLaravel\\Rector\\MethodCall\\EloquentOrderByToLatestOrOldestRector',
        'MigrateToSimplifiedAttributeRector' => 'RectorLaravel\\Rector\\ClassMethod\\MigrateToSimplifiedAttributeRector',
        'NowFuncWithStartOfDayMethodCallToTodayFuncRector' => 'RectorLaravel\\Rector\\FuncCall\\NowFuncWithStartOfDayMethodCallToTodayFuncRector',
        'RedirectBackToBackHelperRector' => 'RectorLaravel\\Rector\\MethodCall\\RedirectBackToBackHelperRector',
        'RedirectRouteToToRouteHelperRector' => 'RectorLaravel\\Rector\\MethodCall\\RedirectRouteToToRouteHelperRector',
        'ServerVariableToRequestFacadeRector' => 'RectorLaravel\\Rector\\ArrayDimFetch\\ServerVariableToRequestFacadeRector',
        'StringToClassConstantRector' => 'Rector\\Transform\\Rector\\String_\\StringToClassConstantRector',
        'TablePropertyToTableAttributeRector' => 'RectorLaravel\\Rector\\Class_\\TablePropertyToTableAttributeRector',
    ];

    public function fix(bool $dry = false): CheckResult
    {
        $rectorFile = base_path('rector.php');

        if (!file_exists($rectorFile)) {
            if ($dry) {
                return CheckResult::FAIL;
            }

            file_put_contents($rectorFile, "<?php\n\nuse Rector\\Config\\RectorConfig;\n\nreturn RectorConfig::configure();\n");
        }

        $required = $this->requiredSkips();

        $result = $this->runVisitorOnRector(
            new RectorVisitorArrayArgument($this->commentCollector, 'withSkip', $required),
        );

        if ($result === null) {
            return CheckResult::PASS;
        }

        if ($dry) {
            return $result;
        }

        if (str_contains((string) (file_get_contents($rectorFile) ?: ''), 'withSkip(')) {
            $this->mergeIntoArrayArgument($rectorFile, 'withSkip', $this->importsFor($required));
        } else {
            $this->appendToRectorChain($rectorFile, $this->skipSnippet($required), $this->fixImports());
        }

        return $this->fix(dry: true);
    }

    protected function makeVisitor(): AbstractRectorVisitor
    {
        return new RectorVisitorArrayArgument($this->commentCollector, 'withSkip', $this->requiredSkips());
    }

    protected function fixCodeSnippet(): string
    {
        return $this->skipSnippet($this->requiredSkips());
    }

    protected function fixImports(): array
    {
        return array_values($this->importsFor($this->requiredSkips()));
    }

    /**
     * @param  list<string>  $classes
     * @return array<string, string> short class name => fully-qualified name
     */
    private function importsFor(array $classes): array
    {
        return array_intersect_key(self::IMPORTS, array_flip($classes));
    }

    /**
     * @return list<string>
     */
    private function requiredSkips(): array
    {
        $classes = self::ALWAYS;

        if ($this->composerPackageSatisfies('laravel/framework', '^13')) {
            $classes[] = 'TablePropertyToTableAttributeRector';
        }

        if (file_exists(base_path('server.php'))) {
            $classes[] = 'ServerVariableToRequestFacadeRector';
        }

        return $classes;
    }

    /**
     * @param  list<string>  $classes
     */
    private function skipSnippet(array $classes): string
    {
        return '->withSkip(['.implode(', ', array_map(fn (string $class): string => $class.'::class', $classes)).'])';
    }
}
