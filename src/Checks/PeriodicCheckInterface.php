<?php

namespace Limenet\LaravelBaseline\Checks;

use Carbon\CarbonInterval;

interface PeriodicCheckInterface extends CheckInterface
{
    public function interval(): CarbonInterval;

    public function isApplicable(): bool;

    public function promptDescription(): string;
}
