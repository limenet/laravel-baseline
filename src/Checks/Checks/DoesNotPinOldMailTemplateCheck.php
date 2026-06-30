<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class DoesNotPinOldMailTemplateCheck extends AbstractCheck
{
    /**
     * Published mail views that pin the pre-modernization template. The
     * modernization (laravel/framework#57987) restyled `themes/default.css` and
     * bumped the logo in `header.blade.php`; a published copy of either keeps the
     * project on the old template, since `vendor:publish` won't overwrite them.
     */
    private const PINNED_VIEWS = [
        'resources/views/vendor/mail/html/themes/default.css',
        'resources/views/vendor/mail/html/header.blade.php',
    ];

    public function check(): CheckResult
    {
        foreach (self::PINNED_VIEWS as $view) {
            if (file_exists(base_path($view))) {
                $this->addComment("Published mail view pins the old template: Delete {$view} (re-publish via `ddev artisan vendor:publish --tag=laravel-mail --force` only if you maintain customizations) to adopt the modernized mail template");

                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }
}
