<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class DdevHasPcovPackageCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $ddevConfig = $this->getDdevConfig();

        if ($ddevConfig === null) {
            return CheckResult::FAIL;
        }

        $extraPackages = $ddevConfig['webimage_extra_packages'] ?? null;

        if ($extraPackages === null) {
            $this->addComment('DDEV missing pcov package configuration: Add "webimage_extra_packages" to .ddev/config.yaml');

            return CheckResult::FAIL;
        }

        if (!is_array($extraPackages)) {
            $this->addComment('DDEV configuration error: "webimage_extra_packages" must be an array in .ddev/config.yaml');

            return CheckResult::FAIL;
        }

        // Check if pcov package is in the list (with the DDEV_PHP_VERSION variable)
        $pcovPackage = 'php${DDEV_PHP_VERSION}-pcov';

        if (!in_array($pcovPackage, $extraPackages, true)) {
            $this->addComment(sprintf(
                'DDEV missing pcov package: Add "%s" to webimage_extra_packages in .ddev/config.yaml',
                $pcovPackage,
            ));

            return CheckResult::FAIL;
        }

        // Check .ddev/php/90-custom.ini exists and has correct content
        $customIniFile = base_path('.ddev/php/90-custom.ini');

        if (!file_exists($customIniFile)) {
            $this->addComment('DDEV PHP configuration missing: Create .ddev/php/90-custom.ini with [PHP] section and opcache.jit=disable');

            return CheckResult::FAIL;
        }

        $iniContent = file_get_contents($customIniFile) ?: '';

        if (!str_starts_with(trim($iniContent), '[PHP]')) {
            $this->addComment('DDEV PHP configuration invalid: .ddev/php/90-custom.ini must start with [PHP] section');

            return CheckResult::FAIL;
        }

        if (!str_contains($iniContent, 'opcache.jit=disable')) {
            $this->addComment('DDEV PHP configuration incomplete: Add "opcache.jit=disable" to .ddev/php/90-custom.ini');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
