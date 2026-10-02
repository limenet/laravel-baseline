<?php

use Limenet\LaravelBaseline\Checks\CheckRegistry;
use Limenet\LaravelBaseline\Project\Profile;

it('documents exactly the checks the standalone runner runs, with their profiles', function (): void {
    $readme = (string) file_get_contents(dirname(__DIR__).'/README.md');
    $start = strpos($readme, '### What the standalone runner checks');

    expect($start)->not->toBeFalse();

    $section = substr($readme, (int) $start, (int) strpos($readme, "\n## ", (int) $start) - (int) $start);

    preg_match_all('/^\| `([a-zA-Z]+)` \| (✓?) \| (✓?) \|/mu', $section, $rows, PREG_SET_ORDER);

    $documented = [];

    foreach ($rows as [, $name, $php, $wordpress]) {
        $documented[$name] = [$php === '✓', $wordpress === '✓'];
    }

    $expected = [];

    foreach ([...CheckRegistry::for(Profile::Php), ...CheckRegistry::for(Profile::WordPress)] as $class) {
        $expected[$class::name()] = [
            in_array(Profile::Php, $class::profiles(), true),
            in_array(Profile::WordPress, $class::profiles(), true),
        ];
    }

    ksort($documented);
    ksort($expected);

    expect($documented)->toBe($expected);
});
