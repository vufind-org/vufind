<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/harvest',
        __DIR__ . '/import',
        __DIR__ . '/module',
        __DIR__ . '/public',
        __DIR__ . '/tests',
        __DIR__ . '/themes',
        __DIR__ . '/util',
    ])
    // uncomment to reach your current PHP version
    // ->withPhpSets()
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0);
