<?php

namespace Limenet\LaravelBaseline\Project;

use Limenet\LaravelBaseline\Policy\Policy;
use Limenet\LaravelBaseline\State\JsonStateStore;
use Limenet\LaravelBaseline\State\StateStore;

/**
 * A project known only by its root directory — what the standalone runner
 * checks, where there is no application to ask. The PHP counterpart of
 * js/src/project.ts.
 */
final class FilesystemProject implements Project
{
    private ?Policy $policy;

    public function __construct(
        private readonly string $root,
        private readonly Profile $profile,
        ?Policy $policy = null,
    ) {
        $this->policy = $policy;
    }

    public function profile(): Profile
    {
        return $this->profile;
    }

    public function path(string $relative = ''): string
    {
        $relative = ltrim($relative, '/\\');

        return $relative === '' ? $this->root : $this->root.DIRECTORY_SEPARATOR.$relative;
    }

    public function hasComposerPackage(string $package): bool
    {
        $file = $this->path('composer.json');

        if (!is_file($file)) {
            return false;
        }

        $composerJson = json_decode((string) file_get_contents($file), true);

        if (!is_array($composerJson)) {
            return false;
        }

        foreach (['require', 'require-dev'] as $section) {
            if (is_array($composerJson[$section] ?? null) && array_key_exists($package, $composerJson[$section])) {
                return true;
            }
        }

        return false;
    }

    public function policy(): Policy
    {
        return $this->policy ??= Policy::fromDirectory();
    }

    public function state(): StateStore
    {
        return new JsonStateStore($this->path(JsonStateStore::FILE));
    }
}
