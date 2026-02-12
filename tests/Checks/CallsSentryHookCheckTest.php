<?php

use Limenet\LaravelBaseline\Checks\Checks\CallsSentryHookCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('callsSentryHook behaves based on package and YAML configuration', function (): void {
    // WARN when sentry not installed
    bindFakeComposer(['sentry/sentry-laravel' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp']), '.gitlab-ci.yml' => '']);

    $check = makeCheck(CallsSentryHookCheck::class);
    expect($check->check())->toBe(CheckResult::WARN);

    // FAIL when installed but wrong config
    bindFakeComposer(['sentry/sentry-laravel' => true]);
    $yamlFail = "release:\n  extends: ['.wrong']\n  variables:\n    SENTRY_RELEASE_WEBHOOK: 'https://example.com'\n";
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp']), '.gitlab-ci.yml' => $yamlFail]);

    $check = makeCheck(CallsSentryHookCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    // PASS when installed and correct config
    bindFakeComposer(['sentry/sentry-laravel' => true]);
    $yamlOk = "release:\n  extends: ['.release']\n  variables:\n    SENTRY_RELEASE_WEBHOOK: 'https://sentry.io/api/hooks/release/builtin/abc'\n";
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp']), '.gitlab-ci.yml' => $yamlOk]);

    $check = makeCheck(CallsSentryHookCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('callsSentryHook fails when .gitlab-ci.yml is missing', function (): void {
    bindFakeComposer(['sentry/sentry-laravel' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(CallsSentryHookCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('GitLab CI configuration missing: Create .gitlab-ci.yml in project root');
});

it('callsSentryHook fails when .gitlab-ci.yml is empty', function (): void {
    bindFakeComposer(['sentry/sentry-laravel' => true]);
    $this->withTempBasePath(['.gitlab-ci.yml' => '', 'composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(CallsSentryHookCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('GitLab CI configuration is empty or invalid: Check .gitlab-ci.yml');
});
