<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\CodeQuality\Rector\Class_\YieldDataProviderRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertCompareOnCountableWithMethodToAssertCountRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertEqualsOrAssertSameFloatParameterToSpecificMethodsTypeRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertEqualsToSameRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertFalseStrposToContainsRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertInstanceOfComparisonRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertNotOperatorRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\FlipAssertRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\MatchAssertSameExpectedTypeRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\RemoveExpectAnyFromMockRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\SingleWithConsecutiveToWithRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\UseSpecificWillMethodRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\UseSpecificWithMethodRector;
use Rector\PHPUnit\PHPUnit60\Rector\MethodCall\GetMockBuilderGetMockToCreateMockRector;
use Rector\PHPUnit\PHPUnit90\Rector\MethodCall\ReplaceAtMethodWithDesiredMatcherRector;
use Rector\PHPUnit\Set\PHPUnitSetList;

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
        AssertCompareOnCountableWithMethodToAssertCountRector::class,
        AssertEqualsOrAssertSameFloatParameterToSpecificMethodsTypeRector::class,
        AssertEqualsToSameRector::class,
        AssertFalseStrposToContainsRector::class,
        AssertInstanceOfComparisonRector::class,
        AssertNotOperatorRector::class,
        FlipAssertRector::class,
        GetMockBuilderGetMockToCreateMockRector::class,
        MatchAssertSameExpectedTypeRector::class,
        RemoveExpectAnyFromMockRector::class,
        ReplaceAtMethodWithDesiredMatcherRector::class,
        SingleWithConsecutiveToWithRector::class,
        UseSpecificWillMethodRector::class,
        UseSpecificWithMethodRector::class,
        YieldDataProviderRector::class,
    ])
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(6)
    ->withCodeQualityLevel(22);
