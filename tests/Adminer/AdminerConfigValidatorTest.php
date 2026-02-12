<?php

use Limenet\LaravelBaseline\Adminer\AdminerConfigValidator;

it('returns error when config file cannot be read', function (): void {
    $validator = new AdminerConfigValidator();

    $this->withTempBasePath([
        'app/Http/Kernel.php' => '<?php class Kernel {}',
    ]);

    $errors = $validator->validate(
        base_path('config/adminer.php'),
        base_path('app/Http/Kernel.php'),
    );

    expect($errors)->toContain('Adminer configuration missing: Create config/adminer.php by running "php artisan vendor:publish --provider=\"Onecentlin\\Adminer\\ServiceProvider\""');
});

it('returns error when config is empty/unparsable', function (): void {
    $validator = new AdminerConfigValidator();

    $this->withTempBasePath([
        'config/adminer.php' => '<?php // empty config, no return',
        'app/Http/Kernel.php' => '<?php class Kernel {}',
    ]);

    $errors = $validator->validate(
        base_path('config/adminer.php'),
        base_path('app/Http/Kernel.php'),
    );

    expect($errors)->toContain('Adminer configuration invalid: Unable to parse config/adminer.php');
});

it('returns error when kernel file cannot be read', function (): void {
    $validator = new AdminerConfigValidator();

    $adminerConfig = <<<'PHP'
<?php
return [
    'middleware' => 'adminer',
];
PHP;

    $this->withTempBasePath([
        'config/adminer.php' => $adminerConfig,
    ]);

    $errors = $validator->validate(
        base_path('config/adminer.php'),
        base_path('app/Http/Kernel.php'),
    );

    expect($errors)->toContain('HTTP Kernel missing: app/Http/Kernel.php not found');
});

it('returns error when kernel has no middlewareGroups property', function (): void {
    $validator = new AdminerConfigValidator();

    $adminerConfig = <<<'PHP'
<?php
return [
    'middleware' => 'adminer',
];
PHP;

    $kernel = <<<'PHP'
<?php
namespace App\Http;
class Kernel
{
    protected $middleware = [];
}
PHP;

    $this->withTempBasePath([
        'config/adminer.php' => $adminerConfig,
        'app/Http/Kernel.php' => $kernel,
    ]);

    $errors = $validator->validate(
        base_path('config/adminer.php'),
        base_path('app/Http/Kernel.php'),
    );

    expect($errors)->toContain('HTTP Kernel invalid: Unable to parse $middlewareGroups from app/Http/Kernel.php');
});

it('returns error when kernel is unparsable PHP', function (): void {
    $validator = new AdminerConfigValidator();

    $adminerConfig = <<<'PHP'
<?php
return [
    'middleware' => 'adminer',
];
PHP;

    $this->withTempBasePath([
        'config/adminer.php' => $adminerConfig,
        'app/Http/Kernel.php' => '<?php this is not valid php {{{',
    ]);

    $errors = $validator->validate(
        base_path('config/adminer.php'),
        base_path('app/Http/Kernel.php'),
    );

    // Kernel parse error should yield null from the visitor, triggering the "invalid" error
    expect($errors)->toContain('HTTP Kernel invalid: Unable to parse $middlewareGroups from app/Http/Kernel.php');
});

it('returns error when adminer middleware group is not an array', function (): void {
    $validator = new AdminerConfigValidator();

    $adminerConfig = <<<'PHP'
<?php
return [
    'middleware' => 'adminer',
];
PHP;

    $kernel = <<<'PHP'
<?php
namespace App\Http;
class Kernel
{
    protected $middlewareGroups = [
        'adminer' => 'not-an-array',
    ];
}
PHP;

    $this->withTempBasePath([
        'config/adminer.php' => $adminerConfig,
        'app/Http/Kernel.php' => $kernel,
    ]);

    $errors = $validator->validate(
        base_path('config/adminer.php'),
        base_path('app/Http/Kernel.php'),
    );

    expect($errors)->toContain('Invalid middleware group in app/Http/Kernel.php: "adminer" group must be an array');
});

it('returns no errors for valid configuration', function (): void {
    $validator = new AdminerConfigValidator();

    $adminerConfig = <<<'PHP'
<?php
return [
    'middleware' => 'adminer',
];
PHP;

    $kernel = <<<'PHP'
<?php
namespace App\Http;
class Kernel
{
    protected $middlewareGroups = [
        'adminer' => [
            \Wnx\TfaConfirmation\Http\Middleware\RequireTwoFactorAuthenticationConfirmation::class,
        ],
    ];
}
PHP;

    $this->withTempBasePath([
        'config/adminer.php' => $adminerConfig,
        'app/Http/Kernel.php' => $kernel,
    ]);

    $errors = $validator->validate(
        base_path('config/adminer.php'),
        base_path('app/Http/Kernel.php'),
    );

    expect($errors)->toBe([]);
});
