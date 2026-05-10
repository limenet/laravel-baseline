<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;

class FormRequestFailOnUnknownFieldsCheck extends AbstractServiceProviderStaticCallCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        if (!$this->composerPackageSatisfies('laravel/framework', '>=13.6.0')) {
            return CheckResult::WARN;
        }

        return parent::fix($dry);
    }

    protected function staticClassName(): string
    {
        return 'FormRequest';
    }

    protected function staticMethodName(): string
    {
        return 'failOnUnknownFields';
    }

    protected function missingCallComment(): string
    {
        return 'Missing FormRequest::failOnUnknownFields() call in AppServiceProvider';
    }

    protected function falseLiteralComment(): string
    {
        return 'Do not pass false to FormRequest::failOnUnknownFields(); use true, no argument, or a dynamic expression';
    }

    protected function fixStatement(): string
    {
        return 'FormRequest::failOnUnknownFields(! app()->isProduction());';
    }

    protected function fixImports(): array
    {
        return ['Illuminate\\Foundation\\Http\\FormRequest'];
    }
}
