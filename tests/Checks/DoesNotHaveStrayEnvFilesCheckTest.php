<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotHaveStrayEnvFilesCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('doesNotHaveStrayEnvFiles passes when only allowed env files exist', function (): void {
    $this->withTempBasePath([
        '.env' => 'APP_KEY=x',
        '.env.example' => 'APP_KEY=',
        '.env.testing' => 'APP_KEY=x',
        '.env.production.encrypted' => 'APP_KEY=eyJ',
    ]);

    expect(makeCheck(DoesNotHaveStrayEnvFilesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotHaveStrayEnvFiles passes when there are no env files at all', function (): void {
    $this->withTempBasePath(['composer.json' => '{}']);

    expect(makeCheck(DoesNotHaveStrayEnvFilesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotHaveStrayEnvFiles fails on a plaintext per-environment env file, even when gitignored', function (): void {
    $this->withTempBasePath([
        '.gitignore' => ".env.production\n",
        '.env.production' => 'APP_KEY=secret',
        '.env.production.encrypted' => 'APP_KEY=eyJ',
    ]);

    [$check, $collector] = makeCheckWithCollector(DoesNotHaveStrayEnvFilesCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toHaveCount(1);
    expect($collector->all()[0])->toStartWith('Remove .env.production — ')
        ->toContain('env:encrypt --readable');
});

it('doesNotHaveStrayEnvFiles reports every stray file', function (): void {
    $this->withTempBasePath([
        '.env.backup' => 'APP_KEY=old',
        '.env.staging' => 'APP_KEY=secret',
    ]);

    [$check, $collector] = makeCheckWithCollector(DoesNotHaveStrayEnvFilesCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toHaveCount(2);
});

it('doesNotHaveStrayEnvFiles ignores env files outside the project root', function (): void {
    $this->withTempBasePath(['tests/fixtures/.env.production' => 'APP_KEY=x']);

    expect(makeCheck(DoesNotHaveStrayEnvFilesCheck::class)->check())->toBe(CheckResult::PASS);
});
