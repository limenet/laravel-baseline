<?php

use Limenet\LaravelBaseline\Facades\LaravelBaseline;

it('facade returns correct accessor', function (): void {
    $reflection = new ReflectionClass(LaravelBaseline::class);
    $method = $reflection->getMethod('getFacadeAccessor');
    $method->setAccessible(true);

    expect($method->invoke(null))->toBe(\Limenet\LaravelBaseline\LaravelBaseline::class);
});
