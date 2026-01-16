<?php

use Illuminate\Support\Facades\Schedule;
use Limenet\LaravelBaseline\Checks\Checks\UsesLaravelTelescopeCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesLaravelTelescope requires package, post-update script and schedule', function (): void {
    // Missing package -> FAIL
    bindFakeComposer(['laravel/telescope' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(UsesLaravelTelescopeCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    // With package but missing script/schedule -> FAIL
    bindFakeComposer(['laravel/telescope' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(UsesLaravelTelescopeCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    // With script but missing schedule -> FAIL
    bindFakeComposer(['laravel/telescope' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan telescope:publish']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(UsesLaravelTelescopeCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    // With script and schedule but missing phpunit.xml TELESCOPE_ENABLED -> FAIL
    bindFakeComposer(['laravel/telescope' => true]);
    $phpunitXml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <phpunit>
        <php>
            <env name="APP_KEY" value="base64:test"/>
        </php>
    </phpunit>
    XML;
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'phpunit.xml' => $phpunitXml,
    ]);

    Schedule::command('telescope:prune');

    $check = makeCheck(UsesLaravelTelescopeCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    // With script, schedule and phpunit.xml TELESCOPE_ENABLED = false -> PASS
    bindFakeComposer(['laravel/telescope' => true]);
    $phpunitXml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <phpunit>
        <php>
            <env name="APP_KEY" value="base64:test"/>
            <env name="TELESCOPE_ENABLED" value="false"/>
        </php>
    </phpunit>
    XML;
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'phpunit.xml' => $phpunitXml,
    ]);

    Schedule::command('telescope:prune');

    $check = makeCheck(UsesLaravelTelescopeCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
