<?php

declare(strict_types=1);

use Limenet\LaravelBaseline\Rector\Rules\RemoveListenerDocBlocksRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $config): void {
    $config->rule(RemoveListenerDocBlocksRector::class);
};
