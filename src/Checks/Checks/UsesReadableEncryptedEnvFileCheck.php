<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Finder\Finder;

class UsesReadableEncryptedEnvFileCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $files = (new Finder())
            ->in(base_path())
            ->ignoreDotFiles(false)
            ->name('.env.*.encrypted')
            ->depth('== 0')
            ->files();

        // Existence of an encrypted env file is HasEncryptedEnvFileCheck's
        // concern; this check only validates the *format* of one that exists.
        if (!$files->hasResults()) {
            return CheckResult::PASS;
        }

        foreach ($files as $file) {
            if (!$this->isReadableFormat($file->getContents())) {
                $this->addComment(sprintf(
                    'Encrypted env file %s uses the opaque blob format: Re-encrypt with `ddev artisan env:encrypt --readable` so variable names stay visible in diffs',
                    $file->getFilename(),
                ));

                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }

    /**
     * The readable format (`env:encrypt --readable`) is a line-per-variable
     * `KEY=<encrypted-value>` listing, whereas the legacy format is a single
     * opaque base64 blob. Detect the readable format by the presence of at
     * least one `KEY=` line.
     */
    private function isReadableFormat(string $contents): bool
    {
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^[A-Z_][A-Z0-9_]*=/', trim($line)) === 1) {
                return true;
            }
        }

        return false;
    }
}
