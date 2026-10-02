<?php

use Limenet\LaravelBaseline\Checks\Checks\HasRectorConfigWithPhpSetsCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesPestCheck;
use Limenet\LaravelBaseline\Support\CheckName;
use Limenet\LaravelBaseline\Support\Filesystem;

it('derives the check name from the class name', function (): void {
    expect(CheckName::fromClass(UsesPestCheck::class))->toBe('usesPest')
        ->and(CheckName::fromClass(HasRectorConfigWithPhpSetsCheck::class))->toBe('hasRectorConfigWithPhpSets');
});

it('displays a check name the way Str::ucsplit did', function (string $name): void {
    expect(CheckName::display($name))->toBe(str($name)->ucsplit()->implode(' '));
})->with(['usesPest', 'bumpsComposer', 'phpVersionMatchesCi', 'doesNotUseAPIs', 'x']);

it('deletes a directory tree', function (): void {
    $this->withTempBasePath([
        '.junie/guidelines.md' => 'x',
        '.junie/nested/deeper/file.txt' => 'y',
    ]);

    Filesystem::deleteDirectory(base_path('.junie'));

    expect(is_dir(base_path('.junie')))->toBeFalse();
});
