<?php

namespace Limenet\LaravelBaseline\Support;

/**
 * Reads a WordPress file header (style.css "Theme Name:", a plugin's
 * "Plugin Name:", "Version:" …) the way WordPress's get_file_data() does:
 * from the first 8 KB, one "Key: value" per line, comment markers allowed.
 */
final class WordPressHeader
{
    private const BYTES = 8192;

    public static function read(string $file, string $header): ?string
    {
        if (!is_file($file)) {
            return null;
        }

        $handle = fopen($file, 'r');

        if ($handle === false) {
            return null;
        }

        $head = (string) fread($handle, self::BYTES);
        fclose($handle);

        if (preg_match(self::pattern($header), str_replace("\r", "\n", $head), $match) !== 1) {
            return null;
        }

        $value = trim((string) preg_replace('/\s*(?:\*\/|\?>).*/', '', $match[1]));

        return $value === '' ? null : $value;
    }

    /**
     * Rewrites a header's value in place, leaving the rest of the file — and
     * the header line's own prefix — untouched. Returns false when the header
     * is absent.
     */
    public static function write(string $file, string $header, string $value): bool
    {
        $contents = (string) file_get_contents($file);
        $count = 0;

        /*
         * Only the value is replaced: whatever closes the comment or the PHP
         * block on the same line, and a CRLF line ending, stay where they were.
         */
        $updated = preg_replace_callback(
            '/^((?:[ \t]*<\?php)?[ \t\/*#@]*'.preg_quote($header, '/').':[ \t]*)[^\r\n]*?([ \t]*(?:\*\/|\?>)[^\r\n]*)?(\r?)$/mi',
            static fn (array $m): string => $m[1].$value.$m[2].$m[3],
            $contents,
            1,
            $count,
        );

        if ($count === 0 || $updated === null) {
            return false;
        }

        file_put_contents($file, $updated);

        return true;
    }

    private static function pattern(string $header): string
    {
        return '/^(?:[ \t]*<\?php)?[ \t\/*#@]*'.preg_quote($header, '/').':(.*)$/mi';
    }
}
