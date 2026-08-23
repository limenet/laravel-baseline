<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

/**
 * Biome's `$schema` must resolve out of node_modules rather than name a released
 * version. The local path always describes the Biome that is actually installed,
 * so upgrading Biome does not also mean editing biome.json — a versioned remote
 * URL leaves the editor validating against whichever version was current when
 * someone last remembered to bump the line.
 *
 * A project without Biome is none of this check's business: no config file means
 * PASS, not a warning.
 *
 * Both the detection and the fix work on the raw text rather than a decoded
 * document, for two reasons: Biome reads its config as JSONC, so a file with
 * comments is valid and must not be reported as broken; and biome.json is
 * formatted by Biome itself, so re-encoding the whole file would leave
 * `biome ci` failing on the very file this fix just touched.
 */
class BiomeUsesLocalSchemaCheck extends AbstractFixableCheck
{
    /**
     * The `$schema` string literal, captured with its JSON escapes intact.
     */
    private const SCHEMA_PATTERN = '/"\$schema"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/';

    public function fix(bool $dry = false): CheckResult
    {
        $configFile = $this->policy()->string('biome.configFile');
        $file = base_path($configFile);

        if (!file_exists($file)) {
            return CheckResult::PASS;
        }

        $contents = file_get_contents($file);

        if ($contents === false || !str_contains($contents, '{')) {
            $this->addComment("{$configFile} is empty or unreadable");

            return CheckResult::FAIL;
        }

        $expected = $this->policy()->string('biome.schema');
        $declared = $this->declaredSchema($contents);

        if ($declared === $expected) {
            return CheckResult::PASS;
        }

        $this->addComment($declared === null
            ? "Missing \"\$schema\" in {$configFile}: set it to \"{$expected}\" so the schema follows the installed Biome"
            : "Invalid \"\$schema\" in {$configFile}: must be \"{$expected}\" (found \"{$declared}\"), so it does not need bumping on every Biome update");

        if ($dry) {
            return CheckResult::FAIL;
        }

        file_put_contents($file, $declared === null
            ? $this->insertSchema($contents, $expected)
            : $this->replaceSchema($contents, $expected));

        return $this->fix(dry: true);
    }

    /**
     * The decoded `$schema` value, or null if the file declares none.
     */
    private function declaredSchema(string $contents): ?string
    {
        if (preg_match(self::SCHEMA_PATTERN, $contents, $matches) !== 1) {
            return null;
        }

        $decoded = json_decode('"'.$matches[1].'"');

        return is_string($decoded) ? $decoded : null;
    }

    private function replaceSchema(string $contents, string $expected): string
    {
        // A callback, not a replacement string: the literal "$schema" would
        // otherwise have to be escaped against backreference interpolation.
        return (string) preg_replace_callback(
            self::SCHEMA_PATTERN,
            fn (): string => '"$schema": '.$this->encode($expected),
            $contents,
            limit: 1,
        );
    }

    /**
     * Prepends the key inside the top-level object, indented like whatever key
     * currently comes first — Biome's own convention is `$schema` first.
     */
    private function insertSchema(string $contents, string $expected): string
    {
        $open = (int) strpos($contents, '{');
        $rest = substr($contents, $open + 1);
        $indent = preg_match('/\n([ \t]+)\S/', $rest, $matches) === 1 ? $matches[1] : '    ';
        $separator = trim($rest) === '}' ? '' : ',';

        return substr($contents, 0, $open + 1)."\n{$indent}\"\$schema\": {$this->encode($expected)}{$separator}".$rest;
    }

    private function encode(string $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES);
    }
}
