<?php

declare(strict_types=1);

use Limenet\LaravelBaseline\Rector\Rules\RemoveFactoryDocBlocksRector;
use Limenet\LaravelBaseline\Rector\Rules\RemoveFormRequestDocBlocksRector;
use Limenet\LaravelBaseline\Rector\Rules\RemoveJobDocBlocksRector;
use Limenet\LaravelBaseline\Rector\Rules\RemoveListenerDocBlocksRector;
use Limenet\LaravelBaseline\Rector\Rules\RemoveMailableDocBlocksRector;
use Limenet\LaravelBaseline\Rector\Rules\RemoveMigrationDocBlocksRector;
use Limenet\LaravelBaseline\Rector\Rules\RemoveNotificationDocBlocksRector;
use Limenet\LaravelBaseline\Rector\Rules\RemoveObserverDocBlocksRector;
use Limenet\LaravelBaseline\Rector\Rules\RemovePolicyDocBlocksRector;
use Limenet\LaravelBaseline\Rector\Rules\RemoveSeederDocBlocksRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $config): void {
    $config->withRules([
        RemoveFactoryDocBlocksRector::class,
        RemoveFormRequestDocBlocksRector::class,
        RemoveJobDocBlocksRector::class,
        RemoveListenerDocBlocksRector::class,
        RemoveMailableDocBlocksRector::class,
        RemoveMigrationDocBlocksRector::class,
        RemoveNotificationDocBlocksRector::class,
        RemoveObserverDocBlocksRector::class,
        RemovePolicyDocBlocksRector::class,
        RemoveSeederDocBlocksRector::class,
    ]);
};
