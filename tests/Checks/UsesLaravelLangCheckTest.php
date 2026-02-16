<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesLaravelLangCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesLaravelLang fails when package is missing', function (): void {
    $composer = [
        'require-dev' => [],
        'scripts' => [],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(UsesLaravelLangCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesLaravelLang fails when package is in require instead of require-dev', function (): void {
    $composer = [
        'require' => ['laravel-lang/lang' => '^15.0'],
        'require-dev' => [],
        'scripts' => [
            'post-update-cmd' => [
                'php artisan lang:update',
                './vendor/bin/pint --dirty',
            ],
        ],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(UsesLaravelLangCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesLaravelLang fails when lang:update script is missing', function (): void {
    $composer = [
        'require-dev' => ['laravel-lang/lang' => '^15.0'],
        'scripts' => [],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(UsesLaravelLangCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesLaravelLang fails when pint --dirty script is missing', function (): void {
    $composer = [
        'require-dev' => ['laravel-lang/lang' => '^15.0'],
        'scripts' => [
            'post-update-cmd' => [
                'php artisan lang:update',
            ],
        ],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(UsesLaravelLangCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesLaravelLang passes when properly configured', function (): void {
    $composer = [
        'require-dev' => ['laravel-lang/lang' => '^15.0'],
        'scripts' => [
            'post-update-cmd' => [
                'php artisan lang:update',
                './vendor/bin/pint --dirty',
            ],
        ],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(UsesLaravelLangCheck::class)->check())->toBe(CheckResult::PASS);
});

it('usesLaravelLang provides helpful comment when package is missing', function (): void {
    $composer = [
        'require-dev' => [],
        'scripts' => [],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    [$check, $collector] = makeCheckWithCollector(UsesLaravelLangCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing dev dependency in composer.json: Add "laravel-lang/lang" to require-dev');
});

it('usesLaravelLang provides helpful comment when lang:update script is missing', function (): void {
    $composer = [
        'require-dev' => ['laravel-lang/lang' => '^15.0'],
        'scripts' => [],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    [$check, $collector] = makeCheckWithCollector(UsesLaravelLangCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing script in composer.json: Add "php artisan lang:update" to post-update-cmd section');
});

it('usesLaravelLang provides helpful comment when pint --dirty script is missing', function (): void {
    $composer = [
        'require-dev' => ['laravel-lang/lang' => '^15.0'],
        'scripts' => [
            'post-update-cmd' => [
                'php artisan lang:update',
            ],
        ],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    [$check, $collector] = makeCheckWithCollector(UsesLaravelLangCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing script in composer.json: Add "./vendor/bin/pint --dirty" to post-update-cmd section');
});
