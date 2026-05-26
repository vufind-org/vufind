<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\CodeQuality\Rector\ClassMethod\BareCreateMockAssignToDirectUseRector;
use Rector\PHPUnit\CodeQuality\Rector\Expression\AssertArrayCastedObjectToAssertSameRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertIssetToSpecificMethodRector;
use Rector\PHPUnit\CodeQuality\Rector\StmtsAwareInterface\DeclareStrictTypesTestsRector;
use Rector\PHPUnit\PHPUnit120\Rector\CallLike\CreateStubOverCreateMockArgRector;
use Rector\PHPUnit\PHPUnit120\Rector\ClassMethod\ExpressionCreateMockToCreateStubRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Privatization\Rector\Class_\FinalizeTestCaseClassRector;

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
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    ])
    ->withSkip([
        AssertArrayCastedObjectToAssertSameRector::class,
        AssertIssetToSpecificMethodRector::class,
        BareCreateMockAssignToDirectUseRector::class, // buggy, doesn't account for compact() as of 2.4.3
        CreateStubOverCreateMockArgRector::class, // buggy, replaces method on wrong classes as of 2.4.3
        ExpressionCreateMockToCreateStubRector::class, // buggy, replaces method on wrong classes as of 2.4.3
        DeclareStrictTypesTestsRector::class,
        FinalizeTestCaseClassRector::class,
    ])
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(6)
    ->withCodeQualityLevel(23);
