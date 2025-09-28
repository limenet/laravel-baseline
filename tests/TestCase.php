<?php

namespace Limenet\LaravelBaseline\Tests;

use Illuminate\Filesystem\Filesystem;
use Limenet\LaravelBaseline\LaravelBaselineServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class TestCase extends Orchestra
{
    private TemporaryDirectory $tempDir;

    /**
     * Helper: create an isolated temporary base path for filesystem-based checks.
     * Returns [$tmpDir, $cleanup].
     *
     * @param  array<string,string>  $files  relative path => content
     */
    public function withTempBasePath(array $files = []): void
    {
        $fs = new Filesystem();

        $tempDirObj = (new TemporaryDirectory())
            ->create();
        $tmp = $tempDirObj->path();

        foreach ($files as $path => $content) {
            $full = $tmp.DIRECTORY_SEPARATOR.$path;
            $fs->ensureDirectoryExists(dirname($full));
            $fs->put($full, $content);
        }

        $app = app();
        $app->setBasePath($tmp);
        $this->tempDir = $tempDirObj;
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelBaselineServiceProvider::class,
        ];
    }
}
