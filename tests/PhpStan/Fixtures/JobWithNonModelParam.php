<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Tests\PhpStan\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;

class JobWithNonModelParam implements ShouldQueue
{
    public function __construct(public string $name, public int $count) {}
}
