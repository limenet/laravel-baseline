<?php

use Limenet\LaravelBaseline\Health\LaravelVersionCheck;
use Spatie\Health\Enums\Status;

it('laravelVersionCheck returns ok for Laravel >= 12', function (): void {
    $result = (new LaravelVersionCheck())->run();

    $major = (int) str(app()->version())->before('.')->toString();

    if ($major >= 12) {
        expect($result->status)->toEqual(Status::ok());
    } elseif ($major === 11) {
        expect($result->status)->toEqual(Status::warning());
    } else {
        expect($result->status)->toEqual(Status::failed());
    }
});
