<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Concerns\WritesNeonParameters;
use Limenet\LaravelBaseline\Enums\CheckResult;

/**
 * Larastan only reads the body of a model's `casts()` method when
 * parseModelCastsMethod is on. It defaults to false, in which case the method's
 * declared return type (`array<string, string>`, not a constant array) is all
 * that is left — so every cast is lost and datetime attributes report as
 * strings. Laravel scaffolds `casts()` by default, and Rector rewrites the
 * legacy `$casts` property into one, so the parameter is what keeps
 * checkModelProperties honest rather than an optimisation.
 */
class PhpstanParsesModelCastsMethodCheck extends AbstractFixableCheck
{
    use WritesNeonParameters;

    public function fix(bool $dry = false): CheckResult
    {
        $configFile = $this->phpstanConfigFile();

        if ($configFile === null) {
            $this->addComment('PHPStan configuration missing: Create phpstan.neon in project root');

            return CheckResult::FAIL;
        }

        $phpstanConfigFile = $this->path($configFile);
        $phpstanConfig = $this->loadNeonConfig($configFile);

        if ($phpstanConfig === null) {
            return CheckResult::FAIL;
        }

        if (($phpstanConfig['parameters']['parseModelCastsMethod'] ?? null) === true) {
            return CheckResult::PASS;
        }

        $this->addComment('PHPStan does not read the casts() method: Add "parseModelCastsMethod: true" to the parameters section of '.$configFile.' so Larastan types attributes cast in casts() rather than the $casts property');

        if ($dry) {
            return CheckResult::FAIL;
        }

        $this->setNeonParameter($phpstanConfigFile, 'parseModelCastsMethod', 'true');

        return $this->fix(dry: true);
    }
}
