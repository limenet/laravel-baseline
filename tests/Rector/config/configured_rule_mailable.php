<?php

declare(strict_types=1);

use Limenet\LaravelBaseline\Rector\Rules\RemoveMailableDocBlocksRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $config): void {
    $config->rule(RemoveMailableDocBlocksRector::class);
};
