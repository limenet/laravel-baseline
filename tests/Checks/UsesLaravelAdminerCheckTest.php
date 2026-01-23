<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesLaravelAdminerCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesLaravelAdminer warns when package not installed', function (): void {
    bindFakeComposer(['onecentlin/laravel-adminer' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesLaravelAdminerCheck::class);
    expect($check->check())->toBe(CheckResult::WARN);
});

it('usesLaravelAdminer fails when TFA package not installed', function (): void {
    bindFakeComposer([
        'onecentlin/laravel-adminer' => true,
        'wnx/laravel-tfa-confirmation' => false,
    ]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesLaravelAdminerCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing package: Install wnx/laravel-tfa-confirmation via "composer require wnx/laravel-tfa-confirmation"');
});

it('usesLaravelAdminer fails when config file missing', function (): void {
    bindFakeComposer([
        'onecentlin/laravel-adminer' => true,
        'wnx/laravel-tfa-confirmation' => true,
    ]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesLaravelAdminerCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Adminer configuration missing: Create config/adminer.php by running "php artisan vendor:publish --provider=\"Onecentlin\\Adminer\\ServiceProvider\""');
});

it('usesLaravelAdminer fails when middleware not set to adminer', function (): void {
    bindFakeComposer([
        'onecentlin/laravel-adminer' => true,
        'wnx/laravel-tfa-confirmation' => true,
    ]);

    $adminerConfig = <<<'PHP'
<?php
return [
    'middleware' => 'web',
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
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/adminer.php' => $adminerConfig,
        'app/Http/Kernel.php' => $kernel,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesLaravelAdminerCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Adminer middleware misconfigured in config/adminer.php: Set "middleware" to "adminer" (found: "web")');
});

it('usesLaravelAdminer fails when middleware is null', function (): void {
    bindFakeComposer([
        'onecentlin/laravel-adminer' => true,
        'wnx/laravel-tfa-confirmation' => true,
    ]);

    $adminerConfig = <<<'PHP'
<?php
return [
    'autologin' => false,
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
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/adminer.php' => $adminerConfig,
        'app/Http/Kernel.php' => $kernel,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesLaravelAdminerCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Adminer middleware misconfigured in config/adminer.php: Set "middleware" to "adminer" (found: null)');
});

it('usesLaravelAdminer fails when kernel missing', function (): void {
    bindFakeComposer([
        'onecentlin/laravel-adminer' => true,
        'wnx/laravel-tfa-confirmation' => true,
    ]);

    $adminerConfig = <<<'PHP'
<?php
return [
    'middleware' => 'adminer',
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/adminer.php' => $adminerConfig,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesLaravelAdminerCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('HTTP Kernel missing: app/Http/Kernel.php not found');
});

it('usesLaravelAdminer fails when adminer middleware group missing', function (): void {
    bindFakeComposer([
        'onecentlin/laravel-adminer' => true,
        'wnx/laravel-tfa-confirmation' => true,
    ]);

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
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
        ],
    ];
}
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/adminer.php' => $adminerConfig,
        'app/Http/Kernel.php' => $kernel,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesLaravelAdminerCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing middleware group in app/Http/Kernel.php: Add "adminer" group to $middlewareGroups array');
});

it('usesLaravelAdminer fails when TFA middleware missing from adminer group', function (): void {
    bindFakeComposer([
        'onecentlin/laravel-adminer' => true,
        'wnx/laravel-tfa-confirmation' => true,
    ]);

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
            \App\Http\Middleware\Authenticate::class,
        ],
    ];
}
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/adminer.php' => $adminerConfig,
        'app/Http/Kernel.php' => $kernel,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesLaravelAdminerCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing TFA middleware in app/Http/Kernel.php: Add Wnx\TfaConfirmation\Http\Middleware\RequireTwoFactorAuthenticationConfirmation::class to the "adminer" middleware group');
});

it('usesLaravelAdminer passes with valid configuration', function (): void {
    bindFakeComposer([
        'onecentlin/laravel-adminer' => true,
        'wnx/laravel-tfa-confirmation' => true,
    ]);

    $adminerConfig = <<<'PHP'
<?php
return [
    'middleware' => 'adminer',
    'autologin' => false,
];
PHP;

    $kernel = <<<'PHP'
<?php

namespace App\Http;

class Kernel
{
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
        ],
        'adminer' => [
            \App\Http\Middleware\Authenticate::class,
            \Wnx\TfaConfirmation\Http\Middleware\RequireTwoFactorAuthenticationConfirmation::class,
        ],
    ];
}
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/adminer.php' => $adminerConfig,
        'app/Http/Kernel.php' => $kernel,
    ]);

    $check = makeCheck(UsesLaravelAdminerCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('usesLaravelAdminer passes with TFA middleware using string reference', function (): void {
    bindFakeComposer([
        'onecentlin/laravel-adminer' => true,
        'wnx/laravel-tfa-confirmation' => true,
    ]);

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
            'Wnx\TfaConfirmation\Http\Middleware\RequireTwoFactorAuthenticationConfirmation',
        ],
    ];
}
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/adminer.php' => $adminerConfig,
        'app/Http/Kernel.php' => $kernel,
    ]);

    $check = makeCheck(UsesLaravelAdminerCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
