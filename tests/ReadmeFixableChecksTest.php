<?php

use Limenet\LaravelBaseline\Checks\CheckRegistry;
use Limenet\LaravelBaseline\Checks\FixableInterface;

it('all fixable checks are marked with 🔧 in README.md', function (): void {
    $readme = file_get_contents(dirname(__DIR__).'/README.md');
    expect($readme)->not->toBeFalse();

    // Extract check names preceded by 🔧
    preg_match_all('/🔧.*?\*\*`([a-zA-Z]+)\(\)`\*\*/', $readme, $matches);
    $markedNames = $matches[1];

    $fixableNames = array_map(
        fn (string $class) => $class::name(),
        array_filter(
            CheckRegistry::all(),
            fn (string $class) => is_a($class, FixableInterface::class, true),
        ),
    );

    $unmarked = array_diff($fixableNames, $markedNames);

    expect($unmarked)->toBe([], implode(', ', array_map(
        fn (string $name) => "`{$name}()`",
        $unmarked,
    )).' implement FixableInterface but are not marked with 🔧 in README.md');
});

it('no non-fixable check is incorrectly marked with 🔧 in README.md', function (): void {
    $readme = file_get_contents(dirname(__DIR__).'/README.md');
    expect($readme)->not->toBeFalse();

    preg_match_all('/🔧.*?\*\*`([a-zA-Z]+)\(\)`\*\*/', $readme, $matches);
    $markedNames = $matches[1];

    $nonFixableNames = array_map(
        fn (string $class) => $class::name(),
        array_filter(
            CheckRegistry::all(),
            fn (string $class) => !is_a($class, FixableInterface::class, true),
        ),
    );

    $wronglyMarked = array_intersect($nonFixableNames, $markedNames);

    expect($wronglyMarked)->toBe([], implode(', ', array_map(
        fn (string $name) => "`{$name}()`",
        $wronglyMarked,
    )).' are marked with 🔧 in README.md but do not implement FixableInterface');
});
