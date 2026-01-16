<?php

use Illuminate\Support\Facades\Schedule;
use Limenet\LaravelBaseline\Checks\Checks\UsesLaravelPulseCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesLaravelPulse checks scheduled pulse:trim', function (): void {
    // FAIL when not installed
    bindFakeComposer(['laravel/pulse' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(UsesLaravelPulseCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    // FAIL when installed but not scheduled
    bindFakeComposer(['laravel/pulse' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    // no schedule

    $check = makeCheck(UsesLaravelPulseCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    // FAIL when scheduled but phpunit.xml missing PULSE_ENABLED = false
    bindFakeComposer(['laravel/pulse' => true]);
    $phpunitXml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <phpunit>
        <php>
            <env name="APP_KEY" value="base64:test"/>
        </php>
    </phpunit>
    XML;
    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'phpunit.xml' => $phpunitXml,
    ]);

    Schedule::command('pulse:trim');

    $check = makeCheck(UsesLaravelPulseCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    // PASS when scheduled and phpunit.xml has PULSE_ENABLED = false
    bindFakeComposer(['laravel/pulse' => true]);
    $phpunitXml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <phpunit>
        <php>
            <env name="APP_KEY" value="base64:test"/>
            <env name="PULSE_ENABLED" value="false"/>
        </php>
    </phpunit>
    XML;
    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'phpunit.xml' => $phpunitXml,
    ]);

    Schedule::command('pulse:trim');

    $check = makeCheck(UsesLaravelPulseCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('usesLaravelPulse fails when phpunit.xml is missing', function (): void {
    bindFakeComposer(['laravel/pulse' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    Schedule::command('pulse:trim');

    $check = makeCheck(UsesLaravelPulseCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});
