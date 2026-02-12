<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Tests\PhpStan\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;

class JobWithNoConstructor implements ShouldQueue
{
    public function handle(): void {}
}
