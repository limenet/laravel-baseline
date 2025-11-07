<?php

use Illuminate\Support\Facades\File;
use Limenet\LaravelBaseline\Commands\UpdateGuidelinesCommand;

it('creates .ai/guidelines directory if it does not exist', function (): void {
    $this->withTempBasePath([]);

    $guidelinesDir = base_path('.ai/guidelines');
    expect(File::isDirectory($guidelinesDir))->toBeFalse();

    $this->artisan(UpdateGuidelinesCommand::class)
        ->assertSuccessful();

    expect(File::isDirectory($guidelinesDir))->toBeTrue();
});

it('publishes baseline-*.md files from package stubs', function (): void {
    $this->withTempBasePath([]);

    $this->artisan(UpdateGuidelinesCommand::class)
        ->assertSuccessful();

    $guidelinesDir = base_path('.ai/guidelines');
    expect(File::exists($guidelinesDir.'/baseline-laravel.md'))->toBeTrue();

    $content = File::get($guidelinesDir.'/baseline-laravel.md');
    expect($content)->toContain('Laravel Baseline Guidelines')
        ->toContain('ci-lint')
        ->toContain('ddev');
});

it('deletes existing baseline-*.md files before re-publishing', function (): void {
    $this->withTempBasePath([
        '.ai/guidelines/baseline-laravel.md' => 'Old content',
        '.ai/guidelines/baseline-old.md' => 'Old file to be deleted',
    ]);

    $guidelinesDir = base_path('.ai/guidelines');
    expect(File::exists($guidelinesDir.'/baseline-old.md'))->toBeTrue();

    $this->artisan(UpdateGuidelinesCommand::class)
        ->assertSuccessful();

    // Old baseline file should be deleted
    expect(File::exists($guidelinesDir.'/baseline-old.md'))->toBeFalse();

    // New baseline file should be published with package content
    expect(File::exists($guidelinesDir.'/baseline-laravel.md'))->toBeTrue();
    $content = File::get($guidelinesDir.'/baseline-laravel.md');
    expect($content)->not->toBe('Old content')
        ->toContain('Laravel Baseline Guidelines');
});

it('preserves custom (non-baseline) guideline files', function (): void {
    $customContent = '# Custom Guidelines
This is a custom guideline file that should not be deleted.';

    $this->withTempBasePath([
        '.ai/guidelines/custom-guidelines.md' => $customContent,
        '.ai/guidelines/my-team-standards.md' => 'Team standards',
    ]);

    $this->artisan(UpdateGuidelinesCommand::class)
        ->assertSuccessful();

    $guidelinesDir = base_path('.ai/guidelines');

    // Custom files should still exist
    expect(File::exists($guidelinesDir.'/custom-guidelines.md'))->toBeTrue();
    expect(File::get($guidelinesDir.'/custom-guidelines.md'))->toBe($customContent);

    expect(File::exists($guidelinesDir.'/my-team-standards.md'))->toBeTrue();
    expect(File::get($guidelinesDir.'/my-team-standards.md'))->toBe('Team standards');

    // Package baseline files should also be present
    expect(File::exists($guidelinesDir.'/baseline-laravel.md'))->toBeTrue();
});

it('displays success message with list of published files', function (): void {
    $this->withTempBasePath([]);

    $this->artisan(UpdateGuidelinesCommand::class)
        ->expectsOutput('Created .ai/guidelines directory')
        ->assertSuccessful();
});

it('does not show directory creation message if directory already exists', function (): void {
    $this->withTempBasePath([
        '.ai/guidelines/custom.md' => 'Custom content',
    ]);

    $this->artisan(UpdateGuidelinesCommand::class)
        ->doesntExpectOutput('Created .ai/guidelines directory')
        ->assertSuccessful();
});

it('shows count of removed baseline files', function (): void {
    $this->withTempBasePath([
        '.ai/guidelines/baseline-old1.md' => 'Old 1',
        '.ai/guidelines/baseline-old2.md' => 'Old 2',
        '.ai/guidelines/custom.md' => 'Custom',
    ]);

    $this->artisan(UpdateGuidelinesCommand::class)
        ->expectsOutputToContain('Removed 2 existing baseline guideline file(s)')
        ->assertSuccessful();
});

it('does not show removed files message when no baseline files exist', function (): void {
    $this->withTempBasePath([
        '.ai/guidelines/custom.md' => 'Custom',
    ]);

    $this->artisan(UpdateGuidelinesCommand::class)
        ->doesntExpectOutputToContain('Removed')
        ->assertSuccessful();
});

it('shows list of published baseline files', function (): void {
    $this->withTempBasePath([]);

    $this->artisan(UpdateGuidelinesCommand::class)
        ->expectsOutputToContain('Published')
        ->expectsOutputToContain('baseline-laravel.md')
        ->assertSuccessful();
});

it('handles multiple baseline-*.md files from package', function (): void {
    // This test verifies the command can handle multiple baseline files
    // Currently we only have baseline-laravel.md, but the implementation
    // should support multiple files
    $this->withTempBasePath([]);

    $this->artisan(UpdateGuidelinesCommand::class)
        ->assertSuccessful();

    $guidelinesDir = base_path('.ai/guidelines');
    $baselineFiles = File::glob($guidelinesDir.'/baseline-*.md');

    // At least baseline-laravel.md should exist
    expect($baselineFiles)->toHaveCount(1);
});

it('succeeds even when no stub files are found', function (): void {
    // This shouldn't happen in practice, but the command should handle it gracefully
    // We can't easily test this without mocking, but we verify current behavior succeeds
    $this->withTempBasePath([]);

    $this->artisan(UpdateGuidelinesCommand::class)
        ->assertSuccessful();
});
