<?php

namespace Limenet\LaravelBaseline\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Limenet\LaravelBaseline\LaravelBaseline
 */
class LaravelBaseline extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Limenet\LaravelBaseline\LaravelBaseline::class;
    }
}
