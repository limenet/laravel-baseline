<?php

use Limenet\LaravelBaseline\Health\PhpVersionCheck;
use Spatie\Health\Enums\Status;

it('phpVersionCheck returns ok for PHP >= 8.4', function (): void {
    $result = (new PhpVersionCheck())->run();

    $phpVersion = PHP_MAJOR_VERSION * 100 + PHP_MINOR_VERSION;

    if ($phpVersion >= 804) {
        expect($result->status)->toEqual(Status::ok());
    } elseif ($phpVersion === 803) {
        expect($result->status)->toEqual(Status::warning());
    } else {
        expect($result->status)->toEqual(Status::failed());
    }
});
