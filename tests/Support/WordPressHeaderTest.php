<?php

use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Support\WordPressHeader;

it('reads headers the way get_file_data does', function (string $contents, ?string $expected): void {
    $project = makeProject(Profile::WordPress, ['style.css' => $contents]);

    expect(WordPressHeader::read($project->path('style.css'), 'Version'))->toBe($expected);
})->with([
    'css comment' => ["/*\nTheme Name: X\nVersion: 1.2.3\n*/", '1.2.3'],
    'starred lines' => ["/**\n * Theme Name: X\n * Version:   2.0.0\n */", '2.0.0'],
    'closing marker on the same line' => ['/* Version: 3.1 */', '3.1'],
    'case-insensitive' => ["/*\nversion: 4.0\n*/", '4.0'],
    'absent' => ["/*\nTheme Name: X\n*/", null],
    'empty value' => ["/*\nVersion:\n*/", null],
]);

it('returns null for a missing file', function (): void {
    expect(WordPressHeader::read('/does/not/exist.css', 'Version'))->toBeNull();
});

it('rewrites only the header value', function (): void {
    $project = makeProject(Profile::WordPress, ['style.css' => "/*\nTheme Name: X\n * Version: 1.0.0\n*/\nbody{}\n"]);

    expect(WordPressHeader::write($project->path('style.css'), 'Version', '1.1.0'))->toBeTrue()
        ->and(file_get_contents($project->path('style.css')))->toBe("/*\nTheme Name: X\n * Version: 1.1.0\n*/\nbody{}\n");
});

it('reports a missing header instead of writing one', function (): void {
    $project = makeProject(Profile::WordPress, ['style.css' => "/*\nTheme Name: X\n*/\n"]);

    expect(WordPressHeader::write($project->path('style.css'), 'Version', '1.1.0'))->toBeFalse();
});
