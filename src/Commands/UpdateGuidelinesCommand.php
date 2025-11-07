<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UpdateGuidelinesCommand extends Command
{
    public $signature = 'limenet:laravel-baseline:guidelines';

    public $description = 'Updates .ai/guidelines with baseline guideline files from the package';

    public function handle(): int
    {
        $guidelinesDir = base_path('.ai/guidelines');
        $stubsDir = __DIR__.'/../../stubs/.ai/guidelines';

        // Ensure the guidelines directory exists
        if (!File::isDirectory($guidelinesDir)) {
            File::makeDirectory($guidelinesDir, 0755, true);
            $this->info('Created .ai/guidelines directory');
        }

        // Delete existing baseline-*.md files
        $existingBaselineFiles = File::glob($guidelinesDir.'/baseline-*.md');
        foreach ($existingBaselineFiles as $file) {
            File::delete($file);
        }

        if (count($existingBaselineFiles) > 0) {
            $this->info('Removed '.count($existingBaselineFiles).' existing baseline guideline file(s)');
        }

        // Copy all baseline-*.md files from stubs
        $stubFiles = File::glob($stubsDir.'/baseline-*.md');
        $copiedFiles = [];

        foreach ($stubFiles as $stubFile) {
            $filename = basename($stubFile);
            $destination = $guidelinesDir.'/'.$filename;
            File::copy($stubFile, $destination);
            $copiedFiles[] = $filename;
        }

        if (count($copiedFiles) === 0) {
            $this->warn('No baseline guideline files found in package stubs');

            return self::SUCCESS;
        }

        $this->info('Published '.count($copiedFiles).' baseline guideline file(s):');
        foreach ($copiedFiles as $file) {
            $this->line('  - '.$file);
        }

        return self::SUCCESS;
    }
}
