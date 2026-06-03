<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Carbon\Carbon;
use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Yaml\Yaml;

class UpdatesDdevAddonsCheck extends AbstractCheck
{
    private const MAX_AGE_MONTHS = 3;

    public function check(): CheckResult
    {
        $manifestFiles = glob(base_path('.ddev/addon-metadata/*/manifest.yaml')) ?: [];

        $result = CheckResult::PASS;

        foreach ($manifestFiles as $manifestFile) {
            $manifest = Yaml::parseFile($manifestFile);

            if (!is_array($manifest) || !isset($manifest['install_date'])) {
                continue;
            }

            $installedAt = Carbon::parse($manifest['install_date']);

            if ($installedAt->copy()->addMonths(self::MAX_AGE_MONTHS)->isPast()) {
                $repository = $manifest['repository'] ?? $manifest['name'] ?? 'unknown';

                $this->addComment(sprintf(
                    'DDEV addon "%s" is outdated (installed %s, older than %d months). '
                    .'Update with "ddev add-on get %s"',
                    $manifest['name'] ?? $repository,
                    $installedAt->toDateString(),
                    self::MAX_AGE_MONTHS,
                    $repository,
                ));

                $result = CheckResult::FAIL;
            }
        }

        return $result;
    }
}
