<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Finder\Finder;

/**
 * Not fixable: a stray `.env.production` may hold the only copy of changes
 * that were never re-encrypted, so deleting it is the developer's call.
 */
class DoesNotHaveStrayEnvFilesCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $allowed = $this->policy()->strings('envFiles.allowed');

        $files = (new Finder)
            ->in($this->path())
            ->ignoreDotFiles(false)
            ->ignoreVCSIgnored(false)
            ->name('.env*')
            ->depth('== 0')
            ->files()
            ->sortByName();

        $stray = [];

        foreach ($files as $file) {
            $name = $file->getFilename();

            foreach ($allowed as $pattern) {
                if (fnmatch($pattern, $name, FNM_PERIOD)) {
                    continue 2;
                }
            }

            $stray[] = $name;
        }

        foreach ($stray as $name) {
            $this->addComment(sprintf(
                'Remove %s — plaintext env files other than %s must not live in the project, even gitignored: re-encrypt any changes with `ddev artisan env:encrypt --readable`, then delete it',
                $name,
                implode(', ', $allowed),
            ));
        }

        return $stray === [] ? CheckResult::PASS : CheckResult::FAIL;
    }
}
