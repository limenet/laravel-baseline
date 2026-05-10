<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;

class FormRequestFailOnUnknownFieldsCheck extends AbstractServiceProviderStaticCallCheck
{
    public function check(): CheckResult
    {
        if (!$this->composerPackageSatisfies('laravel/framework', '>=13.6.0')) {
            return CheckResult::WARN;
        }

        return parent::check();
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
}
