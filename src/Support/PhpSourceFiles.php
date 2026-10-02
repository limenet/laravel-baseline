<?php

namespace Limenet\LaravelBaseline\Support;

use Symfony\Component\Finder\Finder;

/**
 * The project's own PHP files, as project-relative paths with forward
 * slashes: everything except dependencies, dot-directories and git-ignored
 * files — the same set a developer would expect the linters to look at.
 */
final class PhpSourceFiles
{
    /**
     * @param  list<string>  $ignoredDirectories  directory names skipped at any depth
     * @return list<string>
     */
    public static function in(string $root, array $ignoredDirectories): array
    {
        $finder = (new Finder)
            ->in($root)
            ->files()
            ->name('*.php')
            ->ignoreDotFiles(true)
            ->ignoreVCSIgnored(true)
            ->exclude($ignoredDirectories)
            ->sortByName();

        $files = [];

        foreach ($finder as $file) {
            $files[] = str_replace('\\', '/', $file->getRelativePathname());
        }

        return $files;
    }

    /**
     * Whether a configured path (a file, a directory or a glob, relative to
     * the project root; '' or '.' meaning the root itself) covers a file.
     */
    public static function covers(string $path, string $file): bool
    {
        $path = self::normalize($path);

        if ($path === '') {
            return true;
        }

        if (str_contains($path, '*') || str_contains($path, '?') || str_contains($path, '[')) {
            return fnmatch($path, $file) || fnmatch(rtrim($path, '/').'/*', $file);
        }

        return $file === $path || str_starts_with($file, $path.'/');
    }

    private static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        return $path === '.' ? '' : rtrim($path, '/');
    }
}
