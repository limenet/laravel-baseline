<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesReadableEncryptedEnvFileCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesReadableEncryptedEnvFile passes when an encrypted file uses readable line format', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath(['.env.prod.encrypted' => "APP_NAME=eyJpdiI6abc\nAPP_KEY=eyJpdiI6def\n"]);

    expect(makeCheck(UsesReadableEncryptedEnvFileCheck::class)->check())->toBe(CheckResult::PASS);
});

it('usesReadableEncryptedEnvFile fails when the encrypted file is an opaque blob', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath(['.env.prod.encrypted' => 'eyJpdiI6Ik5kMHNX...onelongbase64blobwithnoequalslines']);

    expect(makeCheck(UsesReadableEncryptedEnvFileCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesReadableEncryptedEnvFile passes when no encrypted file exists (existence is HasEncryptedEnvFileCheck\'s concern)', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    expect(makeCheck(UsesReadableEncryptedEnvFileCheck::class)->check())->toBe(CheckResult::PASS);
});

it('usesReadableEncryptedEnvFile provides a helpful comment when the file is a blob', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath(['.env.prod.encrypted' => 'eyJpdiI6Ik5kMHNX...onelongbase64blobwithnoequalslines']);

    [$check, $collector] = makeCheckWithCollector(UsesReadableEncryptedEnvFileCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect(collect($collector->all())->contains(fn ($c) => str_contains($c, 'opaque blob format')))->toBeTrue();
});
