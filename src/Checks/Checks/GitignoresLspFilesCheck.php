<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class GitignoresLspFilesCheck extends AbstractFixableCheck
{
    private const ENTRY = 'storage/framework/lsp-*.php';

    public function fix(bool $dry = false): CheckResult
    {
        $result = $this->ensureGitignoreEntry(
            self::ENTRY,
            'ignore the Laravel language server files generated in storage/framework',
            $dry,
        );

        if ($result !== null && $dry) {
            return $result;
        }

        return CheckResult::PASS;
    }
}
