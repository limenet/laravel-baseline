<?php

namespace Limenet\LaravelBaseline\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;

abstract class AbstractFixableCheck extends AbstractCheck implements FixableInterface
{
    public function check(): CheckResult
    {
        return $this->fix(dry: true);
    }
}
