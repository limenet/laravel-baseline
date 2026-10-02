<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

/**
 * Pint is the formatter in every profile (see isCiLintComplete); a project
 * that still runs PHP CS Fixer directly formats its code twice, by two
 * rule sets that disagree.
 */
class DoesNotUsePhpCsFixerCheck extends AbstractFixableCheck
{
    private const PACKAGE = 'friendsofphp/php-cs-fixer';

    private const CONFIG_FILES = ['.php-cs-fixer.php', '.php-cs-fixer.dist.php', '.php_cs', '.php_cs.dist'];

    private const CACHE_FILES = ['.php-cs-fixer.cache', '.php_cs.cache'];

    public static function profiles(): array
    {
        return Profile::cases();
    }

    public function fix(bool $dry = false): CheckResult
    {
        $hasPackage = $this->project->hasComposerPackage(self::PACKAGE);
        $hasScript = $this->composerScriptContains('ci-lint', 'php-cs-fixer');
        $configs = $this->existing(self::CONFIG_FILES);
        $caches = $this->existing(self::CACHE_FILES);

        if (!$hasPackage && !$hasScript && $configs === [] && $caches === []) {
            return CheckResult::PASS;
        }

        if ($hasPackage) {
            $this->addComment('Remove '.self::PACKAGE.' from composer.json — Pint replaces PHP CS Fixer (run `composer update` afterward to sync composer.lock)');
        }

        if ($hasScript) {
            $this->addComment("Remove the 'php-cs-fixer' entries from the ci-lint script in composer.json — Pint runs there instead");
        }

        // The config holds hand-tuned rules, and this fix runs unattended from
        // post-update-cmd: it only goes once a pint.json shows they were ported.
        $rulesPorted = file_exists($this->path('pint.json'));

        foreach ($configs as $file) {
            $this->addComment($rulesPorted
                ? "Remove {$file} — Pint reads pint.json instead"
                : "Port the rules in {$file} to pint.json, then remove {$file}");
        }

        foreach ($caches as $file) {
            $this->addComment("Remove {$file}");
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        if ($hasPackage) {
            $this->removeComposerPackage(self::PACKAGE);
        }

        if ($hasScript) {
            $this->removeFromComposerScript('ci-lint', 'php-cs-fixer');
        }

        foreach ([...$caches, ...($rulesPorted ? $configs : [])] as $file) {
            unlink($this->path($file));
        }

        return $this->fix(dry: true);
    }

    /**
     * @param  list<string>  $files
     * @return list<string>
     */
    private function existing(array $files): array
    {
        return array_values(array_filter($files, fn (string $file): bool => file_exists($this->path($file))));
    }

    /**
     * checkComposerScript() without its comment, which would otherwise show up
     * on every passing run of this check at -vv.
     */
    private function composerScriptContains(string $scriptName, string $match): bool
    {
        $scripts = (array) ($this->getComposerJson()['scripts'][$scriptName] ?? []);

        foreach ($scripts as $script) {
            if (is_string($script) && str_contains($script, $match)) {
                return true;
            }
        }

        return false;
    }
}
