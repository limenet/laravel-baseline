<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Tests\PhpStan\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\WithoutRelations;

#[WithoutRelations]
class JobWithClassLevelAttr implements ShouldQueue
{
    public function __construct(public User $user) {}
}
