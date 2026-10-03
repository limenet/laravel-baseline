<?php

namespace Limenet\LaravelBaseline\Support;

/**
 * Writes a JSON file a check decoded as associative arrays, without turning
 * an emptied object into a list: json_decode(..., true) cannot tell `{}` from
 * `[]`, so removing the last package from "require-dev" would otherwise write
 * `"require-dev": []`, which Composer rejects. Wherever the file on disk had
 * an object, an empty array is written back as `{}`.
 *
 * It also keeps the indent the file already has, so a fix does not fight the
 * project's formatter (Biome follows the editorconfig, which indents JSON by
 * two). A new or single-line file gets json_encode's four spaces, matching
 * js/src/project.ts writeJson().
 */
final class JsonFile
{
    public const FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    private const DEFAULT_INDENT = '    ';

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function write(string $file, array $data, int $flags = self::FLAGS): void
    {
        $existing = is_file($file) ? (string) file_get_contents($file) : null;
        $shape = $existing !== null ? json_decode($existing) : null;

        $json = json_encode(self::restoreObjects($data, $shape), $flags | JSON_THROW_ON_ERROR);

        if (($flags & JSON_PRETTY_PRINT) !== 0) {
            $json = self::reindent($json, self::indentOf($existing));
        }

        file_put_contents($file, $json."\n");
    }

    /**
     * The leading whitespace of the first indented line, which in pretty-printed
     * JSON is one level deep.
     */
    private static function indentOf(?string $contents): string
    {
        if ($contents !== null && preg_match('/^([ \t]+)\S/m', $contents, $matches) === 1) {
            return $matches[1];
        }

        return self::DEFAULT_INDENT;
    }

    /**
     * json_encode escapes newlines inside strings, so every line's leading
     * spaces are indentation and can be swapped level by level.
     */
    private static function reindent(string $json, string $indent): string
    {
        if ($indent === self::DEFAULT_INDENT) {
            return $json;
        }

        return (string) preg_replace_callback(
            '/^(?:'.self::DEFAULT_INDENT.')+/m',
            fn (array $matches): string => str_repeat($indent, intdiv(strlen($matches[0]), strlen(self::DEFAULT_INDENT))),
            $json,
        );
    }

    private static function restoreObjects(mixed $value, mixed $shape): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($shape instanceof \stdClass && $value === []) {
            return new \stdClass;
        }

        foreach ($value as $key => $child) {
            $childShape = match (true) {
                $shape instanceof \stdClass => $shape->{$key} ?? null,
                is_array($shape) => $shape[$key] ?? null,
                default => null,
            };

            $value[$key] = self::restoreObjects($child, $childShape);
        }

        return $value;
    }
}
