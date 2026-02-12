<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Tests\PhpStan\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;

class JobWithUntypedParam implements ShouldQueue
{
    public function __construct(public $data) {}
}
