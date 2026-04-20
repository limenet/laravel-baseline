<?php

use Limenet\LaravelBaseline\Checks\CheckRegistry;

it('all registered checks are documented in README.md', function (): void {
    $readme = file_get_contents(dirname(__DIR__).'/README.md');
    expect($readme)->not->toBeFalse();

    preg_match_all('/\*\*`([a-zA-Z]+)\(\)`\*\*/', $readme, $matches);
    $documentedNames = $matches[1];

    $registeredNames = array_map(
        fn (string $class) => $class::name(),
        CheckRegistry::all(),
    );

    $missing = array_diff($registeredNames, $documentedNames);

    expect($missing)->toBe([], implode(', ', array_map(
        fn (string $name) => "`{$name}()`",
        $missing,
    )).' '.count($missing) === 1 ? 'is' : 'are'.' registered but not documented in README.md');
});
