<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Yaml\Yaml;

class DdevHasPcovPackageCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $ddevConfig = $this->getDdevConfig();

        if ($ddevConfig === null) {
            return CheckResult::FAIL;
        }

        $extraPackages = $ddevConfig['webimage_extra_packages'] ?? null;
        $pcovPackage = 'php${DDEV_PHP_VERSION}-pcov';

        if ($extraPackages === null) {
            $this->addComment('DDEV missing pcov package configuration: Add "webimage_extra_packages" to .ddev/config.yaml');

            if ($dry) {
                return CheckResult::FAIL;
            }
        } elseif (!is_array($extraPackages)) {
            $this->addComment('DDEV configuration error: "webimage_extra_packages" must be an array in .ddev/config.yaml');

            return CheckResult::FAIL;
        } elseif (!in_array($pcovPackage, $extraPackages, true)) {
            $this->addComment(sprintf(
                'DDEV missing pcov package: Add "%s" to webimage_extra_packages in .ddev/config.yaml',
                $pcovPackage,
            ));

            if ($dry) {
                return CheckResult::FAIL;
            }
        }

        $customIniFile = base_path('.ddev/php/90-custom.ini');
        $iniContent = file_exists($customIniFile) ? (file_get_contents($customIniFile) ?: '') : '';

        if (!str_starts_with(trim($iniContent), '[PHP]')) {
            $this->addComment('DDEV PHP configuration missing or invalid: Create .ddev/php/90-custom.ini with [PHP] section and opcache.jit=disable');

            if ($dry) {
                return CheckResult::FAIL;
            }
        } elseif (!str_contains($iniContent, 'opcache.jit=disable')) {
            $this->addComment('DDEV PHP configuration incomplete: Add "opcache.jit=disable" to .ddev/php/90-custom.ini');

            if ($dry) {
                return CheckResult::FAIL;
            }
        }

        if ($dry) {
            return CheckResult::PASS;
        }

        // Apply fixes
        $ddevConfigFile = base_path('.ddev/config.yaml');

        if (file_exists($ddevConfigFile)) {
            $config = Yaml::parseFile($ddevConfigFile) ?? [];
            $packages = is_array($config['webimage_extra_packages'] ?? null) ? $config['webimage_extra_packages'] : [];

            if (!in_array($pcovPackage, $packages, true)) {
                $packages[] = $pcovPackage;
                $config['webimage_extra_packages'] = $packages;
                file_put_contents($ddevConfigFile, Yaml::dump($config, 4, 2));
            }
        }

        if (!file_exists($customIniFile)
            || !str_contains(file_get_contents($customIniFile) ?: '', 'opcache.jit=disable')
        ) {
            $iniDir = dirname($customIniFile);

            if (!is_dir($iniDir)) {
                mkdir($iniDir, 0755, true);
            }

            file_put_contents($customIniFile, "[PHP]\nopcache.jit=disable\n");
        }

        return $this->fix(dry: true);
    }
}
