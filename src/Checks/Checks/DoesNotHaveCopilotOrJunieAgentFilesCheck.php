<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Support\Filesystem;

class DoesNotHaveCopilotOrJunieAgentFilesCheck extends AbstractFixableCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    public function fix(bool $dry = false): CheckResult
    {
        $present = [];

        foreach ($this->policy()->stringMap('agentFiles.forbidden') as $path => $reason) {
            $absolute = $this->path($path);

            if (file_exists($absolute) || is_dir($absolute)) {
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
            if (is_dir($absolute) && !is_link($absolute)) {
                Filesystem::deleteDirectory($absolute);

                continue;
            }

            unlink($absolute);
        }

        return $this->fix(dry: true);
    }
}
