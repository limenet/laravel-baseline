<?php

use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Project\ProfileDetectionException;
use Limenet\LaravelBaseline\Project\ProfileDetector;

function detectProfile(array $files): Profile
{
    return ProfileDetector::detect(makeProject(Profile::Php, $files)->path());
}

it('detects a plain composer project as php', function (): void {
    expect(detectProfile(['composer.json' => json_encode(['name' => 'acme/lib'])]))->toBe(Profile::Php);
});

it('detects a WordPress theme by its style.css header', function (): void {
    expect(detectProfile([
        'composer.json' => json_encode(['type' => 'project']),
        'style.css' => "/*\nTheme Name: JUST Gallery\nTemplate: astra\nVersion: 1.0.0\n*/\n",
    ]))->toBe(Profile::WordPress);
});

it('detects a WordPress plugin by its main file header', function (): void {
    expect(detectProfile([
        'my-plugin.php' => "<?php\n/**\n * Plugin Name: My Plugin\n */\n",
    ]))->toBe(Profile::WordPress);
});

it('detects WordPress by composer type', function (string $type): void {
    expect(detectProfile(['composer.json' => json_encode(['type' => $type])]))->toBe(Profile::WordPress);
})->with(['wordpress-theme', 'wordpress-plugin', 'wordpress-muplugin']);

it('does not mistake a stylesheet without a theme header for a theme', function (): void {
    expect(detectProfile(['style.css' => "body { color: red; }\n"]))->toBe(Profile::Php);
});

it('honours a profile override in .baseline.json', function (): void {
    expect(detectProfile([
        'style.css' => "/*\nTheme Name: X\n*/\n",
        '.baseline.json' => json_encode(['profile' => 'php']),
    ]))->toBe(Profile::Php);
});

it('refuses a Laravel application', function (array $files, string $reason): void {
    expect(fn () => detectProfile($files))->toThrow(ProfileDetectionException::class, $reason);
})->with([
    'artisan' => [['artisan' => '#!/usr/bin/env php'], 'artisan file'],
    'framework in require' => [['composer.json' => json_encode(['require' => ['laravel/framework' => '^13.0']])], 'requires laravel/framework'],
    'laravel override' => [['.baseline.json' => json_encode(['profile' => 'laravel'])], '"profile": "laravel"'],
    'artisan beats an override' => [['artisan' => '', '.baseline.json' => json_encode(['profile' => 'php'])], 'artisan file'],
]);

it('accepts a Laravel package that only develops against the framework', function (): void {
    expect(detectProfile(['composer.json' => json_encode(['require-dev' => ['laravel/framework' => '^13.0']])]))->toBe(Profile::Php);
});

it('rejects an unknown profile override', function (): void {
    expect(fn () => detectProfile(['.baseline.json' => json_encode(['profile' => 'drupal'])]))
        ->toThrow(ProfileDetectionException::class, 'Unknown "profile" "drupal"');
});
