<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Tests\PhpStan\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\WithoutRelations;

class SendEmail implements ShouldQueue
{
    public function __construct(
        #[WithoutRelations]
        public User $user,
    ) {}
}
