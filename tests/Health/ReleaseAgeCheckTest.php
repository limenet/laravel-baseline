<?php

use Limenet\LaravelBaseline\Health\ReleaseAgeCheck;
use Spatie\Health\Enums\Status;

it('releaseAge passes when composer.json is newer than 6 weeks', function (): void {
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $composerFile = base_path('composer.json');
    touch($composerFile, time() - (10 * 86400));

    $result = (new ReleaseAgeCheck())->run();
    expect($result->status)->toBe(Status::ok());
});

it('releaseAge warns when composer.json is between 6 weeks and 3 months old', function (): void {
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $composerFile = base_path('composer.json');
    touch($composerFile, time() - (50 * 86400));

    $result = (new ReleaseAgeCheck())->run();
    expect($result->status)->toBe(Status::warning());
    expect($result->notificationMessage)->toBe('Release is getting old: last released 50 days ago (should be updated within 6 weeks)');
});

it('releaseAge fails when composer.json is at least 3 months old', function (): void {
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $composerFile = base_path('composer.json');
    touch($composerFile, time() - (90 * 86400));

    $result = (new ReleaseAgeCheck())->run();
    expect($result->status)->toBe(Status::failed());
    expect($result->notificationMessage)->toBe('Release is too old: last released 90 days ago (must be updated within 3 months)');
});
