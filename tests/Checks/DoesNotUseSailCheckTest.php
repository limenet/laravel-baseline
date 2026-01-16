<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotUseSailCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('doesNotUseSail passes when sail is not installed and docker-compose.yml is missing', function (): void {
    bindFakeComposer(['laravel/sail' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(DoesNotUseSailCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('doesNotUseSail fails when sail package is installed', function (): void {
    bindFakeComposer(['laravel/sail' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(DoesNotUseSailCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('doesNotUseSail fails when docker-compose.yml exists', function (): void {
    bindFakeComposer(['laravel/sail' => false]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'docker-compose.yml' => 'version: "3"',
    ]);

    $check = makeCheck(DoesNotUseSailCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('doesNotUseSail fails when both sail package and docker-compose.yml exist', function (): void {
    bindFakeComposer(['laravel/sail' => true]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'docker-compose.yml' => 'version: "3"',
    ]);

    $check = makeCheck(DoesNotUseSailCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});
