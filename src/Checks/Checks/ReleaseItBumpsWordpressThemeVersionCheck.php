<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Support\JsonFile;
use Limenet\LaravelBaseline\Support\WordPressHeader;

/**
 * release-it bumps composer.json through @release-it/bumper, which cannot
 * rewrite one header line inside a CSS comment. An after:bump hook does, and
 * because it runs before release-it commits, the new header lands in the
 * release commit alongside composer.json.
 */
class ReleaseItBumpsWordpressThemeVersionCheck extends AbstractFixableCheck
{
    public static function profiles(): array
    {
        return [Profile::WordPress];
    }

    public function fix(bool $dry = false): CheckResult
    {
        if (WordPressHeader::read($this->path('style.css'), 'Theme Name') === null) {
            $this->addComment('No theme header in style.css: this check only applies to WordPress themes');

            return CheckResult::WARN;
        }

        $releaseItFile = $this->path('.release-it.json');

        if (!file_exists($releaseItFile)) {
            // Setting release-it up is usesReleaseIt's job; there is nothing to hook into yet.
            $this->addComment('No .release-it.json yet: set up release-it first (see usesReleaseIt)');

            return CheckResult::WARN;
        }

        $config = $this->getReleaseItConfig() ?? [];
        $hooks = (array) ($config['hooks']['after:bump'] ?? []);

        foreach ($hooks as $hook) {
            // Any after:bump hook that touches style.css counts — a project may
            // prefer its own script over the policy's one-liner.
            if (is_string($hook) && str_contains($hook, 'style.css')) {
                return CheckResult::PASS;
            }
        }

        $command = $this->policy()->string('wordpress.themeVersionHook');
        $this->addComment('Missing after:bump hook in .release-it.json: add a hook that rewrites the Version: header of style.css, e.g. '.$command);

        if ($dry) {
            return CheckResult::FAIL;
        }

        $config['hooks']['after:bump'] = [...array_values(array_filter($hooks, is_string(...))), $command];

        JsonFile::write($releaseItFile, $config);

        return $this->fix(dry: true);
    }
}
