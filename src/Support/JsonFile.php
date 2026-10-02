<?php

namespace Limenet\LaravelBaseline\Support;

/**
 * Writes a JSON file a check decoded as associative arrays, without turning
 * an emptied object into a list: json_decode(..., true) cannot tell `{}` from
 * `[]`, so removing the last package from "require-dev" would otherwise write
 * `"require-dev": []`, which Composer rejects. Wherever the file on disk had
 * an object, an empty array is written back as `{}`.
 */
final class JsonFile
{
    public const FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function write(string $file, array $data, int $flags = self::FLAGS): void
    {
        $shape = is_file($file) ? json_decode((string) file_get_contents($file)) : null;

        file_put_contents($file, json_encode(self::restoreObjects($data, $shape), $flags | JSON_THROW_ON_ERROR)."\n");
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
