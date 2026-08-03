<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Illuminate\Support\Facades\File;
use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class DoesNotHaveCopilotOrJunieAgentFilesCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $agentsMdFile = base_path('AGENTS.md');
        $junieDir = base_path('.junie');

        $agentsMdClean = !file_exists($agentsMdFile);
        $junieDirClean = !File::isDirectory($junieDir);

        if ($agentsMdClean && $junieDirClean) {
            return CheckResult::PASS;
        }

        if (!$agentsMdClean) {
            $this->addComment('Remove AGENTS.md — it is generated for the Copilot/Junie Boost agents, which are no longer required');
        }

        if (!$junieDirClean) {
            $this->addComment('Remove the .junie directory — it is generated for the Junie Boost agent, which is no longer required');
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        if (!$agentsMdClean) {
            unlink($agentsMdFile);
        }

        if (!$junieDirClean) {
            File::deleteDirectory($junieDir);
        }

        return $this->fix(dry: true);
    }
}
