<?php

namespace Limenet\LaravelBaseline\Concerns;

/**
 * Sets a scalar in a PHPStan config's `parameters:` block.
 */
trait WritesNeonParameters
{
    /**
     * Writes a scalar into the `parameters:` block as a targeted text edit: a
     * parse/dump round trip would discard every comment in the file, and NEON
     * is only YAML-shaped enough to read — Symfony's dumper cannot write it
     * back (`includes:`, entities, tabs).
     */
    protected function setNeonParameter(string $file, string $key, string $value): void
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
