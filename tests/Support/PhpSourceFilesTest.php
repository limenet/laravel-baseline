<?php

use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Support\PhpSourceFiles;

it('lists the project PHP files, skipping dependencies, dot-directories and git-ignored files', function (): void {
    $project = makeProject(Profile::WordPress, [
        'functions.php' => "<?php\n",
        'inc/setup.php' => "<?php\n",
        'inc/style.css' => '',
        'vendor/acme/lib/src/Lib.php' => "<?php\n",
        'node_modules/x/y.php' => "<?php\n",
        '.ddev/commands/web/x.php' => "<?php\n",
        'public/build.php' => "<?php\n",
        '.gitignore' => "/public/\n",
    ]);

    expect(PhpSourceFiles::in($project->path(), ['vendor', 'node_modules']))->toBe(['functions.php', 'inc/setup.php']);
});

it('matches files against configured paths', function (string $path, string $file, bool $covered): void {
    expect(PhpSourceFiles::covers($path, $file))->toBe($covered);
})->with([
    'exact file' => ['functions.php', 'functions.php', true],
    'directory' => ['inc', 'inc/setup.php', true],
    'directory with slash' => ['./inc/', 'inc/setup.php', true],
    'sibling prefix' => ['inc', 'includes/x.php', false],
    'root' => ['.', 'functions.php', true],
    'empty' => ['', 'inc/setup.php', true],
    'glob' => ['inc/*.php', 'inc/setup.php', true],
    'glob directory' => ['templates/*', 'templates/parts/a.php', true],
    'other file' => ['app', 'functions.php', false],
]);
