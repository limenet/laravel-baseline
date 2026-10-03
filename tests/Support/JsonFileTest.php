<?php

use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Support\JsonFile;

it('writes an emptied object back as {} rather than []', function (): void {
    $project = makeProject(Profile::Php, ['composer.json' => json_encode([
        'name' => 'a/b',
        'require-dev' => ['friendsofphp/php-cs-fixer' => '^3'],
        'config' => new stdClass,
        'keywords' => [],
        'autoload' => ['psr-4' => new stdClass],
    ])]);
    $file = $project->path('composer.json');

    $data = json_decode((string) file_get_contents($file), true);
    unset($data['require-dev']['friendsofphp/php-cs-fixer']);
    JsonFile::write($file, $data);

    expect(json_decode((string) file_get_contents($file)))->toEqual((object) [
        'name' => 'a/b',
        'require-dev' => new stdClass,
        'config' => new stdClass,
        'keywords' => [],
        'autoload' => (object) ['psr-4' => new stdClass],
    ]);
});

it('keeps the indent of an existing file', function (string $indent): void {
    $project = makeProject(Profile::Php, ['package.json' => "{\n{$indent}\"name\": \"app\"\n}\n"]);
    $file = $project->path('package.json');

    JsonFile::write($file, ['name' => 'app', 'scripts' => ['test' => 'vitest']]);

    expect(file_get_contents($file))->toBe(
        "{\n{$indent}\"name\": \"app\",\n{$indent}\"scripts\": {\n{$indent}{$indent}\"test\": \"vitest\"\n{$indent}}\n}\n",
    );
})->with([
    'two spaces' => '  ',
    'tabs' => "\t",
]);

it('writes a new file as given', function (): void {
    $file = makeProject(Profile::Php)->path('new.json');

    JsonFile::write($file, ['a' => [], 'b' => ['x' => 1]]);

    expect(file_get_contents($file))->toBe("{\n    \"a\": [],\n    \"b\": {\n        \"x\": 1\n    }\n}\n");
});
