<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\RemoveExpectAnyFromMockRector;
use Rector\PHPUnit\PHPUnit60\Rector\MethodCall\GetMockBuilderGetMockToCreateMockRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertEqualsToSameRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\UseSpecificWithMethodRector;

return RectorConfig::configure()
    ->withCache(
        cacheClass: FileCacheStorage::class,
        cacheDirectory: __DIR__ . '/../.rector'
    )->withPaths([
        __DIR__ . '/../config',
        __DIR__ . '/../module',
        __DIR__ . '/../public',
    ])
    ->withSets([
        PHPUnitSetList::PHPUNIT_110,
        PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
    ])
    ->withRules([
        RemoveExpectAnyFromMockRector::class,
        GetMockBuilderGetMockToCreateMockRector::class,
        UseSpecificWithMethodRector::class,
        AssertEqualsToSameRector::class,
    ])
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(6)
    ->withCodeQualityLevel(22);
