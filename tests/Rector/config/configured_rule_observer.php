<?php

declare(strict_types=1);

use Limenet\LaravelBaseline\Rector\Rules\RemoveObserverDocBlocksRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $config): void {
    $config->rule(RemoveObserverDocBlocksRector::class);
};
