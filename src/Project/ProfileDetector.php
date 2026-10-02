<?php

namespace Limenet\LaravelBaseline\Project;

use Limenet\LaravelBaseline\State\JsonStateStore;
use Limenet\LaravelBaseline\Support\WordPressHeader;

/**
 * Decides which profile the standalone runner checks a project under.
 *
 * A Laravel application is refused rather than checked: artisan runs the full
 * Laravel profile, and running the standalone one there would silently skip
 * most of it. An `artisan` file always wins, even over an explicit override.
 */
final class ProfileDetector
{
    private const WORDPRESS_COMPOSER_TYPES = ['wordpress-theme', 'wordpress-plugin', 'wordpress-muplugin'];

    public static function detect(string $root): Profile
    {
        if (is_file($root.'/artisan')) {
            throw ProfileDetectionException::laravel('it has an artisan file');
        }

        $override = self::override($root);

        if ($override !== null) {
            return $override;
        }

        $composerJson = self::composerJson($root);

        if (is_array($composerJson['require'] ?? null) && array_key_exists('laravel/framework', $composerJson['require'])) {
            throw ProfileDetectionException::laravel('composer.json requires laravel/framework');
        }

        return self::isWordPress($root, $composerJson) ? Profile::WordPress : Profile::Php;
    }

    private static function override(string $root): ?Profile
    {
        $state = (new JsonStateStore($root.'/'.JsonStateStore::FILE))->read();
        $value = $state['profile'] ?? null;

        if ($value === null) {
            return null;
        }

        $profile = is_string($value) ? Profile::tryFrom($value) : null;

        if ($profile === Profile::Laravel) {
            throw ProfileDetectionException::laravel('.baseline.json sets "profile": "laravel"');
        }

        return $profile ?? throw ProfileDetectionException::invalidOverride(is_scalar($value) ? (string) $value : get_debug_type($value));
    }

    /**
     * @param  array<string,mixed>  $composerJson
     */
    private static function isWordPress(string $root, array $composerJson): bool
    {
        if (in_array($composerJson['type'] ?? null, self::WORDPRESS_COMPOSER_TYPES, true)) {
            return true;
        }

        if (WordPressHeader::read($root.'/style.css', 'Theme Name') !== null) {
            return true;
        }

        foreach (glob($root.'/*.php') ?: [] as $file) {
            if (WordPressHeader::read($file, 'Plugin Name') !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,mixed>
     */
    private static function composerJson(string $root): array
    {
        $file = $root.'/composer.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) ? $data : [];
    }
}
