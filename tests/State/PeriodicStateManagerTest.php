<?php

use Limenet\LaravelBaseline\State\PeriodicStateManager;

it('returns null when config file is missing', function (): void {
    $this->withTempBasePath([]);

    expect(PeriodicStateManager::getLastRun('someCheck'))->toBeNull();
});

it('returns null when periodic key is absent from config', function (): void {
    $this->withTempBasePath([
        'config/baseline.php' => "<?php\n\nreturn ['excludes' => []];\n",
    ]);

    expect(PeriodicStateManager::getLastRun('someCheck'))->toBeNull();
});

it('returns null when specific check is not in periodic state', function (): void {
    $timestamp = (new DateTimeImmutable('2026-01-01'))->format(DateTimeInterface::ATOM);
    $this->withTempBasePath([
        'config/baseline.php' => "<?php\n\nreturn ['excludes' => [], 'periodic' => ['otherCheck' => '{$timestamp}']];\n",
    ]);

    expect(PeriodicStateManager::getLastRun('someCheck'))->toBeNull();
});

it('returns DateTimeImmutable when check entry exists', function (): void {
    $timestamp = (new DateTimeImmutable('2026-01-01T12:00:00+00:00'))->format(DateTimeInterface::ATOM);
    $this->withTempBasePath([
        'config/baseline.php' => "<?php\n\nreturn ['excludes' => [], 'periodic' => ['someCheck' => '{$timestamp}']];\n",
    ]);

    $result = PeriodicStateManager::getLastRun('someCheck');

    expect($result)->toBeInstanceOf(DateTimeImmutable::class);
    expect($result->format(DateTimeInterface::ATOM))->toBe($timestamp);
});

it('setLastRun writes timestamp to config file', function (): void {
    $this->withTempBasePath([
        'config/baseline.php' => "<?php\n\nreturn ['excludes' => []];\n",
    ]);

    $time = new DateTimeImmutable('2026-04-10T12:00:00+00:00');
    PeriodicStateManager::setLastRun('someCheck', $time);

    $result = PeriodicStateManager::getLastRun('someCheck');
    expect($result)->toBeInstanceOf(DateTimeImmutable::class);
    expect($result->format(DateTimeInterface::ATOM))->toBe($time->format(DateTimeInterface::ATOM));
});

it('setLastRun updates an existing entry', function (): void {
    $old = (new DateTimeImmutable('2025-01-01T00:00:00+00:00'))->format(DateTimeInterface::ATOM);
    $this->withTempBasePath([
        'config/baseline.php' => "<?php\n\nreturn ['excludes' => [], 'periodic' => ['someCheck' => '{$old}']];\n",
    ]);

    $new = new DateTimeImmutable('2026-04-10T12:00:00+00:00');
    PeriodicStateManager::setLastRun('someCheck', $new);

    $result = PeriodicStateManager::getLastRun('someCheck');
    expect($result->format(DateTimeInterface::ATOM))->toBe($new->format(DateTimeInterface::ATOM));
});

it('setLastRun preserves existing config keys', function (): void {
    $this->withTempBasePath([
        'config/baseline.php' => "<?php\n\nreturn ['excludes' => ['someOtherCheck']];\n",
    ]);

    PeriodicStateManager::setLastRun('someCheck', new DateTimeImmutable());

    $config = require config_path('baseline.php');
    expect($config['excludes'])->toBe(['someOtherCheck']);
});
