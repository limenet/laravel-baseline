<?php

declare(strict_types=1);

use Limenet\LaravelBaseline\Rector\Rules\RemovePolicyDocBlocksRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $config): void {
    $config->rule(RemovePolicyDocBlocksRector::class);
};
