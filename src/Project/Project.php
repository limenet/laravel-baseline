<?php

namespace Limenet\LaravelBaseline\Project;

use Limenet\LaravelBaseline\Policy\Policy;
use Limenet\LaravelBaseline\State\StateStore;

/**
 * Everything a check needs from the project it inspects, so the checks never
 * call base_path() or reach into the container themselves. The Laravel runner
 * passes a LaravelProject, the standalone runner a FilesystemProject.
 */
interface Project
{
    public function profile(): Profile;

    /**
     * An absolute path inside the project, with base_path() semantics:
     * a leading slash on $relative is ignored and '' is the root itself.
     */
    public function path(string $relative = ''): string;

    /**
     * Whether composer.json declares the package in require or require-dev.
     */
    public function hasComposerPackage(string $package): bool;

    public function policy(): Policy;

    public function state(): StateStore;
}
