<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class CallsSentryHookCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if (!$this->checkComposerPackages('sentry/sentry-laravel')) {
            return CheckResult::WARN;
        }

        $data = $this->getGitlabCiData();

        if (
            ($data['release']['extends'][0] ?? null) !== '.release'
            || !str_starts_with($data['release']['variables']['SENTRY_RELEASE_WEBHOOK'] ?? '', 'https://sentry.io/api/hooks/release/builtin/')
        ) {
            $this->addComment('Sentry release hook missing or misconfigured in .gitlab-ci.yml: Job "release" must extend ".release" and set SENTRY_RELEASE_WEBHOOK variable to a valid Sentry webhook URL');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
