<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Illuminate\Support\Facades\File;
use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class DoesNotHaveCopilotOrJunieAgentFilesCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $present = [];

        foreach ($this->policy()->stringMap('agentFiles.forbidden') as $path => $reason) {
            $absolute = base_path($path);

            if (file_exists($absolute) || File::isDirectory($absolute)) {
                $present[$path] = $absolute;
                $this->addComment("Remove {$path} — {$reason}");
            }
        }

        if ($present === []) {
            return CheckResult::PASS;
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        foreach ($present as $absolute) {
            if (File::isDirectory($absolute)) {
                File::deleteDirectory($absolute);

                continue;
            }

            unlink($absolute);
        }

        return $this->fix(dry: true);
    }
}
