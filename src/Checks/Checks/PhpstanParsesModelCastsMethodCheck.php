<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

/**
 * Larastan only reads the body of a model's `casts()` method when
 * parseModelCastsMethod is on. It defaults to false, in which case the method's
 * declared return type (`array<string, string>`, not a constant array) is all
 * that is left — so every cast is lost and datetime attributes report as
 * strings. Laravel scaffolds `casts()` by default, and Rector rewrites the
 * legacy `$casts` property into one, so the parameter is what keeps
 * checkModelProperties honest rather than an optimisation.
 */
class PhpstanParsesModelCastsMethodCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $phpstanConfigFile = base_path('phpstan.neon');

        if (!file_exists($phpstanConfigFile)) {
            $this->addComment('PHPStan configuration missing: Create phpstan.neon in project root');

            return CheckResult::FAIL;
        }

        $phpstanConfig = $this->loadYamlConfig('phpstan.neon');

        if ($phpstanConfig === null) {
            return CheckResult::FAIL;
        }

        if (($phpstanConfig['parameters']['parseModelCastsMethod'] ?? null) === true) {
            return CheckResult::PASS;
        }

        $this->addComment('PHPStan does not read the casts() method: Add "parseModelCastsMethod: true" to the parameters section of phpstan.neon so Larastan types attributes cast in casts() rather than the $casts property');

        if ($dry) {
            return CheckResult::FAIL;
        }

        $this->setNeonParameter($phpstanConfigFile, 'parseModelCastsMethod', 'true');

        return $this->fix(dry: true);
    }

    /**
     * Writes a scalar into the `parameters:` block as a targeted text edit: a
     * parse/dump round trip would discard every comment in the file, and NEON
     * is only YAML-shaped enough to read — Symfony's dumper cannot write it
     * back (`includes:`, entities, tabs).
     */
    private function setNeonParameter(string $file, string $key, string $value): void
    {
        $content = file_get_contents($file) ?: '';
        $lines = explode("\n", $content);
        $headerIndex = null;

        foreach ($lines as $index => $line) {
            if (preg_match('/^parameters:[ \t]*(?:#.*)?$/', $line) === 1) {
                $headerIndex = $index;
                break;
            }
        }

        // Appending a second `parameters:` would give the file a duplicate key,
        // so the block is only created when there is none to extend.
        if ($headerIndex === null) {
            file_put_contents($file, rtrim($content, "\n")."\n\nparameters:\n    {$key}: {$value}\n");

            return;
        }

        // The first child line fixes the block's indentation; anything deeper
        // belongs to a nested value (`paths:` and its list) and is never the key
        // being set, but still extends the block the entry is appended to.
        $blockIndent = null;
        $insertAt = $headerIndex + 1;

        for ($i = $headerIndex + 1, $count = count($lines); $i < $count; $i++) {
            // Blank lines sit inside the block; a dedented line ends it.
            if (trim($lines[$i]) === '') {
                continue;
            }

            if (preg_match('/^[ \t]+/', $lines[$i], $indentMatch) !== 1) {
                break;
            }

            $blockIndent ??= $indentMatch[0];

            if (strlen($indentMatch[0]) < strlen($blockIndent)) {
                break;
            }

            $insertAt = $i + 1;

            if (strlen($indentMatch[0]) === strlen($blockIndent)
                && preg_match('/^[ \t]+'.preg_quote($key, '/').':/', $lines[$i]) === 1) {
                $lines[$i] = $blockIndent.$key.': '.$value;
                file_put_contents($file, implode("\n", $lines));

                return;
            }
        }

        array_splice($lines, $insertAt, 0, [($blockIndent ?? '    ').$key.': '.$value]);
        file_put_contents($file, implode("\n", $lines));
    }
}
