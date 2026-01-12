<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\CodeQuality\Rector\Class_\TypeWillReturnCallableArrowFunctionRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\YieldDataProviderRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertCompareOnCountableWithMethodToAssertCountRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertComparisonToSpecificMethodRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertEmptyNullableObjectToAssertInstanceofRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertEqualsOrAssertSameFloatParameterToSpecificMethodsTypeRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertEqualsToSameRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertIssetToSpecificMethodRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertFalseStrposToContainsRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertInstanceOfComparisonRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertNotOperatorRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertRegExpRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertSameBoolNullToSpecificMethodRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertSameTrueFalseToAssertTrueFalseRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertTrueFalseToSpecificMethodRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\FlipAssertRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\MatchAssertSameExpectedTypeRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\NarrowIdenticalWithConsecutiveRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\NarrowSingleWillReturnCallbackRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\RemoveExpectAnyFromMockRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\ScalarArgumentToExpectedParamTypeRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\SingleWithConsecutiveToWithRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\StringCastAssertStringContainsStringRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\UseSpecificWillMethodRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\UseSpecificWithMethodRector;
use Rector\PHPUnit\PHPUnit60\Rector\MethodCall\GetMockBuilderGetMockToCreateMockRector;
use Rector\PHPUnit\PHPUnit70\Rector\Class_\RemoveDataProviderTestPrefixRector;
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
        AssertComparisonToSpecificMethodRector::class,
        AssertEqualsOrAssertSameFloatParameterToSpecificMethodsTypeRector::class,
        AssertEqualsToSameRector::class,
        AssertFalseStrposToContainsRector::class,
        AssertInstanceOfComparisonRector::class,
        AssertEmptyNullableObjectToAssertInstanceofRector::class,
        AssertNotOperatorRector::class,
        AssertRegExpRector::class,
        AssertSameBoolNullToSpecificMethodRector::class,
        AssertSameTrueFalseToAssertTrueFalseRector::class,
        AssertTrueFalseToSpecificMethodRector::class,
        FlipAssertRector::class,
        GetMockBuilderGetMockToCreateMockRector::class,
        MatchAssertSameExpectedTypeRector::class,
        NarrowIdenticalWithConsecutiveRector::class,
        NarrowSingleWillReturnCallbackRector::class,
        RemoveDataProviderTestPrefixRector::class,
        RemoveExpectAnyFromMockRector::class,
        ReplaceAtMethodWithDesiredMatcherRector::class,
        ScalarArgumentToExpectedParamTypeRector::class,
        SingleWithConsecutiveToWithRector::class,
        StringCastAssertStringContainsStringRector::class,
        TypeWillReturnCallableArrowFunctionRector::class,
        UseSpecificWillMethodRector::class,
        UseSpecificWithMethodRector::class,
        YieldDataProviderRector::class,
    ])
    ->withSkip([
        AssertIssetToSpecificMethodRector::class,
    ])
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(6)
    ->withCodeQualityLevel(22);
