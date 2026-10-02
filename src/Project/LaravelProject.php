<?php

namespace Limenet\LaravelBaseline\Project;

use Illuminate\Support\Composer;
use Limenet\LaravelBaseline\Policy\Policy;
use Limenet\LaravelBaseline\State\PhpConfigStateStore;
use Limenet\LaravelBaseline\State\StateStore;

/**
 * The project the artisan commands run in. Stateless on purpose: every call
 * resolves through the application at call time, so a base path or a
 * container binding swapped mid-test is what the next check sees.
 */
final class LaravelProject implements Project
{
    public function profile(): Profile
    {
        return Profile::Laravel;
    }

    public function path(string $relative = ''): string
    {
        return base_path($relative);
    }

    public function hasComposerPackage(string $package): bool
    {
        return app(Composer::class)->setWorkingPath(base_path())->hasPackage($package);
    }

    public function policy(): Policy
    {
        return app(Policy::class);
    }

    public function state(): StateStore
    {
        return new PhpConfigStateStore;
    }
}
