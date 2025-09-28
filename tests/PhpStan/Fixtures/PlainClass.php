<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Tests\PhpStan\Fixtures;

class PlainClass
{
    public function __construct(public User $user) {}
}
