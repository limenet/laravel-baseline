<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Support\WordPressHeader;

/**
 * WordPress reads a theme's version from the Version: header in style.css,
 * while release-it (via @release-it/bumper, see usesReleaseIt) keeps the
 * project's version in composer.json. The two drift the moment a release
 * bumps one and not the other.
 */
class WordpressThemeVersionMatchesComposerCheck extends AbstractFixableCheck
{
    private const STYLESHEET = 'style.css';

    public static function profiles(): array
    {
        return [Profile::WordPress];
    }

    public function fix(bool $dry = false): CheckResult
    {
        $stylesheet = $this->path(self::STYLESHEET);

        if (WordPressHeader::read($stylesheet, 'Theme Name') === null) {
            $this->addComment('No theme header in style.css: this check only applies to WordPress themes');

            return CheckResult::WARN;
        }

        $themeVersion = WordPressHeader::read($stylesheet, 'Version');
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return CheckResult::FAIL;
        }

        $composerVersion = is_string($composerJson['version'] ?? null) ? $composerJson['version'] : null;

        if ($themeVersion !== null && $themeVersion === $composerVersion) {
            return CheckResult::PASS;
        }

        if ($themeVersion === null && $composerVersion === null) {
            $this->addComment('Neither style.css nor composer.json declares a version: add "Version: 1.0.0" to the style.css header and "version": "1.0.0" to composer.json');

            return CheckResult::FAIL;
        }

        if ($composerVersion === null) {
            // Seed composer.json from the theme, so the bumper has a version to carry forward.
            $this->addComment("composer.json has no \"version\": set it to the theme's {$themeVersion} — @release-it/bumper keeps it current from there");

            if ($dry) {
                return CheckResult::FAIL;
            }

            $composerJson['version'] = $themeVersion;
            $this->writeComposerJson($composerJson);

            return $this->fix(dry: true);
        }

        $this->addComment($themeVersion === null
            ? "style.css has no Version: header: add \"Version: {$composerVersion}\" to it"
            : "style.css declares Version: {$themeVersion}, composer.json {$composerVersion}: set the style.css header to {$composerVersion}");

        if ($dry || $themeVersion === null) {
            // A missing header is not rewritten: where it goes in the comment block is the developer's call.
            return CheckResult::FAIL;
        }

        WordPressHeader::write($stylesheet, 'Version', $composerVersion);

        return $this->fix(dry: true);
    }
}
