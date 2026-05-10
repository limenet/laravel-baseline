<?php

use Limenet\LaravelBaseline\Checks\Checks\FormRequestFailOnUnknownFieldsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('formRequestFailOnUnknownFields warns when Laravel is older than 13.6', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp', 'require' => ['laravel/framework' => '^12.0']]),
    ]);

    expect(makeCheck(FormRequestFailOnUnknownFieldsCheck::class)->check())->toBe(CheckResult::WARN);
});

it('formRequestFailOnUnknownFields fails when AppServiceProvider does not exist', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp', 'require' => ['laravel/framework' => '^13.6']]),
    ]);

    [$check, $collector] = makeCheckWithCollector(FormRequestFailOnUnknownFieldsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing FormRequest::failOnUnknownFields() call in AppServiceProvider');
});

it('formRequestFailOnUnknownFields fails when FormRequest::failOnUnknownFields() is not called', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp', 'require' => ['laravel/framework' => '^13.6']]),
        'app/Providers/AppServiceProvider.php' => '<?php class AppServiceProvider {}',
    ]);

    [$check, $collector] = makeCheckWithCollector(FormRequestFailOnUnknownFieldsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing FormRequest::failOnUnknownFields() call in AppServiceProvider');
});

it('formRequestFailOnUnknownFields fails when called with false', function (): void {
    bindFakeComposer([]);

    $provider = <<<'PHP'
<?php
FormRequest::failOnUnknownFields(false);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp', 'require' => ['laravel/framework' => '^13.6']]),
        'app/Providers/AppServiceProvider.php' => $provider,
    ]);

    [$check, $collector] = makeCheckWithCollector(FormRequestFailOnUnknownFieldsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Do not pass false to FormRequest::failOnUnknownFields(); use true, no argument, or a dynamic expression');
});

it('formRequestFailOnUnknownFields passes when called with no argument', function (): void {
    bindFakeComposer([]);

    $provider = <<<'PHP'
<?php
FormRequest::failOnUnknownFields();
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp', 'require' => ['laravel/framework' => '^13.6']]),
        'app/Providers/AppServiceProvider.php' => $provider,
    ]);

    expect(makeCheck(FormRequestFailOnUnknownFieldsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('formRequestFailOnUnknownFields passes when called with true', function (): void {
    bindFakeComposer([]);

    $provider = <<<'PHP'
<?php
FormRequest::failOnUnknownFields(true);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp', 'require' => ['laravel/framework' => '^13.6']]),
        'app/Providers/AppServiceProvider.php' => $provider,
    ]);

    expect(makeCheck(FormRequestFailOnUnknownFieldsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('formRequestFailOnUnknownFields passes when called with a dynamic expression', function (): void {
    bindFakeComposer([]);

    $provider = <<<'PHP'
<?php
FormRequest::failOnUnknownFields(!app()->isProduction());
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp', 'require' => ['laravel/framework' => '^13.6']]),
        'app/Providers/AppServiceProvider.php' => $provider,
    ]);

    expect(makeCheck(FormRequestFailOnUnknownFieldsCheck::class)->check())->toBe(CheckResult::PASS);
});
