<?php

declare(strict_types=1);

use Limenet\LaravelBaseline\Rector\LaravelBaselineSetList;
use Rector\Config\RectorConfig;

return static function (RectorConfig $config): void {
    $config->sets([LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS]);
};
